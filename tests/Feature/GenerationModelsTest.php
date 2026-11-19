<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('persists a generation with its casts and provider parameters', function () {
    $user = User::factory()->create();
    $generation = Generation::create([
        'ulid' => (string) Str::ulid(), 'user_id' => $user->id,
        'status' => GenerationStatus::Pending, 'prompt' => 'A lighthouse',
        'size' => '1024x1024', 'quality' => 'low', 'reference_images' => ['https://example.com/image.png'],
        'rates' => ['reasoning_tokens' => '12.50'],
        'est_cost_min_nanos' => 100, 'est_cost_max_nanos' => 200,
        'est_charge_min_nanos' => 125, 'est_charge_max_nanos' => 250,
        'provider_created_at' => '2026-11-20 00:00:00',
    ])->fresh();

    expect($generation->status)->toBe(GenerationStatus::Pending)
        ->and($generation->rates)->toBe(['reasoning_tokens' => '12.50'])
        ->and($generation->provider_created_at->toIso8601String())->toBe('2026-11-20T00:00:00+00:00')
        ->and($generation->user->id)->toBe($user->id)
        ->and($generation->toPixelforgeParams())->toBe([
            'prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'low',
            'reference_images' => ['https://example.com/image.png'],
            'metadata' => ['local_id' => $generation->ulid, 'user_id' => (string) $user->id],
        ]);
});

it('stores webhook payloads and prevents duplicate event ids', function () {
    $attributes = ['event_id' => 'evt_123', 'type' => 'generation.processing', 'delivery_attempt' => 1, 'payload' => ['id' => 'evt_123']];
    $event = WebhookEvent::create($attributes)->fresh();

    expect($event->payload)->toBe(['id' => 'evt_123']);
    WebhookEvent::create($attributes);
})->throws(QueryException::class);
