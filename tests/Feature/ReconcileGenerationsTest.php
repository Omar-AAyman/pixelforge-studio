<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function staleGeneration(string $status, ?string $remoteId): Generation
{
    $generation = Generation::create([
        'user_id' => User::factory()->create()->id, 'ulid' => (string) Str::ulid(),
        'pixelforge_id' => $remoteId, 'status' => $status, 'prompt' => 'test',
        'size' => '1024x1024', 'quality' => 'low', 'reference_images' => [],
        'est_cost_min_nanos' => 0, 'est_cost_max_nanos' => 0,
        'est_charge_min_nanos' => 0, 'est_charge_max_nanos' => 0,
    ]);
    $generation->timestamps = false;
    $generation->forceFill(['updated_at' => now()->subMinutes(4)])->save();

    return $generation;
}

it('reconciles stale rows using the same billing state machine', function () {
    Http::preventStrayRequests();
    Http::fake(['*/generations/gen_1' => Http::response([
        'id' => 'gen_1', 'status' => 'succeeded',
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'usage' => ['input_text_tokens' => 15, 'input_image_tokens' => 765, 'output_tokens' => 420, 'reasoning_tokens' => 233],
        'image_url' => 'https://example.com/image.png', 'completed_at' => '2026-11-19T12:00:00Z',
    ])]);
    $generation = staleGeneration('queued', 'gen_1');
    staleGeneration('pending', null);
    staleGeneration('failed', 'gen_final');
    $recent = staleGeneration('processing', 'gen_recent');
    $recent->timestamps = true;
    $recent->touch();

    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->fresh()->charge_nanos)->toBe(34296875)
        ->and($generation->fresh()->image_url)->toBe('https://example.com/image.png');
    Http::assertSentCount(1);
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Http::assertSentCount(1);
});

it('limits each reconciliation run to 20 requests', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['id' => 'gen_ignored', 'status' => 'queued'])]);
    for ($i = 0; $i < 21; $i++) {
        staleGeneration('queued', 'gen_'.$i);
    }
    $this->artisan('pixelforge:reconcile')->assertSuccessful();
    Http::assertSentCount(20);
});

it('stops the batch when the provider rate limits requests', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['error' => ['code' => 'rate_limit_exceeded', 'message' => 'Slow down']], 429, ['Retry-After' => '5'])]);
    staleGeneration('queued', 'gen_1');
    staleGeneration('queued', 'gen_2');

    $this->artisan('pixelforge:reconcile')->assertFailed();
    Http::assertSentCount(1);
});
