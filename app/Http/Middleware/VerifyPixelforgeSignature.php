<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPixelforgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', (string) $request->header('Pixelforge-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        $secret = config('services.pixelforge.webhook_secret');
        if (! is_string($secret) || $secret === '' || ! is_string($timestamp) || ! ctype_digit($timestamp)
            || abs(now()->getTimestamp() - (int) $timestamp) > (int) config('services.pixelforge.signature_tolerance', 300)) {
            abort(400, 'Invalid Pixelforge signature.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return $next($request);
            }
        }

        abort(400, 'Invalid Pixelforge signature.');
    }
}
