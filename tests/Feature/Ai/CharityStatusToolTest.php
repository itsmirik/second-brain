<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\CharityStatusTool;
use App\Models\Entry;
use App\Models\User;
use App\Support\Charity\CharityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CharityStatusToolTest extends TestCase
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

    public function test_it_reports_obligation_given_and_remaining_per_month(): void
    {
        // Arrange — Atheer has no token here, so it degrades to zero.
        app(CharityService::class)->setPercentage($this->owner->id, 10.0);

        Entry::factory()->for($this->owner)->create([
            'section' => 'home-business',
            'body' => 'Оплата заказа',
            'amount' => '1000000',
            'occurred_at' => '2026-09-04',
        ]);
        Entry::factory()->for($this->owner)->create([
            'section' => 'charity',
            'body' => 'Садака',
            'amount' => '40000',
            'occurred_at' => '2026-09-10',
        ]);

        // Act
        $raw = app(CharityStatusTool::class)->handle(new Request(['months' => 2]));

        /** @var array<string, mixed> $result */
        $result = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        // Assert
        $this->assertSame(10.0, $result['percentage']);
        $this->assertCount(2, $result['months']);

        $current = $result['months'][0];
        $this->assertSame('2026-09', $current['month']);
        $this->assertSame(1000000.0, $current['profit']);
        $this->assertSame(100000.0, $current['obligation']);
        $this->assertSame(40000.0, $current['given']);
        $this->assertSame(60000.0, $current['remaining']);
    }
}
