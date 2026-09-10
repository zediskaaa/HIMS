<?php

namespace App\Services\Warehouse;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\PdeaDangerousDrugsRegister;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NarcoticsVaultService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Authenticate secondary witness credentials for dual-custody access.
     */
    public function authenticateWitness(string $email, string $password, User $custodian): User
    {
        $witness = User::query()->where('email', $email)->first();
        if (! $witness || ! Hash::check($password, $witness->password)) {
            throw new DomainException('Dual-custody verification failed: Invalid witness credentials.');
        }

        if (! $witness->isActive()) {
            throw new DomainException('Dual-custody verification failed: Witness account is inactive.');
        }

        if ($witness->id === $custodian->id) {
            throw new DomainException('Segregation of duties: Witness must be a distinct authorized user.');
        }

        $eligibleWitnessRoles = [
            UserRole::PharmacyStaff,
            UserRole::InventoryManager,
            UserRole::SuperAdministrator,
        ];

        if (! in_array($witness->role, $eligibleWitnessRoles, true)) {
            throw new DomainException('Dual-custody verification failed: Witness must be a licensed pharmacist or authorized supervisor.');
        }

        return $witness;
    }

    /**
     * Record an append-only dangerous drugs ledger entry in the electronic DDRB.
     *
     * @param array{
     *     item_id: int,
     *     item_batch_id?: ?int,
     *     movement_id?: ?int,
     *     storage_location_id: int,
     *     pdea_spf_number?: ?string,
     *     physician_s2_license?: ?string,
     *     prescriber_name?: ?string,
     *     patient_encounter_id?: ?string,
     *     quantity: int,
     *     is_inbound?: bool,
     *     notes?: ?string
     * } $data
     */
    public function recordEntry(array $data, User $custodian, User $witness): PdeaDangerousDrugsRegister
    {
        return DB::transaction(function () use ($data, $custodian, $witness): PdeaDangerousDrugsRegister {
            $item = InventoryItem::lockForUpdate()->findOrFail($data['item_id']);
            $location = StorageLocation::findOrFail($data['storage_location_id']);

            if (! $location->is_narcotics_vault) {
                throw new DomainException("Location {$location->code} is not a designated narcotics vault.");
            }

            $isInbound = $data['is_inbound'] ?? false;
            $quantity = (int) $data['quantity'];
            if ($quantity <= 0) {
                throw new DomainException('Narcotics transaction quantity must be greater than zero.');
            }

            // Calculate current running balance for this item in this vault
            $latestEntry = PdeaDangerousDrugsRegister::query()
                ->where('item_id', $item->id)
                ->where('storage_location_id', $location->id)
                ->latest('id')
                ->first();

            $prevBalance = $latestEntry ? $latestEntry->running_balance : 0;
            $newBalance = $isInbound ? ($prevBalance + $quantity) : ($prevBalance - $quantity);

            if ($newBalance < 0) {
                throw new DomainException("Insufficient vault balance for {$item->name}. Current balance: {$prevBalance}.");
            }

            // If dispensing outbound, SPF Yellow Prescription and S-2 license are mandatory by law
            if (! $isInbound) {
                if (empty($data['pdea_spf_number'])) {
                    throw new DomainException('Official DOH/PDEA Yellow Prescription Form (SPF) serial number is required.');
                }
                if (empty($data['physician_s2_license'])) {
                    throw new DomainException('Prescribing physician PDEA S-2 license number is required.');
                }
            }

            $registerNumber = 'DDRB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));

            $entry = PdeaDangerousDrugsRegister::create([
                'register_number' => $registerNumber,
                'item_id' => $item->id,
                'item_batch_id' => $data['item_batch_id'] ?? null,
                'movement_id' => $data['movement_id'] ?? null,
                'storage_location_id' => $location->id,
                'pdea_spf_number' => $data['pdea_spf_number'] ?? null,
                'physician_s2_license' => $data['physician_s2_license'] ?? null,
                'prescriber_name' => $data['prescriber_name'] ?? null,
                'patient_encounter_id' => $data['patient_encounter_id'] ?? null,
                'quantity' => $quantity,
                'running_balance' => $newBalance,
                'custodian_id' => $custodian->id,
                'witness_pharmacist_id' => $witness->id,
                'witness_authenticated_at' => now(),
                'notes' => $data['notes'] ?? null,
                'recorded_at' => now(),
            ]);

            $this->auditLogger->record(
                AuditAction::RecordedDangerousDrugTransaction,
                actor: $custodian,
                target: $entry,
                description: "Recorded Dangerous Drug transaction {$registerNumber} for {$item->name} ({$quantity} units). Witness: {$witness->name}",
                newValues: [
                    'register_number' => $registerNumber,
                    'running_balance' => $newBalance,
                    'witness_id' => $witness->id,
                    'pdea_spf_number' => $entry->pdea_spf_number,
                ],
            );

            return $entry;
        });
    }

    /**
     * Generate structured data for the mandatory PDEA Semi-Annual Report.
     *
     * @return Collection<int, PdeaDangerousDrugsRegister>
     */
    public function getSemiAnnualReportData(?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate ??= now()->subMonths(6)->startOfDay();
        $endDate ??= now()->endOfDay();

        return PdeaDangerousDrugsRegister::query()
            ->with(['item', 'batch', 'location', 'custodian', 'witnessPharmacist'])
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at')
            ->get();
    }
}
