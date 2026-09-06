<?php

declare(strict_types=1);

namespace App\Services\Atheer;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin read-only client for the Atheer ERP report API. Responses are cached
 * briefly so repeated questions don't hammer the ERP.
 */
class AtheerApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly int $cacheTtl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('atheer.api_url', 'http://127.0.0.1:8000'),
            token: config('atheer.api_token'),
            cacheTtl: (int) config('atheer.cache_ttl', 900),
        );
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return $this->get('/api/reports/summary');
    }

    /** @return array<string, mixed> */
    public function funnel(): array
    {
        return $this->get('/api/reports/funnel');
    }

    /** @return array<string, mixed> */
    public function deliveries(): array
    {
        return $this->get('/api/reports/deliveries');
    }

    /** @return array<string, mixed> */
    public function reconciliation(): array
    {
        return $this->get('/api/reports/reconciliation');
    }

    /** @return array<string, mixed> */
    public function finance(?string $from = null, ?string $to = null): array
    {
        return $this->get('/api/reports/finance', array_filter([
            'from' => $from,
            'to' => $to,
        ]));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        if (empty($this->token)) {
            throw new RuntimeException('ATHEER_API_TOKEN is not configured.');
        }

        $cacheKey = 'atheer:'.md5($path.'?'.http_build_query($query));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($path, $query): array {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->token,
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->get($this->baseUrl.$path, $query);

            if ($response->failed()) {
                throw new RuntimeException("Atheer API [{$path}] failed with status {$response->status()}.");
            }

            return (array) $response->json();
        });
    }
}
