<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\AtheerReportsTool;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AtheerReportsToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'atheer.api_url' => 'http://atheer.test',
            'atheer.api_token' => 'test-token',
            'atheer.cache_ttl' => 0,
        ]);
    }

    public function test_it_asks_the_erp_what_the_instagram_dms_are_requesting(): void
    {
        // Arrange
        Http::fake(['*/api/reports/lead-demand*' => Http::response([
            'from' => '2026-08-17',
            'to' => '2026-09-15',
            'products' => [
                ['product_id' => 3, 'name' => 'Lattafa Yara', 'leads' => 31, 'mentions' => 47],
            ],
            'unmatched_terms' => [['term' => 'chanel', 'count' => 9]],
        ])]);

        // Act
        $result = app(AtheerReportsTool::class)->handle(new Request([
            'report' => 'lead-demand',
            'from' => '2026-08-17',
            'to' => '2026-09-15',
            'limit' => 5,
        ]));

        // Assert — the model gets the raw figures to phrase, Cyrillic and all.
        $this->assertStringContainsString('Lattafa Yara', $result);
        $this->assertStringContainsString('unmatched_terms', $result);

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/reports/lead-demand')
            && str_contains($request->url(), 'from=2026-08-17')
            && str_contains($request->url(), 'to=2026-09-15')
            && str_contains($request->url(), 'limit=5'));
    }

    public function test_it_degrades_to_a_plain_message_when_the_erp_is_down(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

        $result = app(AtheerReportsTool::class)->handle(new Request(['report' => 'lead-demand']));

        $this->assertSame('Atheer data is temporarily unavailable.', $result);
    }

    public function test_an_unknown_report_falls_back_to_the_summary(): void
    {
        Http::fake(['*/api/reports/summary' => Http::response(['funnel' => []])]);

        $result = app(AtheerReportsTool::class)->handle(new Request(['report' => 'nonsense']));

        $this->assertStringContainsString('funnel', $result);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/api/reports/summary'));
    }
}
