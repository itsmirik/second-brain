<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\LogEntryTool;
use App\Models\Entry;
use App\Models\House;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class LogEntryToolTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-02 12:00:00'));

        $this->owner = User::factory()->create(['email' => 'owner@example.test']);

        config(['dashboard.owner_email' => $this->owner->email]);
    }

    public function test_it_saves_a_signed_amount_into_a_section(): void
    {
        // Act
        $result = $this->log(['section' => 'budget', 'body' => 'Продукты', 'amount' => -80000]);

        // Assert
        $entry = Entry::query()->sole();
        $this->assertSame('budget', $entry->section);
        $this->assertSame(-80000.0, (float) $entry->amount);
        $this->assertSame('2026-10-02', $entry->occurred_at->toDateString());
        $this->assertNull($entry->house_id);
        $this->assertStringContainsString('Saved', $result);
    }

    public function test_it_files_a_home_business_entry_under_the_named_house(): void
    {
        // Arrange
        $house = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        House::factory()->for($this->owner)->create(['name' => 'Сергели']);

        // Act
        $result = $this->log([
            'section' => 'home-business',
            'body' => 'Кирпич',
            'amount' => -3000000,
            'house' => 'чиланзар',
        ]);

        // Assert
        $this->assertSame($house->id, Entry::query()->sole()->house_id);
        $this->assertStringContainsString('«Чиланзар»', $result);
    }

    public function test_part_of_a_house_name_finds_the_house(): void
    {
        $house = House::factory()->for($this->owner)->create(['name' => 'Дом на Чиланзаре']);
        House::factory()->for($this->owner)->create(['name' => 'Сергели']);

        $this->log(['section' => 'home-business', 'body' => 'Кирпич', 'amount' => -3000000, 'house' => 'Чиланзар']);

        $this->assertSame($house->id, Entry::query()->sole()->house_id);
    }

    public function test_a_name_that_fits_several_houses_picks_none_of_them(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар 1']);
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар 2']);

        $result = $this->log(['section' => 'home-business', 'body' => 'Кирпич', 'amount' => -3000000, 'house' => 'Чиланзар']);

        $this->assertNull(Entry::query()->sole()->house_id);
        $this->assertStringContainsString('«Чиланзар 1»', $result);
        $this->assertStringContainsString('«Чиланзар 2»', $result);
    }

    public function test_the_only_house_is_used_when_none_is_named(): void
    {
        $house = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);

        $this->log(['section' => 'home-business', 'body' => 'Кирпич', 'amount' => -3000000]);

        $this->assertSame($house->id, Entry::query()->sole()->house_id);
    }

    public function test_with_several_houses_and_none_named_it_saves_the_entry_and_asks_which_house(): void
    {
        // Arrange
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        House::factory()->for($this->owner)->create(['name' => 'Сергели']);

        // Act
        $result = $this->log(['section' => 'home-business', 'body' => 'Кирпич', 'amount' => -3000000]);

        // Assert — the record is kept, and the model is told how to finish it.
        $entry = Entry::query()->sole();
        $this->assertNull($entry->house_id);
        $this->assertStringContainsString('#'.$entry->id, $result);
        $this->assertStringContainsString('«Чиланзар»', $result);
        $this->assertStringContainsString('«Сергели»', $result);
    }

    public function test_an_unknown_house_is_not_guessed_or_created(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);

        $result = $this->log(['section' => 'home-business', 'body' => 'Кирпич', 'amount' => -3000000, 'house' => 'Юнусабад']);

        $this->assertSame(1, House::query()->count());
        $this->assertNull(Entry::query()->sole()->house_id);
        $this->assertStringContainsString('Юнусабад', $result);
        $this->assertStringContainsString('«Чиланзар»', $result);
    }

    public function test_a_house_is_ignored_outside_home_business(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);

        $this->log(['section' => 'budget', 'body' => 'Продукты', 'amount' => -80000, 'house' => 'Чиланзар']);

        $this->assertNull(Entry::query()->sole()->house_id);
    }

    public function test_another_owners_house_is_never_matched(): void
    {
        House::factory()->create(['name' => 'Чиланзар']);

        $this->log(['section' => 'home-business', 'body' => 'Кирпич', 'amount' => -3000000, 'house' => 'Чиланзар']);

        $this->assertNull(Entry::query()->sole()->house_id);
    }

    public function test_the_description_lists_the_owners_houses(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);

        $description = (string) app(LogEntryTool::class)->description();

        $this->assertStringContainsString('«Чиланзар»', $description);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function log(array $arguments): string
    {
        return (string) app(LogEntryTool::class)->handle(new Request($arguments));
    }
}
