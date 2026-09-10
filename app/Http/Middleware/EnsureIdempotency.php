<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return $next($request);
        }

        $cacheKey = 'idempotency:'.hash('sha256', implode(':', [
            (string) $request->user()?->getAuthIdentifier(),
            $request->method(),
            $request->path(),
            $idempotencyKey,
        ]));
        $requestFingerprint = hash('sha256', $request->getContent());

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            if (($cached['fingerprint'] ?? null) !== $requestFingerprint) {
                return response()->json([
                    'message' => 'This Idempotency-Key was already used with a different request payload.',
                ], 409);
            }

            return response()->json($cached['data'], $cached['status'], [
                'X-Idempotent-Replay' => 'true',
            ]);
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'data' => json_decode($response->getContent(), true) ?? $response->getContent(),
                'fingerprint' => $requestFingerprint,
            ], now()->addMinutes(30));
        }

        return $response;
    }
}
