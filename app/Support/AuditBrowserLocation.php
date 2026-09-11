<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuditBrowserLocation
{
    public const SESSION_KEY = 'audit_browser_location';

    private const MAX_AGE_SECONDS = 900;

    /**
     * @param  array{latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string}  $location
     */
    public static function store(Request $request, array $location, int|string|null $userId = null): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $userId ?? self::userId(),
            'latitude' => round((float) $location['latitude'], 4),
            'longitude' => round((float) $location['longitude'], 4),
            'accuracy' => min(100000, max(0, (int) ceil((float) ($location['accuracy'] ?? 0)))),
            'captured_at' => now()->getTimestamp(),
        ]);
    }

    /**
     * @return array{latitude: float, longitude: float, accuracy: int}|null
     */
    public static function current(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $location = $request->session()->get(self::SESSION_KEY);

        if (! is_array($location)
            || ($location['user_id'] ?? null) !== self::userId()
            || ! is_numeric($location['latitude'] ?? null)
            || ! is_numeric($location['longitude'] ?? null)
            || ! is_numeric($location['accuracy'] ?? null)
            || ! is_int($location['captured_at'] ?? null)
            || $location['captured_at'] < now()->getTimestamp() - self::MAX_AGE_SECONDS) {

            if (is_numeric($request->input('latitude')) && is_numeric($request->input('longitude'))) {
                self::store($request, [
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'accuracy' => $request->input('accuracy', 0),
                ]);

                return [
                    'latitude' => round((float) $request->input('latitude'), 4),
                    'longitude' => round((float) $request->input('longitude'), 4),
                    'accuracy' => min(100000, max(0, (int) ceil((float) $request->input('accuracy', 0)))),
                ];
            }

            return null;
        }

        return [
            'latitude' => (float) $location['latitude'],
            'longitude' => (float) $location['longitude'],
            'accuracy' => (int) $location['accuracy'],
        ];
    }

    private static function userId(): int|string|null
    {
        $guard = AuthenticationContext::authenticatedGuard();

        return $guard === null ? null : Auth::guard($guard)->id();
    }
}
