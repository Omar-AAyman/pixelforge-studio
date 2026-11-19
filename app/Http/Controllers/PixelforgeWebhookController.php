<?php

namespace App\Http\Controllers;

use App\Actions\ApplyGenerationUpdate;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, ApplyGenerationUpdate $apply): Response
    {
        $event = $request->json()->all();
        Validator::make($event, [
            'id' => ['required', 'string'],
            'type' => ['required', 'string'],
        ])->validate();

        if ($event['type'] === 'ping') {
            return response()->noContent();
        }

        Validator::make($event, [
            'data.object' => ['required', 'array'],
            'data.object.id' => ['required', 'string'],
            'data.object.status' => ['required', 'in:queued,processing,succeeded,failed'],
            'data.object.rates' => ['required', 'array'],
        ])->validate();

        DB::transaction(function () use ($request, $event, $apply) {
            $remote = $event['data']['object'];
            $inserted = WebhookEvent::query()->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'pixelforge_generation_id' => $remote['id'],
                'delivery_attempt' => max(1, (int) $request->header('Pixelforge-Delivery-Attempt', '1')),
                'payload' => json_encode($event, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (! $inserted) {
                return;
            }

            $localId = $remote['metadata']['local_id'] ?? null;
            $generation = is_string($localId) ? Generation::where('ulid', $localId)->first() : null;
            $generation ??= Generation::where('pixelforge_id', $remote['id'])->first();

            if ($generation) {
                $apply->apply($generation, $remote);
            } else {
                Log::warning('Pixelforge webhook for unknown generation', ['event_id' => $event['id'], 'generation_id' => $remote['id']]);
            }

            WebhookEvent::where('event_id', $event['id'])->update(['processed_at' => now()]);
        });

        return response()->noContent();
    }
}
