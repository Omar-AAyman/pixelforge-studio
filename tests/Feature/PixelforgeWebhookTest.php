<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.pixelforge.webhook_secret' => 'whsec_test', 'services.pixelforge.signature_tolerance' => 300]);
});

function webhookGeneration(): Generation
{
    return Generation::create([
        'user_id' => User::factory()->create()->id, 'ulid' => (string) Str::ulid(),
        'status' => GenerationStatus::Pending, 'prompt' => 'test', 'size' => '1024x1024',
        'quality' => 'low', 'reference_images' => [], 'est_cost_min_nanos' => 0,
        'est_cost_max_nanos' => 0, 'est_charge_min_nanos' => 0, 'est_charge_max_nanos' => 0,
    ]);
}

function webhookEvent(Generation $generation, string $status = 'succeeded', string $id = 'evt_1'): array
{
    return ['id' => $id, 'type' => 'generation.'.$status, 'data' => ['object' => [
        'id' => 'gen_1', 'status' => $status, 'metadata' => ['local_id' => $generation->ulid],
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'usage' => ['input_text_tokens' => 15, 'input_image_tokens' => 765, 'output_tokens' => 420, 'reasoning_tokens' => 233],
        'image_url' => 'https://example.com/image.png', 'completed_at' => '2026-11-19T12:00:00Z',
    ]]];
}

function sendWebhook(array $event, ?int $timestamp = null, bool $valid = true): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $timestamp ??= now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $valid ? 'whsec_test' : 'wrong');

    return test()->call('POST', '/webhooks/pixelforge', server: [
        'CONTENT_TYPE' => 'application/json', 'HTTP_PIXELFORGE_SIGNATURE' => "t=$timestamp,v1=invalid,v1=$signature",
    ], content: $body);
}

it('rejects bad signatures and stale timestamps', function () {
    $event = ['id' => 'evt_ping', 'type' => 'ping'];
    sendWebhook($event, valid: false)->assertStatus(400);
    sendWebhook($event, now()->timestamp - 301)->assertStatus(400);
    sendWebhook($event)->assertNoContent();
});

it('deduplicates events and ignores stale processing after success', function () {
    $generation = webhookGeneration();
    $event = webhookEvent($generation);
    sendWebhook($event)->assertNoContent();
    sendWebhook($event)->assertNoContent();
    sendWebhook(webhookEvent($generation, 'processing', 'evt_2'))->assertNoContent();
    $generation->refresh();
    expect(WebhookEvent::count())->toBe(2)
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost_nanos)->toBe(27437500)
        ->and($generation->charge_nanos)->toBe(34296875)
        ->and($generation->image_url)->toBe('https://example.com/image.png');
});

it('matches a webhook before the job response by local id', function () {
    $generation = webhookGeneration();
    sendWebhook(webhookEvent($generation))->assertNoContent();
    expect($generation->fresh()->pixelforge_id)->toBe('gen_1')
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('rolls back the event when processing fails so delivery can retry', function () {
    $generation = webhookGeneration();
    $event = webhookEvent($generation);
    $event['data']['object']['rates']['output_tokens'] = '1.2345';
    sendWebhook($event)->assertStatus(500);
    expect(WebhookEvent::count())->toBe(0);
    sendWebhook(webhookEvent($generation))->assertNoContent();
    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('accepts unknown generations and records them as processed', function () {
    $generation = webhookGeneration();
    $event = webhookEvent($generation);
    $event['data']['object']['metadata']['local_id'] = 'unknown';
    sendWebhook($event)->assertNoContent();
    expect(WebhookEvent::first()->processed_at)->not->toBeNull()
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Pending);
});
