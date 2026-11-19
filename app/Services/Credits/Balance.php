<?php

namespace App\Services\Credits;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;

class Balance
{
    /** @return array{balance: int, reserved: int, available: int} */
    public function forUser(int $userId): array
    {
        $balance = (int) DB::table('credit_transactions')->where('user_id', $userId)->sum('amount_nanos');
        $reserved = (int) Generation::where('user_id', $userId)
            ->whereIn('status', [GenerationStatus::Pending->value, GenerationStatus::Queued->value, GenerationStatus::Processing->value])
            ->sum('est_charge_max_nanos');

        return ['balance' => $balance, 'reserved' => $reserved, 'available' => $balance - $reserved];
    }
}
