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

        $cacheKey = 'idempotency:'.md5($idempotencyKey.':'.$request->path());

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response()->json($cached['data'], $cached['status'], [
                'X-Idempotent-Replay' => 'true',
            ]);
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'data' => json_decode($response->getContent(), true) ?? $response->getContent(),
            ], now()->addMinutes(30));
        }

        return $response;
    }
}
