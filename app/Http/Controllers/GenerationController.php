<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Http\Requests\StoreGenerationRequest;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pricing\PricingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function create(PixelforgeClient $client): Response
    {
        return Inertia::render('Generations/Create', [
            'pricing' => $client->pricing(),
            'markupPercent' => config('services.pixelforge.markup_percent'),
        ]);
    }

    public function store(StoreGenerationRequest $request, PixelforgeClient $client, PricingCalculator $calculator): RedirectResponse
    {
        $data = $request->validated();
        $estimate = $calculator->estimate($data['prompt'], $data['size'], $data['quality'], count($data['reference_images']), $client->pricing(), (int) config('services.pixelforge.markup_percent'));

        $generation = Generation::create([
            ...$data,
            'ulid' => (string) Str::ulid(),
            'user_id' => $request->user()->id,
            'status' => GenerationStatus::Pending,
            'est_cost_min_nanos' => $estimate['cost_min_nanos'],
            'est_cost_max_nanos' => $estimate['cost_max_nanos'],
            'est_charge_min_nanos' => $estimate['charge_min_nanos'],
            'est_charge_max_nanos' => $estimate['charge_max_nanos'],
        ]);

        SubmitGeneration::dispatch($generation->id)->afterCommit();

        return to_route('generations.index');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Generations/Index', [
            'generations' => Generation::where('user_id', $request->user()->id)->latest()->get(['ulid', 'prompt', 'status', 'est_charge_min_nanos', 'est_charge_max_nanos', 'charge_nanos', 'image_url', 'image_url_expires_at', 'error_message']),
        ]);
    }
}
