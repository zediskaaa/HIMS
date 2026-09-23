<?php

namespace Database\Seeders;

use App\Models\KpiProcessReview;
use App\Models\SupplierScorecard;
use App\Services\Analytics\SupplierScoringService;
use Illuminate\Database\Seeder;

class SupplierScorecardDemoSeeder extends Seeder
{
    public function __construct(private readonly SupplierScoringService $supplierScorer) {}

    public function run(): void
    {
        $this->call(SupplierReviewEvidenceDemoSeeder::class);

        KpiProcessReview::query()
            ->whereIn('status', ['approved', 'submitted'])
            ->orderBy('period_start')
            ->each(function (KpiProcessReview $review): void {
                $this->syncScorecards($review);
                $this->syncReviewMetrics($review);
            });
    }

    private function syncScorecards(KpiProcessReview $review): void
    {
        $scorecards = $this->supplierScorer->evaluate($review->period_start, $review->period_end);

        foreach ($scorecards as $scorecard) {
            $supplierId = $scorecard['supplier_id'];
            unset($scorecard['supplier_id'], $scorecard['supplier_name']);

            SupplierScorecard::updateOrCreate(
                [
                    'kpi_process_review_id' => $review->id,
                    'supplier_id' => $supplierId,
                ],
                $scorecard
            );
        }
    }

    private function syncReviewMetrics(KpiProcessReview $review): void
    {
        $metrics = $review->metrics_summary ?? [];
        $metrics['suppliers_evaluated'] = $review->supplierScorecards()->count();
        $metrics['avg_supplier_score'] = round((float) $review->supplierScorecards()->avg('total_score'), 1);

        $review->update(['metrics_summary' => $metrics]);
    }
}
