<?php

use App\Services\Pricing\Money;
use App\Services\Pricing\PricingCalculator;

function pricingTable(): array
{
    return [
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => ['high' => ['1536x1024' => 6450], 'low' => ['1536x1024' => 420]],
            'reasoning' => ['high' => ['min' => 1000, 'max' => 8000], 'low' => ['min' => 0, 'max' => 500]],
        ],
    ];
}

it('calculates the documented maximum estimate and charge', function () {
    $estimate = (new PricingCalculator)->estimate(str_repeat('a', 400), '1536x1024', 'high', 2, pricingTable());

    expect($estimate['cost_max_nanos'])->toBe(373_875_000)
        ->and($estimate['charge_max_nanos'])->toBe(467_343_750);
});

it('prices actual usage using the supplied rate snapshot', function () {
    $usage = ['input_text_tokens' => 15, 'input_image_tokens' => 765, 'output_tokens' => 420, 'reasoning_tokens' => 233];
    $cost = (new PricingCalculator)->cost($usage, pricingTable()['rates']);

    expect($cost)->toBe(27_437_500)
        ->and(Money::charge($cost))->toBe(34_296_875)
        ->and(Money::format($cost))->toBe('0.027437500');
});

it('parses rates exactly and rejects unsupported precision', function () {
    expect(Money::rateToNanos('12.5'))->toBe(12_500)
        ->and(Money::rateToNanos('0.001'))->toBe(1);
    Money::rateToNanos('1.0000');
})->throws(InvalidArgumentException::class);

it('rounds the markup up to a whole nanodollar', function () {
    expect(Money::charge(1))->toBe(2)
        ->and(Money::charge(4))->toBe(5)
        ->and(Money::format(1_000_000_001))->toBe('1.000000001');
});

it('uses unicode characters and rounds text token bounds outward', function () {
    $calculator = new PricingCalculator;
    $estimate = $calculator->estimate('😀مرحبا', '1536x1024', 'low', 0, pricingTable());
    expect($estimate['text_tokens_estimate'])->toBe(2)
        ->and($estimate['text_tokens_min'])->toBe(1)
        ->and($estimate['text_tokens_max'])->toBe(3);

    $empty = $calculator->estimate('', '1536x1024', 'low', 0, pricingTable());
    expect($empty['text_tokens_min'])->toBe(0)->and($empty['text_tokens_max'])->toBe(0);
});
