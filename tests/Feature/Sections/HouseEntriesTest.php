<?php

declare(strict_types=1);

namespace Tests\Feature\Sections;

use App\Models\Entry;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The home-business page split by house: switching between houses, per-house
 * totals, and entries filed into (and moved between) houses.
 */
class HouseEntriesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->owner = User::factory()->create();
    }

    public function test_all_houses_view_lists_every_house_with_its_totals(): void
    {
        // Arrange
        $chilanzar = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $sergeli = House::factory()->for($this->owner)->create(['name' => 'Сергели']);
        $this->entry($chilanzar, '-3000000');
        $this->entry($chilanzar, '-2000000');
        $this->entry($sergeli, '10000000');
        $this->entry(null, '-500000');

        // Act
        $response = $this->page('/home-business');

        // Assert
        $response->assertInertia(fn (Assert $page) => $page
            ->component('sections/Entries')
            ->where('house', null)
            ->has('entries', 4)
            ->has('houses', 2)
            ->where('houses.0.name', 'Чиланзар')
            ->where('houses.1.name', 'Сергели'));

        $props = $response->inertiaProps();
        $this->assertEquals(['entries' => 4, 'income' => 10000000, 'expense' => 5500000, 'net' => 4500000], $props['totals']);
        $this->assertEquals(['entries' => 2, 'income' => 0, 'expense' => 5000000, 'net' => -5000000], $props['houses'][0]['totals']);
        $this->assertEquals(['entries' => 1, 'income' => 10000000, 'expense' => 0, 'net' => 10000000], $props['houses'][1]['totals']);
        $this->assertEquals(['entries' => 1, 'income' => 0, 'expense' => 500000, 'net' => -500000], $props['unassigned']);
    }

    public function test_selecting_a_house_shows_only_its_entries_and_totals(): void
    {
        // Arrange
        $chilanzar = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $sergeli = House::factory()->for($this->owner)->create(['name' => 'Сергели']);
        $this->entry($chilanzar, '-3000000', 'Кирпич');
        $this->entry($sergeli, '-1000000', 'Цемент');

        // Act
        $response = $this->page('/home-business?house='.$chilanzar->id);

        // Assert
        $response->assertInertia(fn (Assert $page) => $page
            ->where('house', $chilanzar->id)
            ->has('entries', 1)
            ->where('entries.0.body', 'Кирпич')
            ->where('entries.0.house_id', $chilanzar->id)
            ->has('houses', 2));

        $this->assertEquals(-3000000, $response->inertiaProps('totals.net'));
    }

    public function test_the_no_house_view_shows_entries_that_are_not_filed_under_a_house(): void
    {
        $house = House::factory()->for($this->owner)->create();
        $this->entry($house, '-3000000', 'В доме');
        $this->entry(null, '-1000000', 'Без дома');

        $this->page('/home-business?house=none')
            ->assertInertia(fn (Assert $page) => $page
                ->where('house', 'none')
                ->has('entries', 1)
                ->where('entries.0.body', 'Без дома'));
    }

    public function test_an_unknown_or_foreign_house_falls_back_to_all_houses(): void
    {
        $foreign = House::factory()->create();
        $this->entry(null, '-1000000');

        $this->page('/home-business?house='.$foreign->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('house', null)
                ->has('entries', 1));
    }

    public function test_totals_cover_every_entry_not_just_the_listed_page(): void
    {
        // Arrange — more entries than the page lists.
        $house = House::factory()->for($this->owner)->create();
        Entry::factory()->for($this->owner)->count(205)->create([
            'section' => 'home-business',
            'house_id' => $house->id,
            'amount' => '-1000',
        ]);

        // Act
        $response = $this->page('/home-business?house='.$house->id);

        // Assert
        $response->assertInertia(fn (Assert $page) => $page->has('entries', 200));
        $this->assertEquals(-205000, $response->inertiaProps('totals.net'));
        $this->assertEquals(205, $response->inertiaProps('totals.entries'));
    }

    public function test_sections_without_houses_get_no_house_props(): void
    {
        Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'amount' => '-80000',
        ]);

        $response = $this->page('/budget')
            ->assertInertia(fn (Assert $page) => $page
                ->where('houses', null)
                ->where('unassigned', null)
                ->where('house', null));

        $this->assertEquals(-80000, $response->inertiaProps('totals.net'));
    }

    public function test_an_entry_is_filed_under_the_chosen_house(): void
    {
        // Arrange
        $house = House::factory()->for($this->owner)->create();

        // Act
        $this->actingAs($this->owner)->post('/home-business/entries', [
            'body' => 'Кирпич',
            'amount' => '3000000',
            'direction' => 'expense',
            'house_id' => $house->id,
        ])->assertSessionHasNoErrors();

        // Assert
        $entry = Entry::query()->sole();
        $this->assertSame($house->id, $entry->house_id);
        $this->assertSame(-3000000.0, (float) $entry->amount);
    }

    public function test_an_entry_cannot_be_filed_under_another_owners_house(): void
    {
        $foreign = House::factory()->create();

        $this->actingAs($this->owner)
            ->from('/home-business')
            ->post('/home-business/entries', [
                'body' => 'Кирпич',
                'house_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('house_id');

        $this->assertSame(0, Entry::query()->count());
    }

    public function test_a_house_is_ignored_for_sections_that_are_not_split_by_house(): void
    {
        $house = House::factory()->for($this->owner)->create();

        $this->actingAs($this->owner)->post('/budget/entries', [
            'body' => 'Продукты',
            'amount' => '80000',
            'direction' => 'expense',
            'house_id' => $house->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Entry::query()->sole()->house_id);
    }

    public function test_an_entry_can_be_moved_to_another_house(): void
    {
        // Arrange
        $from = House::factory()->for($this->owner)->create();
        $to = House::factory()->for($this->owner)->create();
        $entry = $this->entry($from, '-3000000', 'Кирпич');

        // Act
        $this->actingAs($this->owner)->put("/entries/{$entry->id}", [
            'section' => 'home-business',
            'body' => 'Кирпич',
            'amount' => '3000000',
            'direction' => 'expense',
            'house_id' => $to->id,
        ])->assertSessionHasNoErrors();

        // Assert
        $this->assertSame($to->id, $entry->refresh()->house_id);
    }

    public function test_editing_without_a_house_field_keeps_the_house(): void
    {
        $house = House::factory()->for($this->owner)->create();
        $entry = $this->entry($house, '-3000000', 'Кирпич');

        $this->actingAs($this->owner)->put("/entries/{$entry->id}", [
            'section' => 'home-business',
            'body' => 'Кирпич, 2 поддона',
            'amount' => '3000000',
            'direction' => 'expense',
        ])->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertSame($house->id, $entry->house_id);
        $this->assertSame('Кирпич, 2 поддона', $entry->body);
    }

    public function test_moving_an_entry_out_of_home_business_drops_its_house(): void
    {
        $house = House::factory()->for($this->owner)->create();
        $entry = $this->entry($house, '-80000', 'Продукты');

        $this->actingAs($this->owner)->put("/entries/{$entry->id}", [
            'section' => 'budget',
            'body' => 'Продукты',
            'amount' => '80000',
            'direction' => 'expense',
            'house_id' => $house->id,
        ])->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertSame('budget', $entry->section);
        $this->assertNull($entry->house_id);
    }

    private function entry(?House $house, string $amount, string $body = 'Запись'): Entry
    {
        return Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'house_id' => $house?->id,
            'body' => $body,
            'amount' => $amount,
        ]);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function page(string $url): TestResponse
    {
        return $this->actingAs($this->owner)->get($url)->assertOk();
    }
}
