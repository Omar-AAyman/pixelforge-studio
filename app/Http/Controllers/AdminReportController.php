<?php

namespace App\Http\Controllers;

use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use App\Services\Pricing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function __invoke(Request $request, PixelforgeClient $client): Response
    {
        abort_unless($request->user()->is_admin, 403);

        $dates = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
        ]);
        $end = CarbonImmutable::parse($dates['end_date'] ?? now('UTC')->toDateString(), 'UTC')->startOfDay();
        $start = CarbonImmutable::parse($dates['start_date'] ?? $end->subDays(6)->toDateString(), 'UTC')->startOfDay();
        if ($start->diffInDays($end) > 91) {
            throw ValidationException::withMessages(['end_date' => 'The date range may cover at most 92 days.']);
        }

        $generations = DB::table('generations')
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->where('generations.status', 'succeeded')
            ->where('generations.completed_at', '>=', $start->toDateTimeString())
            ->where('generations.completed_at', '<', $end->addDay()->toDateTimeString());

        $daily = (clone $generations)->selectRaw('DATE(generations.completed_at) as date, SUM(provider_cost_nanos) as cost, SUM(charge_nanos) as charge')
            ->groupByRaw('DATE(generations.completed_at)')->get()->keyBy('date');
        $users = (clone $generations)->selectRaw('users.id, users.name, users.email, SUM(provider_cost_nanos) as cost, SUM(charge_nanos) as charge')
            ->groupBy('users.id', 'users.name', 'users.email')->orderBy('users.name')->get()
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'email' => $row->email, ...$this->amounts((int) $row->cost, (int) $row->charge)]);

        $usage = null;
        try {
            $data = $client->usage($start->toDateString(), $end->toDateString())['data'] ?? null;
            if (! is_array($data)) {
                throw new \UnexpectedValueException('Invalid Pixelforge usage response.');
            }
            $usage = collect($data)->keyBy('date');
        } catch (PixelforgeException|ConnectionException|\UnexpectedValueException $e) {
            report($e);
        }

        $days = [];
        for ($date = $end; $date->greaterThanOrEqualTo($start); $date = $date->subDay()) {
            $key = $date->toDateString();
            $row = $daily->get($key);
            $cost = Money::format((int) ($row->cost ?? 0));
            $provider = $usage?->get($key)['cost_usd'] ?? null;
            $days[] = ['date' => $key, ...$this->amounts((int) ($row->cost ?? 0), (int) ($row->charge ?? 0)), 'provider' => $provider, 'matches' => $provider === null ? null : $provider === $cost];
        }

        return Inertia::render('Admin/Report', [
            'startDate' => $start->toDateString(), 'endDate' => $end->toDateString(),
            'days' => $days, 'users' => $users, 'usageAvailable' => $usage !== null,
        ]);
    }

    /** @return array<string, string> */
    private function amounts(int $cost, int $charge): array
    {
        return ['cost' => Money::format($cost), 'charge' => Money::format($charge), 'profit' => Money::format($charge - $cost)];
    }
}
