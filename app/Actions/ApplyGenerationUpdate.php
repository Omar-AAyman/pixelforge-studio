<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pricing\Money;
use App\Services\Pricing\PricingCalculator;
use Illuminate\Support\Facades\DB;

class ApplyGenerationUpdate
{
    /** @param array<string, mixed> $remote */
    public function apply(Generation $generation, array $remote): void
    {
        DB::transaction(function () use ($generation, $remote) {
            User::whereKey($generation->user_id)->lockForUpdate()->firstOrFail();
            $generation = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();
            $status = GenerationStatus::from($remote['status']);
            $unconfirmed = $generation->status === GenerationStatus::Failed && $generation->pixelforge_id === null;

            if (! $unconfirmed && $status->rank() <= $generation->status->rank()) {
                return;
            }

            $generation->fill([
                'pixelforge_id' => $remote['id'],
                'status' => $status,
                'rates' => $remote['rates'],
                'provider_created_at' => $remote['created_at'] ?? null,
                'started_at' => $remote['started_at'] ?? null,
                'completed_at' => $remote['completed_at'] ?? null,
                'image_url' => $remote['image_url'] ?? null,
                'image_url_expires_at' => $remote['image_url_expires_at'] ?? null,
                'error_code' => $remote['error']['code'] ?? null,
                'error_message' => $remote['error']['message'] ?? null,
                'provider_cost_nanos' => null,
                'charge_nanos' => null,
            ]);

            if ($status === GenerationStatus::Succeeded) {
                $usage = $remote['usage'];
                $cost = app(PricingCalculator::class)->cost($usage, $remote['rates']);
                $generation->fill([
                    'input_text_tokens' => $usage['input_text_tokens'],
                    'input_image_tokens' => $usage['input_image_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'reasoning_tokens' => $usage['reasoning_tokens'],
                    'provider_cost_nanos' => $cost,
                    'charge_nanos' => Money::charge($cost, (int) config('services.pixelforge.markup_percent')),
                ]);
            } elseif ($status === GenerationStatus::Failed) {
                $generation->fill(['provider_cost_nanos' => 0, 'charge_nanos' => 0]);
            }

            $generation->save();

            if ($status === GenerationStatus::Succeeded) {
                DB::table('credit_transactions')->insert([
                    'user_id' => $generation->user_id,
                    'generation_id' => $generation->id,
                    'type' => 'generation_charge',
                    'amount_nanos' => -$generation->charge_nanos,
                    'description' => 'Image generation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
