<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['*/pricing' => Http::response([
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'tokens' => [
            'characters_per_text_token' => 4, 'text_token_variance' => 0.15, 'per_reference_image' => 765,
            'output' => ['high' => ['1536x1024' => 6450]],
            'reasoning' => ['high' => ['min' => 1000, 'max' => 8000]],
        ],
    ])]);
});

it('requires authentication for the generation pages and submission', function () {
    $this->get('/generations/create')->assertRedirect('/login');
    $this->get('/generations')->assertRedirect('/login');
    $this->post('/generations')->assertRedirect('/login');
});

it('shows pricing and saves a pending generation with server calculated estimates', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/generations/create')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Generations/Create')->where('pricing.rates.output_tokens', '40.00')->where('markupPercent', 25));

    $this->post('/generations', [
        'prompt' => str_repeat('a', 400), 'size' => '1536x1024', 'quality' => 'high',
        'reference_images' => ['https://example.com/1.png', 'https://example.com/2.png'],
        'est_charge_max_nanos' => 1,
    ])->assertRedirect('/generations');

    $generation = Generation::firstOrFail();
    expect($generation->user_id)->toBe($user->id)
        ->and($generation->status->value)->toBe('pending')
        ->and($generation->ulid)->toHaveLength(26)
        ->and($generation->est_cost_max_nanos)->toBe(373875000)
        ->and($generation->est_charge_max_nanos)->toBe(467343750)
        ->and($generation->rates)->toBeNull();
    Http::assertSentCount(1);
});

it('validates input before saving', function (array $changes, string $error) {
    $this->actingAs(User::factory()->create())->post('/generations', array_replace([
        'prompt' => 'hello', 'size' => '1536x1024', 'quality' => 'high', 'reference_images' => [],
    ], $changes))->assertSessionHasErrors($error);
    expect(Generation::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    [['prompt' => str_repeat('a', 4001)], 'prompt'],
    [['prompt' => ''], 'prompt'],
    [['size' => 'bad'], 'size'],
    [['quality' => 'bad'], 'quality'],
    [['reference_images' => array_fill(0, 5, 'https://example.com')], 'reference_images'],
    [['reference_images' => ['ftp://example.com/image.png']], 'reference_images.0'],
]);

it('only shows the signed-in user their history', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($owner)->post('/generations', ['prompt' => 'private', 'size' => '1536x1024', 'quality' => 'high', 'reference_images' => []]);
    $this->actingAs($other)->get('/generations')->assertInertia(fn (Assert $page) => $page->component('Generations/Index')->has('generations', 0));
    $this->actingAs($owner)->get('/generations')->assertInertia(fn (Assert $page) => $page->has('generations', 1)->where('generations.0.status', 'pending'));
});
