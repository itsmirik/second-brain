<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\UpdateEntryTool;
use App\Models\Entry;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class UpdateEntryToolTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'owner@example.test']);

        config(['dashboard.owner_email' => $this->owner->email]);
    }

    public function test_it_updates_body_amount_and_date(): void
    {
        // Arrange
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Продукты',
            'amount' => '-80000',
            'occurred_at' => '2026-09-05',
        ]);

        // Act
        $result = $this->update([
            'id' => $entry->id,
            'body' => 'Продукты и бытовая химия',
            'amount' => -95000,
            'occurred_at' => '2026-09-06',
        ]);

        // Assert
        $entry->refresh();
        $this->assertSame('Продукты и бытовая химия', $entry->body);
        $this->assertSame(-95000.0, (float) $entry->amount);
        $this->assertSame('2026-09-06', $entry->occurred_at->toDateString());
        $this->assertStringContainsString('Updated', $result);
    }

    public function test_it_moves_an_entry_to_another_section_and_drops_amount_for_non_money_sections(): void
    {
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Записал не туда',
            'amount' => '-50000',
            'occurred_at' => '2026-09-05',
        ]);

        $this->update(['id' => $entry->id, 'section' => 'personal']);

        $entry->refresh();
        $this->assertSame('personal', $entry->section);
        $this->assertNull($entry->amount);
    }

    public function test_it_refuses_to_touch_another_users_entry(): void
    {
        $stranger = User::factory()->create();

        $entry = Entry::factory()->for($stranger)->create([
            'section' => 'budget',
            'body' => 'Чужая запись',
            'amount' => '999',
        ]);

        $result = $this->update(['id' => $entry->id, 'body' => 'взлом']);

        $entry->refresh();
        $this->assertSame('Чужая запись', $entry->body);
        $this->assertStringContainsString('No entry', $result);
    }

    public function test_it_rejects_an_unknown_section(): void
    {
        $entry = Entry::factory()->for($this->owner)->create(['section' => 'personal']);

        $result = $this->update(['id' => $entry->id, 'section' => 'crypto']);

        $entry->refresh();
        $this->assertSame('personal', $entry->section);
        $this->assertStringContainsString('crypto', $result);
    }

    public function test_it_moves_an_entry_to_another_house(): void
    {
        // Arrange
        $from = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $to = House::factory()->for($this->owner)->create(['name' => 'Сергели']);
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'house_id' => $from->id,
            'amount' => '-3000000',
        ]);

        // Act
        $result = $this->update(['id' => $entry->id, 'house' => 'сергели']);

        // Assert
        $this->assertSame($to->id, $entry->refresh()->house_id);
        $this->assertStringContainsString('«Сергели»', $result);
    }

    public function test_an_unknown_house_changes_nothing(): void
    {
        $house = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'house_id' => $house->id,
            'body' => 'Кирпич',
        ]);

        $result = $this->update(['id' => $entry->id, 'house' => 'Юнусабад', 'body' => 'Цемент']);

        $entry->refresh();
        $this->assertSame($house->id, $entry->house_id);
        $this->assertSame('Кирпич', $entry->body);
        $this->assertStringContainsString('Юнусабад', $result);
        $this->assertStringContainsString('«Чиланзар»', $result);
    }

    public function test_a_house_cannot_be_set_on_a_section_without_houses(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $entry = Entry::factory()->for($this->owner)->create(['section' => 'budget', 'amount' => '-80000']);

        $result = $this->update(['id' => $entry->id, 'house' => 'Чиланзар']);

        $entry->refresh();
        $this->assertSame('budget', $entry->section);
        $this->assertNull($entry->house_id);
        $this->assertStringContainsString('home-business', $result);
    }

    public function test_moving_into_home_business_can_set_the_house(): void
    {
        $house = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $entry = Entry::factory()->for($this->owner)->create(['section' => 'budget', 'amount' => '-80000']);

        $this->update(['id' => $entry->id, 'section' => 'home-business', 'house' => 'Чиланзар']);

        $entry->refresh();
        $this->assertSame('home-business', $entry->section);
        $this->assertSame($house->id, $entry->house_id);
    }

    public function test_moving_into_home_business_without_a_house_asks_which_one(): void
    {
        // Arrange
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        House::factory()->for($this->owner)->create(['name' => 'Сергели']);
        $entry = Entry::factory()->for($this->owner)->create(['section' => 'budget', 'amount' => '-80000']);

        // Act
        $result = $this->update(['id' => $entry->id, 'section' => 'home-business']);

        // Assert — moved, and the model is told to settle the house.
        $entry->refresh();
        $this->assertSame('home-business', $entry->section);
        $this->assertNull($entry->house_id);
        $this->assertStringContainsString('«Чиланзар»', $result);
        $this->assertStringContainsString('«Сергели»', $result);
    }

    public function test_moving_into_home_business_uses_the_only_house(): void
    {
        $house = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $entry = Entry::factory()->for($this->owner)->create(['section' => 'budget', 'amount' => '-80000']);

        $this->update(['id' => $entry->id, 'section' => 'home-business']);

        $this->assertSame($house->id, $entry->refresh()->house_id);
    }

    public function test_editing_a_home_business_entry_never_guesses_its_house(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'body' => 'Гвозди',
            'amount' => '-50000',
        ]);

        $this->update(['id' => $entry->id, 'amount' => -60000]);

        $this->assertNull($entry->refresh()->house_id);
    }

    public function test_moving_out_of_home_business_drops_the_house(): void
    {
        $house = House::factory()->for($this->owner)->create();
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'house_id' => $house->id,
            'amount' => '-80000',
        ]);

        $this->update(['id' => $entry->id, 'section' => 'budget']);

        $entry->refresh();
        $this->assertSame('budget', $entry->section);
        $this->assertNull($entry->house_id);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function update(array $arguments): string
    {
        return app(UpdateEntryTool::class)->handle(new Request($arguments));
    }
}
