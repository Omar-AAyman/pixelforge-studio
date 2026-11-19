<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'pixelforge_id', 'status', 'prompt', 'size', 'quality',
        'reference_images', 'rates', 'est_cost_min_nanos', 'est_cost_max_nanos',
        'est_charge_min_nanos', 'est_charge_max_nanos', 'input_text_tokens',
        'input_image_tokens', 'output_tokens', 'reasoning_tokens', 'provider_cost_nanos',
        'charge_nanos', 'image_url', 'image_url_expires_at', 'error_code', 'error_message',
        'submit_attempts', 'provider_created_at', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'image_url_expires_at' => 'datetime',
            'provider_created_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, mixed> */
    public function toPixelforgeParams(): array
    {
        return [
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images,
            'metadata' => ['local_id' => $this->ulid, 'user_id' => (string) $this->user_id],
        ];
    }
}
