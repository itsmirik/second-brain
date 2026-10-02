<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\SearchEntriesTool;
use App\Models\Entry;
use App\Models\House;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class SearchEntriesToolTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));

        $this->owner = User::factory()->create(['email' => 'owner@example.test']);

        config(['dashboard.owner_email' => $this->owner->email]);
    }

    public function test_it_returns_entries_and_totals_for_a_section_and_period(): void
    {
        // Arrange
        Entry::factory()->for($this->owner)->create([
            'section' => 'charity',
            'body' => 'Мечеть, садака',
            'amount' => '500000',
            'occurred_at' => '2026-08-03',
        ]);
        Entry::factory()->for($this->owner)->create([
            'section' => 'charity',
            'body' => 'Сентябрьская садака',
            'amount' => '200000',
            'occurred_at' => '2026-09-02',
        ]);

        // Act
        $result = $this->search(['sections' => ['charity'], 'period' => 'last_month']);

        // Assert
        $this->assertSame(1, $result['matched']);
        $this->assertSame('2026-08-01', $result['range']['from']);
        $this->assertSame('2026-08-31', $result['range']['to']);
        $this->assertSame(500000.0, $result['totals']['sum']);
        $this->assertSame('Мечеть, садака', $result['entries'][0]['body']);
        $this->assertSame('2026-08-03', $result['entries'][0]['date']);
    }

    public function test_it_splits_income_and_expense_and_breaks_down_by_section(): void
    {
        // Arrange
        Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Зарплата',
            'amount' => '3000000',
            'occurred_at' => '2026-09-01',
        ]);
        Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Продукты',
            'amount' => '-80000',
            'occurred_at' => '2026-09-05',
        ]);
        Entry::factory()->for($this->owner)->create([
            'section' => 'health',
            'body' => 'Пробежка 5 км',
            'occurred_at' => '2026-09-05',
        ]);

        // Act
        $result = $this->search(['period' => 'this_month']);

        // Assert
        $this->assertSame(3, $result['matched']);
        $this->assertSame(3000000.0, $result['totals']['income']);
        $this->assertSame(80000.0, $result['totals']['expense']);
        $this->assertSame(2920000.0, $result['totals']['sum']);

        $bySection = collect($result['by_section'])->keyBy('section');
        $this->assertSame(2, $bySection['budget']['entries']);
        $this->assertSame(2920000.0, $bySection['budget']['sum']);
        $this->assertSame(1, $bySection['health']['entries']);
    }

    public function test_entries_on_the_last_day_of_the_range_are_included(): void
    {
        // Arrange — "today" is the last day of both windows below.
        Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Обед',
            'amount' => '-60000',
            'occurred_at' => '2026-09-15',
        ]);

        // Act
        $today = $this->search(['period' => 'today']);
        $all = $this->search(['period' => 'all']);

        // Assert
        $this->assertSame(1, $today['matched']);
        $this->assertSame(1, $all['matched']);
        $this->assertSame(-60000.0, $all['totals']['sum']);
    }

    public function test_it_filters_by_free_text(): void
    {
        Entry::factory()->for($this->owner)->create([
            'section' => 'personal',
            'body' => 'Позвонить поставщику по коробкам',
            'occurred_at' => '2026-09-10',
        ]);
        Entry::factory()->for($this->owner)->create([
            'section' => 'personal',
            'body' => 'Купить цветы',
            'occurred_at' => '2026-09-10',
        ]);

        $result = $this->search(['query' => 'поставщику', 'period' => 'this_month']);

        $this->assertSame(1, $result['matched']);
        $this->assertStringContainsString('поставщику', $result['entries'][0]['body']);
    }

    public function test_it_never_returns_another_users_entries(): void
    {
        $stranger = User::factory()->create();

        Entry::factory()->for($stranger)->create([
            'section' => 'budget',
            'body' => 'Чужая запись',
            'amount' => '999',
            'occurred_at' => '2026-09-05',
        ]);

        $result = $this->search(['period' => 'this_month']);

        $this->assertSame(0, $result['matched']);
        $this->assertSame([], $result['entries']);
    }

    public function test_it_narrows_to_one_house_and_totals_every_house(): void
    {
        // Arrange
        $chilanzar = House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);
        $sergeli = House::factory()->for($this->owner)->create(['name' => 'Сергели']);
        Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'house_id' => $chilanzar->id,
            'body' => 'Кирпич',
            'amount' => '-3000000',
            'occurred_at' => '2026-09-05',
        ]);
        Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'house_id' => $sergeli->id,
            'body' => 'Цемент',
            'amount' => '-1000000',
            'occurred_at' => '2026-09-06',
        ]);
        Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'body' => 'Инструменты',
            'amount' => '-500000',
            'occurred_at' => '2026-09-07',
        ]);

        // Act
        $one = $this->search(['house' => 'чиланзар', 'period' => 'this_month']);
        $all = $this->search(['sections' => ['home-business'], 'period' => 'this_month']);

        // Assert
        $this->assertSame('Чиланзар', $one['house']);
        $this->assertSame(1, $one['matched']);
        $this->assertSame('Кирпич', $one['entries'][0]['body']);
        $this->assertSame('Чиланзар', $one['entries'][0]['house']);
        $this->assertSame(-3000000.0, $one['totals']['sum']);

        $byHouse = collect($all['by_house'])->keyBy(fn (array $row): string => $row['house'] ?? 'none');
        $this->assertSame(-3000000.0, $byHouse['Чиланзар']['sum']);
        $this->assertSame(-1000000.0, $byHouse['Сергели']['sum']);
        $this->assertSame(-500000.0, $byHouse['none']['sum']);
        $this->assertSame(1, $byHouse['none']['entries']);
    }

    public function test_an_unknown_house_is_reported_with_the_known_ones(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Чиланзар']);

        $raw = (string) app(SearchEntriesTool::class)->handle(new Request(['house' => 'Юнусабад']));

        $this->assertStringContainsString('Юнусабад', $raw);
        $this->assertStringContainsString('«Чиланзар»', $raw);
    }

    public function test_results_without_houses_carry_no_house_breakdown(): void
    {
        Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Продукты',
            'amount' => '-80000',
            'occurred_at' => '2026-09-05',
        ]);

        $result = $this->search(['period' => 'this_month']);

        $this->assertArrayNotHasKey('by_house', $result);
        $this->assertNull($result['entries'][0]['house']);
    }

    public function test_it_rejects_an_unknown_section(): void
    {
        $raw = (string) app(SearchEntriesTool::class)->handle(new Request(['sections' => ['crypto']]));

        $this->assertStringContainsString('crypto', $raw);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function search(array $arguments): array
    {
        $raw = app(SearchEntriesTool::class)->handle(new Request($arguments));

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
