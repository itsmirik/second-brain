<?php

declare(strict_types=1);

namespace Tests\Feature\Sections;

use App\Models\Entry;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
    }

    public function test_it_adds_a_house_and_opens_it(): void
    {
        // Act
        $response = $this->actingAs($this->owner)
            ->post('/home-business/houses', ['name' => '  Дом на   Чиланзаре ']);

        // Assert
        $house = House::query()->sole();
        $this->assertSame('Дом на Чиланзаре', $house->name);
        $this->assertSame($this->owner->id, $house->user_id);
        $response->assertRedirect('/home-business?house='.$house->id);
    }

    public function test_a_house_name_is_unique_per_owner_ignoring_case(): void
    {
        // Arrange
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);

        // Act
        $response = $this->actingAs($this->owner)
            ->from('/home-business')
            ->post('/home-business/houses', ['name' => 'ЧИЛАНЗАР']);

        // Assert
        $response->assertSessionHasErrors('name');
        $this->assertSame(1, House::query()->count());
    }

    public function test_a_house_needs_a_name(): void
    {
        $this->actingAs($this->owner)
            ->from('/home-business')
            ->post('/home-business/houses', ['name' => '   '])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, House::query()->count());
    }

    public function test_it_renames_a_house(): void
    {
        // Arrange
        $house = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);

        // Act
        $response = $this->actingAs($this->owner)
            ->from('/home-business?house='.$house->id)
            ->put("/home-business/houses/{$house->id}", ['name' => 'Чиланзар, 9 квартал']);

        // Assert
        $response->assertRedirect('/home-business?house='.$house->id);
        $this->assertSame('Чиланзар, 9 квартал', $house->refresh()->name);
    }

    public function test_a_house_can_be_renamed_to_its_own_name_in_another_case(): void
    {
        $house = House::factory()->for($this->owner)->create(['name' => 'чиланзар']);

        $this->actingAs($this->owner)
            ->put("/home-business/houses/{$house->id}", ['name' => 'Чиланзар'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Чиланзар', $house->refresh()->name);
    }

    public function test_a_house_cannot_take_another_houses_name(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Сергели']);
        $house = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);

        $this->actingAs($this->owner)
            ->from('/home-business')
            ->put("/home-business/houses/{$house->id}", ['name' => 'сергели'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Чиланзар', $house->refresh()->name);
    }

    public function test_deleting_a_house_keeps_its_entries_in_the_section(): void
    {
        // Arrange
        $house = House::factory()->for($this->owner)->create();
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'house_id' => $house->id,
            'amount' => '-500000',
        ]);

        // Act
        $response = $this->actingAs($this->owner)
            ->delete("/home-business/houses/{$house->id}");

        // Assert
        $response->assertRedirect('/home-business');
        $this->assertModelMissing($house);

        $entry->refresh();
        $this->assertNull($entry->house_id);
        $this->assertSame('home-business', $entry->section);
        $this->assertSame(-500000.0, (float) $entry->amount);
    }

    public function test_it_refuses_to_touch_another_owners_house(): void
    {
        $house = House::factory()->create(['name' => 'Чужой дом']);

        $this->actingAs($this->owner)
            ->put("/home-business/houses/{$house->id}", ['name' => 'взлом'])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->delete("/home-business/houses/{$house->id}")
            ->assertForbidden();

        $this->assertSame('Чужой дом', $house->refresh()->name);
    }

    public function test_sections_that_are_not_split_by_house_have_no_house_routes(): void
    {
        $this->actingAs($this->owner)
            ->post('/budget/houses', ['name' => 'Дом'])
            ->assertNotFound();
    }

    public function test_guests_cannot_add_houses(): void
    {
        $this->post('/home-business/houses', ['name' => 'Дом'])->assertRedirect('/login');

        $this->assertSame(0, House::query()->count());
    }
}
