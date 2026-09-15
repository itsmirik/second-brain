<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\MoneyReportTool;
use App\Models\Entry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class MoneyReportToolTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00'));

        $this->owner = User::factory()->create(['email' => 'owner@example.test']);

        config([
            'dashboard.owner_email' => $this->owner->email,
            'atheer.api_token' => 'test-token',
            'atheer.cache_ttl' => 0,
        ]);
    }

    public function test_it_nets_atheer_with_the_money_sections(): void
    {
        // Arrange
        Http::fake(['*/api/reports/finance*' => Http::response([
            'revenue' => 1000000,
            'refunds' => 100000,
            'profit' => 400000,
        ])]);

        Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Продукты',
            'amount' => '-200000',
            'occurred_at' => '2026-09-05',
        ]);

        // Act
        $result = $this->report(['period' => 'this_month']);

        // Assert
        $this->assertSame('2026-09-01', $result['range']['from']);
        $this->assertSame(200000.0, $result['net']);

        $sources = collect($result['sources'])->keyBy('key');
        $this->assertTrue($sources['atheer']['available']);
        $this->assertSame(400000.0, $sources['atheer']['net']);
        $this->assertSame(-200000.0, $sources['budget']['net']);
    }

    public function test_it_still_reports_when_atheer_is_down(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

        Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Зарплата',
            'amount' => '500000',
            'occurred_at' => '2026-09-05',
        ]);

        $result = $this->report(['period' => 'this_month']);

        $sources = collect($result['sources'])->keyBy('key');
        $this->assertFalse($sources['atheer']['available']);
        $this->assertSame(500000.0, $result['net']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function report(array $arguments): array
    {
        $raw = app(MoneyReportTool::class)->handle(new Request($arguments));

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
