<?php

namespace App\Jobs;

use App\Actions\ApplyGenerationUpdate;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public function __construct(public int $generationId) {}

    public function handle(PixelforgeClient $client, ApplyGenerationUpdate $updates): void
    {
        $generation = Generation::findOrFail($this->generationId);
        if ($generation->status !== GenerationStatus::Pending) {
            return;
        }

        $generation->increment('submit_attempts');

        try {
            $remote = $client->createGeneration($generation->toPixelforgeParams(), $generation->ulid);
            $updates->apply($generation, $remote);
        } catch (PixelforgeException|ConnectionException $exception) {
            $retryable = $exception instanceof ConnectionException || $exception->isRetryable();
            if ($retryable && $generation->submit_attempts < $this->tries) {
                $backoff = [2, 5, 10, 30, 60];
                $delay = max($exception instanceof PixelforgeException ? ($exception->retryAfter ?? 0) : 0, $backoff[min($generation->submit_attempts - 1, 4)]);
                $this->release($delay + random_int(0, 2));

                return;
            }

            $this->recordFailure($exception);
        }
    }

    private function recordFailure(PixelforgeException|ConnectionException $exception): void
    {
        DB::transaction(function () use ($exception) {
            $generation = Generation::whereKey($this->generationId)->lockForUpdate()->firstOrFail();
            if ($generation->status !== GenerationStatus::Pending) {
                return;
            }

            $connectionError = $exception instanceof ConnectionException;
            $generation->update([
                'status' => GenerationStatus::Failed,
                'error_code' => $connectionError ? 'submit_unconfirmed' : ($exception->errorCode ?? 'submit_failed'),
                'error_message' => $connectionError ? 'Could not confirm whether Pixelforge accepted this generation.' : $exception->getMessage(),
                'provider_cost_nanos' => 0,
                'charge_nanos' => 0,
            ]);
        });
    }
}
