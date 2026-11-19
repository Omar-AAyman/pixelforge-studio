<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PixelforgeClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function pricing(): array
    {
        return Cache::remember('pixelforge.pricing', now()->addMinutes(10), fn () => $this->decode(Http::baseUrl($this->baseUrl)->acceptJson()->connectTimeout(5)->timeout($this->timeoutSeconds)->get('/pricing'))
        );
    }

    public function createGeneration(array $params, string $idempotencyKey): array
    {
        return $this->decode($this->request()->withHeaders(['Idempotency-Key' => $idempotencyKey])->post('/generations', $params));
    }

    public function getGeneration(string $pixelforgeId): array
    {
        return $this->decode($this->request()->get('/generations/'.rawurlencode($pixelforgeId)));
    }

    public function listGenerations(array $query = []): array
    {
        return $this->decode($this->request()->get('/generations', $query));
    }

    public function usage(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->decode($this->request()->get('/usage', array_filter([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], fn ($value) => $value !== null)));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)->withToken($this->apiKey)->acceptJson()->asJson()
            ->connectTimeout(5)->timeout($this->timeoutSeconds);
    }

    private function decode(Response $response): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        throw PixelforgeException::fromResponse($response);
    }
}
