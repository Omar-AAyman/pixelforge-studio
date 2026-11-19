<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->bigInteger('amount_nanos');
            $table->foreignId('generation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->timestamps();
            $table->unique(['generation_id', 'type']);
        });

        DB::table('generations')->where('status', 'succeeded')->orderBy('id')->chunkById(500, function ($generations) {
            foreach ($generations as $generation) {
                DB::table('credit_transactions')->insert([
                    'user_id' => $generation->user_id,
                    'generation_id' => $generation->id,
                    'type' => 'generation_charge',
                    'amount_nanos' => -$generation->charge_nanos,
                    'description' => 'Image generation',
                    'created_at' => $generation->completed_at ?? $generation->updated_at,
                    'updated_at' => $generation->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
