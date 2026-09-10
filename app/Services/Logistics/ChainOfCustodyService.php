<?php

namespace App\Services\Logistics;

use App\Enums\AuditAction;
use App\Models\ChainOfCustodyLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class ChainOfCustodyService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Record an immutable chain of custody transfer event.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordTransfer(Model $trackable, array $data, ?User $actor = null): ChainOfCustodyLog
    {
        return DB::transaction(function () use ($trackable, $data, $actor): ChainOfCustodyLog {
            $custodyNumber = 'COC-'.now()->format('Ym').'-'.Str::upper(Str::random(6));

            $log = ChainOfCustodyLog::create([
                'custody_number' => $custodyNumber,
                'trackable_type' => $trackable->getMorphClass(),
                'trackable_id' => $trackable->getKey(),
                'event_type' => $data['event_type'],
                'releasing_user_id' => $data['releasing_user_id'] ?? null,
                'releasing_party_name' => $data['releasing_party_name'] ?? null,
                'receiving_user_id' => $data['receiving_user_id'] ?? null,
                'receiving_party_name' => $data['receiving_party_name'] ?? null,
                'transferred_at' => $data['transferred_at'] ?? now(),
                'origin_location' => $data['origin_location'] ?? null,
                'destination_location' => $data['destination_location'] ?? null,
                'package_condition' => $data['package_condition'] ?? 'good_order',
                'verification_method' => $data['verification_method'] ?? 'credential_auth',
                'notes' => $data['notes'] ?? null,
                'ip_address' => Request::ip() ?? '127.0.0.1',
                'user_agent' => Request::userAgent() ?? 'System / Console',
            ]);

            $this->auditLogger->record(
                AuditAction::RecordedCustodyTransfer,
                actor: $actor,
                target: $log,
                description: "Logged Chain of Custody event {$data['event_type']} ({$custodyNumber}) for {$trackable->getMorphClass()} #{$trackable->getKey()}.",
                newValues: [
                    'custody_number' => $custodyNumber,
                    'event_type' => $data['event_type'],
                    'package_condition' => $log->package_condition,
                ],
            );

            return $log;
        });
    }
}
