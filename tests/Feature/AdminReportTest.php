<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::preventStrayRequests();
});

function reportGeneration(User $user, string $status, string $completed, int $cost, int $charge): void
{
    DB::table('generations')->insert([
        'ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'status' => $status,
        'prompt' => 'test', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => '[]',
        'est_cost_min_nanos' => 0, 'est_cost_max_nanos' => 0, 'est_charge_min_nanos' => 0, 'est_charge_max_nanos' => 0,
        'provider_cost_nanos' => $cost, 'charge_nanos' => $charge,
        'provider_created_at' => '2026-11-18 23:59:59', 'completed_at' => $completed,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('restricts the report to admins', function () {
    $this->get('/admin/report')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/report')->assertForbidden();
    Http::assertNothingSent();
});

it('reconciles exact daily costs and groups users by UTC completion date', function () {
    Http::fake(['*/usage*' => Http::response(['data' => [
        ['date' => '2026-11-19', 'cost_usd' => '0.027437500'],
        ['date' => '2026-11-20', 'cost_usd' => '0.000000001'],
    ]])]);
    $admin = User::factory()->create(['is_admin' => true]);
    $other = User::factory()->create();
    reportGeneration($admin, 'succeeded', '2026-11-19 00:00:00', 27437500, 34296875);
    reportGeneration($other, 'succeeded', '2026-11-20 23:59:59', 2, 3);
    reportGeneration($other, 'failed', '2026-11-19 12:00:00', 100, 125);
    reportGeneration($other, 'succeeded', '2026-11-21 00:00:00', 100, 125);
    $this->actingAs($admin)->get('/admin/report?start_date=2026-11-19&end_date=2026-11-20')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Report')
        ->where('days.0.date', '2026-11-20')->where('days.0.cost', '0.000000002')
        ->where('days.0.matches', false)->where('days.1.matches', true)
        ->where('days.1.charge', '0.034296875')->where('days.1.profit', '0.006859375')
        ->has('users', 2));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'start_date=2026-11-19') && str_contains($request->url(), 'end_date=2026-11-20'));
});

it('shows unavailable rather than a false match if usage fails or omits a day', function () {
    Http::fake(['*/usage*' => Http::response(['error' => ['message' => 'Unavailable']], 503)]);
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get('/admin/report?start_date=2026-11-19&end_date=2026-11-19')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('usageAvailable', false)->where('days.0.matches', null));
});

it('rejects date ranges longer than the provider limit', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get('/admin/report?start_date=2026-01-01&end_date=2026-04-03')->assertSessionHasErrors('end_date');
    Http::assertNothingSent();
});
