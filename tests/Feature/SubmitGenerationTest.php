<?php

use App\Actions\ApplyGenerationUpdate;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function pendingGeneration(): Generation
{
    return Generation::create([
        'user_id' => User::factory()->create()->id, 'ulid' => (string) Str::ulid(),
        'status' => GenerationStatus::Pending, 'prompt' => 'test', 'size' => '1024x1024',
        'quality' => 'low', 'reference_images' => [], 'est_cost_min_nanos' => 0,
        'est_cost_max_nanos' => 0, 'est_charge_min_nanos' => 0, 'est_charge_max_nanos' => 0,
    ]);
}

function remoteGeneration(string $status = 'queued'): array
{
    return [
        'id' => 'gen_test', 'status' => $status,
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'usage' => ['input_text_tokens' => 15, 'input_image_tokens' => 765, 'output_tokens' => 420, 'reasoning_tokens' => 233],
        'image_url' => 'https://example.com/image.png', 'completed_at' => '2026-11-19T12:00:00Z',
    ];
}

it('submits with a stable key and metadata and stores the accepted generation', function () {
    Http::fake(['*/generations' => Http::response(remoteGeneration(), 202)]);
    $generation = pendingGeneration();
    (new SubmitGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationUpdate::class));
    expect($generation->fresh()->status)->toBe(GenerationStatus::Queued)
        ->and($generation->fresh()->pixelforge_id)->toBe('gen_test');
    Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->ulid)
        && $request['metadata']['local_id'] === $generation->ulid
        && $request['metadata']['user_id'] === (string) $generation->user_id);
});

it('retries transient responses using the same key and respects retry after', function (int $status, string $code) {
    Http::fakeSequence()->push(['error' => ['code' => $code, 'message' => 'Try later']], $status, ['Retry-After' => '15'])
        ->push(remoteGeneration(), 202);
    $generation = pendingGeneration();
    $job = (new SubmitGeneration($generation->id))->withFakeQueueInteractions();
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationUpdate::class));
    $job->assertReleased();
    expect($job->job->releaseDelay)->toBeGreaterThanOrEqual(15);
    expect($generation->fresh()->status)->toBe(GenerationStatus::Pending);
    (new SubmitGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationUpdate::class));
    expect($generation->fresh()->status)->toBe(GenerationStatus::Queued)->and($generation->fresh()->submit_attempts)->toBe(2);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->ulid));
})->with([[429, 'concurrency_limit_exceeded'], [503, 'overloaded']]);

it('records final rejections and exhausted retries as free failures', function (int $status, string $code, int $attempts) {
    Http::fake(['*' => Http::response(['error' => ['code' => $code, 'message' => 'Provider says no']], $status)]);
    $generation = pendingGeneration();
    $generation->update(['submit_attempts' => $attempts]);
    (new SubmitGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationUpdate::class));
    expect($generation->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->fresh()->error_code)->toBe($code)
        ->and($generation->fresh()->error_message)->toBe('Provider says no')
        ->and($generation->fresh()->charge_nanos)->toBe(0);
})->with([[422, 'validation_failed', 0], [400, 'content_policy_violation', 0], [503, 'overloaded', 7], [429, 'daily_limit_exceeded', 0]]);

it('bills a succeeded replay once using its own rates and ignores stale updates', function () {
    Http::fake(['*/generations' => Http::response(remoteGeneration('succeeded'), 202, ['Idempotent-Replayed' => 'true'])]);
    $generation = pendingGeneration();
    (new SubmitGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationUpdate::class));
    app(ApplyGenerationUpdate::class)->apply($generation, remoteGeneration('processing'));
    app(ApplyGenerationUpdate::class)->apply($generation, remoteGeneration('succeeded'));
    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->fresh()->provider_cost_nanos)->toBe(27437500)
        ->and($generation->fresh()->charge_nanos)->toBe(34296875)
        ->and($generation->fresh()->image_url)->toBe('https://example.com/image.png');
});

it('allows a provider update to resolve an unconfirmed local failure', function () {
    $generation = pendingGeneration();
    $generation->update(['status' => GenerationStatus::Failed, 'error_code' => 'submit_unconfirmed', 'provider_cost_nanos' => 0, 'charge_nanos' => 0]);
    app(ApplyGenerationUpdate::class)->apply($generation, remoteGeneration('succeeded'));
    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->fresh()->error_code)->toBeNull()
        ->and($generation->fresh()->provider_cost_nanos)->toBe(27437500);
});

it('marks an exhausted connection error as unconfirmed', function () {
    Http::fake(fn () => throw new ConnectionException('Connection failed'));
    $generation = pendingGeneration();
    $generation->update(['submit_attempts' => 7]);
    (new SubmitGeneration($generation->id))->handle(app(PixelforgeClient::class), app(ApplyGenerationUpdate::class));
    expect($generation->fresh()->error_code)->toBe('submit_unconfirmed')
        ->and($generation->fresh()->pixelforge_id)->toBeNull()
        ->and($generation->fresh()->charge_nanos)->toBe(0);
});
