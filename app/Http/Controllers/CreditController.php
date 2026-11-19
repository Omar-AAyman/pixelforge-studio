<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Credits/Index', [
            'transactions' => DB::table('credit_transactions')->where('user_id', $request->user()->id)->orderByDesc('id')->get(['id', 'description', 'amount_nanos', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['dollars' => ['required', 'integer', 'in:5,10,20']]);
        DB::transaction(function () use ($request, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            DB::table('credit_transactions')->insert([
                'user_id' => $request->user()->id,
                'type' => 'purchase',
                'amount_nanos' => (int) $data['dollars'] * 1_000_000_000,
                'description' => 'Fake credit purchase',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('credits.index');
    }
}
