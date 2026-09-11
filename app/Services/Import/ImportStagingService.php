<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImportStagingService
{
    private const CACHE_PREFIX = 'hims_import_staging_';
    private const TTL_MINUTES = 60;

    /**
     * Store validated import payload in cache and return a unique token.
     *
     * @param  string  $target
     * @param  string  $mode
     * @param  array<int, array<string, mixed>>  $records
     * @param  int  $userId
     * @return string
     */
    public function stage(string $target, string $mode, array $records, int $userId): string
    {
        $token = (string) Str::uuid();
        $key = self::CACHE_PREFIX . $token;

        Cache::put($key, [
            'target' => $target,
            'mode' => $mode,
            'records' => $records,
            'user_id' => $userId,
            'created_at' => now()->timestamp,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * Retrieve staged import payload.
     *
     * @param  string  $token
     * @param  int  $userId
     * @return array{target: string, mode: string, records: array<int, array<string, mixed>>}|null
     */
    public function retrieve(string $token, int $userId): ?array
    {
        $key = self::CACHE_PREFIX . $token;
        $staged = Cache::get($key);

        if (! $staged || ! is_array($staged)) {
            return null;
        }

        // Verify token belongs to the requesting user
        if (($staged['user_id'] ?? null) !== $userId) {
            return null;
        }

        return [
            'target' => $staged['target'],
            'mode' => $staged['mode'],
            'records' => $staged['records'],
        ];
    }

    /**
     * Remove staged payload after completion.
     */
    public function forget(string $token): void
    {
        Cache::forget(self::CACHE_PREFIX . $token);
    }
}
