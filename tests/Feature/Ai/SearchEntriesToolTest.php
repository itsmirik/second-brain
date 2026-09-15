<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\SearchEntriesTool;
use App\Models\Entry;
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

    public function test_it_rejects_an_unknown_section(): void
    {
        $raw = (new SearchEntriesTool(app(\App\Ai\Support\OwnerResolver::class)))
            ->handle(new Request(['sections' => ['crypto']]));

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
