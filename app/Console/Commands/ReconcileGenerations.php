<?php

namespace App\Console\Commands;

use App\Actions\ApplyGenerationUpdate;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Console\Command;
use Throwable;

class ReconcileGenerations extends Command
{
    protected $signature = 'pixelforge:reconcile';

    protected $description = 'Refresh stale generations from Pixelforge';

    public function handle(PixelforgeClient $client, ApplyGenerationUpdate $update): int
    {
        $generations = Generation::whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', now()->subMinutes(3))
            ->oldest('updated_at')->limit(20)->get();

        $failed = false;
        foreach ($generations as $generation) {
            try {
                $update->apply($generation, $client->getGeneration($generation->pixelforge_id));
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Could not reconcile generation {$generation->ulid}.");
                $failed = true;

                if ($exception instanceof PixelforgeException && ($exception->status === 429 || $exception->status === 503)) {
                    break;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
