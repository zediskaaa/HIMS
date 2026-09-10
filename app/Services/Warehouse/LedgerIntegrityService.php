<?php

namespace App\Services\Warehouse;

use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class LedgerIntegrityService
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Compute and seal the SHA-256 cryptographic hash for a StockMovement.
     */
    public function sealMovement(StockMovement $movement): StockMovement
    {
        return DB::transaction(function () use ($movement): StockMovement {
            $latest = StockMovement::query()
                ->whereKeyNot($movement->id)
                ->whereNotNull('hash')
                ->latest('id')
                ->first();

            $previousHash = $latest ? $latest->hash : self::GENESIS_HASH;
            $payload = implode('|', [
                $previousHash,
                $movement->item_id,
                $movement->item_batch_id ?? '',
                $movement->movement_type->value ?? (string) $movement->movement_type,
                $movement->quantity,
                $movement->from_location_id ?? '',
                $movement->to_location_id ?? '',
                $movement->user_id ?? '',
                $movement->moved_at ? $movement->moved_at->toIso8601String() : now()->toIso8601String(),
            ]);

            $movement->previous_hash = $previousHash;
            $movement->hash = hash('sha256', $payload);
            $movement->saveQuietly();

            return $movement;
        });
    }

    /**
     * Verify the entire cryptographic chain of stock movements.
     *
     * @return array{is_valid: bool, verified_count: int, broken_at_id: ?int, error: ?string}
     */
    public function verifyChain(): array
    {
        $movements = StockMovement::query()->whereNotNull('hash')->orderBy('id')->get();
        $expectedPrevious = self::GENESIS_HASH;
        $verifiedCount = 0;

        foreach ($movements as $movement) {
            if ($movement->previous_hash !== $expectedPrevious) {
                return [
                    'is_valid' => false,
                    'verified_count' => $verifiedCount,
                    'broken_at_id' => $movement->id,
                    'error' => "Hash chain broken at movement #{$movement->id}: previous hash does not match preceding digest.",
                ];
            }

            $payload = implode('|', [
                $movement->previous_hash,
                $movement->item_id,
                $movement->item_batch_id ?? '',
                $movement->movement_type->value ?? (string) $movement->movement_type,
                $movement->quantity,
                $movement->from_location_id ?? '',
                $movement->to_location_id ?? '',
                $movement->user_id ?? '',
                $movement->moved_at ? $movement->moved_at->toIso8601String() : '',
            ]);

            $calculated = hash('sha256', $payload);
            if ($calculated !== $movement->hash) {
                return [
                    'is_valid' => false,
                    'verified_count' => $verifiedCount,
                    'broken_at_id' => $movement->id,
                    'error' => "Payload tampering detected at movement #{$movement->id}: computed hash {$calculated} does not match sealed hash {$movement->hash}.",
                ];
            }

            $expectedPrevious = $movement->hash;
            $verifiedCount++;
        }

        return [
            'is_valid' => true,
            'verified_count' => $verifiedCount,
            'broken_at_id' => null,
            'error' => null,
        ];
    }
}
