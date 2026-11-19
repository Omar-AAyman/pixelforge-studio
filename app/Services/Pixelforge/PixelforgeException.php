<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\Response;
use RuntimeException;

class PixelforgeException extends RuntimeException
{
    private const RETRYABLE_CODES = ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded'];

    /** @param  array<string, mixed>  $errors */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorType = null,
        public readonly ?int $retryAfter = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error') ?? [];
        $retryAfter = $response->header('Retry-After');

        return new self(
            message: $error['message'] ?? "Pixelforge returned HTTP {$response->status()}.",
            status: $response->status(),
            errorCode: $error['code'] ?? null,
            errorType: $error['type'] ?? null,
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
            errors: $error['errors'] ?? [],
        );
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE_CODES, true)
            || $this->status >= 500;
    }
}
