<?php

namespace App\Console\Commands;

use App\Enums\RfqStatus;
use App\Models\SourcingRfq;
use App\Services\Procurement\ProcurementAuditService;
use Illuminate\Console\Command;

class CloseExpiredRfqs extends Command
{
    protected $signature = 'procurement:close-expired-rfqs';

    protected $description = 'Close bidding window for sourcing RFQs whose submission deadline has elapsed';

    public function handle(ProcurementAuditService $auditService): int
    {
        $expiredRfqs = SourcingRfq::where('status', RfqStatus::Published->value)
            ->where('submission_deadline', '<=', now())
            ->get();

        $closedCount = 0;

        foreach ($expiredRfqs as $rfq) {
            $rfq->status = RfqStatus::BiddingClosed;
            $rfq->save();

            $auditService->record(
                null,
                'SourcingRfq',
                $rfq->id,
                'closed_bidding_sourcing_rfq',
                null,
                [
                    'rfq_number' => $rfq->rfq_number,
                    'submission_deadline' => $rfq->submission_deadline?->toIso8601String(),
                    'quotes_count' => $rfq->quotes()->count(),
                ]
            );

            $closedCount++;
        }

        $this->info("Expired sourcing RFQ check completed. {$closedCount} RFQ(s) transitioned to bidding closed.");

        return self::SUCCESS;
    }
}
