<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

class PricingCalculator
{
    private const TOKEN_TYPES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    /** Calculate from the rates supplied with the generation, not current pricing. */
    public function cost(array $usage, array $rates): int
    {
        $cost = 0;

        foreach (self::TOKEN_TYPES as $type) {
            if (! isset($usage[$type], $rates[$type]) || ! is_int($usage[$type]) || $usage[$type] < 0 || ! is_string($rates[$type])) {
                throw new InvalidArgumentException("Invalid usage or rate for {$type}.");
            }

            $cost += $usage[$type] * Money::rateToNanos($rates[$type]);
        }

        return $cost;
    }

    /** Return provider and user charge bounds in nanodollars. */
    public function estimate(string $prompt, string $size, string $quality, int $referenceCount, array $pricing, int $markupPercent = 25): array
    {
        $tokens = $pricing['tokens'];
        $charactersPerToken = $tokens['characters_per_text_token'];
        $characters = mb_strlen($prompt);
        $textEstimate = intdiv($characters + $charactersPerToken - 1, $charactersPerToken);

        // JSON decodes the variance as a float; convert its decimal representation to integer parts.
        $variance = (string) $tokens['text_token_variance'];
        if (! preg_match('/^0(?:\.(\d{1,6}))?$/', $variance, $matches)) {
            throw new InvalidArgumentException('Invalid text token variance.');
        }
        $digits = $matches[1] ?? '';
        $scale = 10 ** strlen($digits);
        $variation = (int) $digits;
        $textMin = intdiv($textEstimate * ($scale - $variation), $scale);
        $textMax = intdiv($textEstimate * ($scale + $variation) + $scale - 1, $scale);

        $common = [
            'input_image_tokens' => $referenceCount * $tokens['per_reference_image'],
            'output_tokens' => $tokens['output'][$quality][$size],
        ];
        $min = $this->cost($common + [
            'input_text_tokens' => $textMin,
            'reasoning_tokens' => $tokens['reasoning'][$quality]['min'],
        ], $pricing['rates']);
        $max = $this->cost($common + [
            'input_text_tokens' => $textMax,
            'reasoning_tokens' => $tokens['reasoning'][$quality]['max'],
        ], $pricing['rates']);

        return [
            'text_tokens_estimate' => $textEstimate,
            'text_tokens_min' => $textMin,
            'text_tokens_max' => $textMax,
            'cost_min_nanos' => $min,
            'cost_max_nanos' => $max,
            'charge_min_nanos' => Money::charge($min, $markupPercent),
            'charge_max_nanos' => Money::charge($max, $markupPercent),
        ];
    }
}
