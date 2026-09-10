<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. DPRI Master Table (DOH Drug Price Reference Index)
        Schema::create('dpri_reference_prices', function (Blueprint $table) {
            $table->id();
            $table->string('pndf_code', 64)->index();
            $table->string('drug_name');
            $table->string('dosage_form_strength')->nullable();
            $table->string('unit_of_measure', 64);
            $table->decimal('ceiling_price', 12, 4);
            $table->unsignedSmallInteger('edition_year')->default(2026)->index();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pndf_code', 'edition_year'], 'dpri_code_year_unique');
        });

        // 2. Master Table: KPI Process Reviews
        Schema::create('kpi_process_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_number', 64)->unique();
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->longText('qualitative_context')->nullable();
            $table->longText('executive_summary')->nullable();
            $table->json('metrics_summary')->nullable();
            $table->timestamps();
        });

        // 3. Supplier Scorecards Table
        Schema::create('supplier_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_process_review_id')->constrained('kpi_process_reviews')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->decimal('delivery_score', 5, 2)->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('fill_rate_score', 5, 2)->default(0);
            $table->decimal('total_score', 5, 2)->default(0);
            $table->unsignedInteger('total_pos_count')->default(0);
            $table->unsignedInteger('completed_pos_count')->default(0);
            $table->unsignedInteger('late_deliveries_count')->default(0);
            $table->decimal('avg_lead_time_days', 8, 2)->default(0);
            $table->decimal('promised_lead_time_days', 8, 2)->default(0);
            $table->unsignedInteger('non_conformance_count')->default(0);
            $table->unsignedInteger('temperature_excursions_count')->default(0);
            $table->boolean('has_valid_lto')->default(true);
            $table->boolean('has_valid_cpr')->default(true);
            $table->string('recommendation', 32)->default('retain');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['kpi_process_review_id', 'supplier_id'], 'sc_review_supp_idx');
        });

        // 4. Procurement Savings Logs Table
        Schema::create('procurement_savings_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_process_review_id')->constrained('kpi_process_reviews')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('po_line_items')->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('pndf_code', 64)->nullable()->index();
            $table->string('item_name');
            $table->string('uom', 64);
            $table->decimal('quantity_procured', 12, 2)->default(0);
            $table->decimal('actual_unit_price', 12, 4)->default(0);
            $table->decimal('dpri_ceiling_price', 12, 4)->default(0);
            $table->decimal('variance_amount', 14, 4)->default(0);
            $table->decimal('savings_percentage', 8, 2)->default(0);
            $table->boolean('is_above_ceiling')->default(false);
            $table->text('justification')->nullable();
            $table->timestamps();

            $table->index(['kpi_process_review_id', 'inventory_item_id'], 'psl_review_item_idx');
        });

        // 5. Inventory Shrinkage Reports Table (COA RPCI)
        Schema::create('inventory_shrinkage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_process_review_id')->constrained('kpi_process_reviews')->cascadeOnDelete();
            $table->foreignId('cycle_count_doc_id')->nullable()->constrained('cycle_count_docs')->nullOnDelete();
            $table->foreignId('storage_location_id')->constrained('storage_locations')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('ledger_book_quantity', 12, 2)->default(0);
            $table->decimal('physical_counted_quantity', 12, 2)->default(0);
            $table->decimal('shrinkage_quantity', 12, 2)->default(0);
            $table->decimal('shrinkage_rate_pct', 8, 2)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('total_loss_value', 14, 4)->default(0);
            $table->string('shrinkage_reason', 64)->nullable();
            $table->boolean('requires_admin_escalation')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['kpi_process_review_id', 'storage_location_id'], 'isr_review_loc_idx');
        });

        // 6. Process Recommendations Table
        Schema::create('process_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_process_review_id')->constrained('kpi_process_reviews')->cascadeOnDelete();
            $table->string('category', 64)->index();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_name');
            $table->text('problem_detected');
            $table->json('evidence_metrics')->nullable();
            $table->text('root_cause_analysis')->nullable();
            $table->text('recommended_action');
            $table->text('expected_operational_benefit')->nullable();
            $table->string('priority', 32)->default('medium')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('implemented_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('implemented_at')->nullable();
            $table->text('implementation_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_recommendations');
        Schema::dropIfExists('inventory_shrinkage_reports');
        Schema::dropIfExists('procurement_savings_logs');
        Schema::dropIfExists('supplier_scorecards');
        Schema::dropIfExists('kpi_process_reviews');
        Schema::dropIfExists('dpri_reference_prices');
    }
};
