<?php

use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    $this->client = new PixelforgeClient('https://pf.test/v1', 'test-key');
});

it('sends authentication, parameters and the idempotency key when creating', function () {
    Http::fake(['*' => Http::response(['id' => 'gen_1', 'status' => 'queued'], 202)]);

    expect($this->client->createGeneration(['prompt' => 'hi'], 'local-123')['id'])->toBe('gen_1');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://pf.test/v1/generations'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->hasHeader('Idempotency-Key', 'local-123')
        && $request['prompt'] === 'hi');
});

it('caches public pricing without authentication', function () {
    Cache::flush();
    Http::fake(['*' => Http::response(['rates' => ['output_tokens' => '40.00']])]);
    expect($this->client->pricing())->toBe($this->client->pricing());
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://pf.test/v1/pricing' && ! $request->hasHeader('Authorization'));
});

it('retrieves, lists and requests usage with optional dates', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);
    $this->client->getGeneration('gen_1');
    $this->client->listGenerations(['limit' => 2]);
    $this->client->usage('2026-11-01', '2026-11-02');
    $this->client->usage();
    Http::assertSent(fn (Request $r) => $r->url() === 'https://pf.test/v1/generations/gen_1' && $r->hasHeader('Authorization', 'Bearer test-key'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/generations?limit=2'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'start_date=2026-11-01') && str_contains($r->url(), 'end_date=2026-11-02'));
    Http::assertSent(fn (Request $r) => $r->url() === 'https://pf.test/v1/usage');
});

it('maps retryable errors and Retry-After', function (int $status, string $code) {
    Http::fake(['*' => Http::response(['error' => ['type' => 'api_error', 'code' => $code, 'message' => 'Try later']], $status, ['Retry-After' => '5'])]);
    try {
        $this->client->createGeneration(['prompt' => 'hi'], 'k');
        $this->fail('Expected exception');
    } catch (PixelforgeException $e) {
        expect($e->status)->toBe($status)->and($e->errorCode)->toBe($code)
            ->and($e->errorType)->toBe('api_error')->and($e->retryAfter)->toBe(5)->and($e->isRetryable())->toBeTrue();
    }
})->with([[429, 'concurrency_limit_exceeded'], [429, 'rate_limit_exceeded'], [503, 'overloaded']]);

it('does not retry permanent errors and preserves validation details', function (int $status, string $code) {
    Http::fake(['*' => Http::response(['error' => ['code' => $code, 'message' => 'No', 'errors' => ['size' => ['Invalid']]]], $status)]);
    try {
        $this->client->createGeneration(['prompt' => 'hi'], 'k');
        $this->fail('Expected exception');
    } catch (PixelforgeException $e) {
        expect($e->isRetryable())->toBeFalse()->and($e->retryAfter)->toBeNull()->and($e->errors)->toBe(['size' => ['Invalid']]);
    }
})->with([[429, 'daily_limit_exceeded'], [422, 'validation_failed'], [400, 'content_policy_violation'], [409, 'idempotency_key_reused']]);

it('treats an unstructured server error as retryable', function () {
    Http::fake(['*' => Http::response([], 500)]);
    try {
        $this->client->usage();
        $this->fail('Expected exception');
    } catch (PixelforgeException $e) {
        expect($e->isRetryable())->toBeTrue()->and($e->getMessage())->toBe('Pixelforge returned HTTP 500.');
    }
});
