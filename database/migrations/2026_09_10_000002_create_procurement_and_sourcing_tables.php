<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Procurement Categories
        Schema::create('procurement_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('procurement_categories')->nullOnDelete();
            $table->foreignId('category_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'code']);
        });

        // 2. Cost Centers & Budgets
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('department', 100);
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['department', 'is_active']);
        });

        Schema::create('cost_center_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('allocated_budget', 14, 2)->default(0);
            $table->decimal('soft_encumbered', 14, 2)->default(0);
            $table->decimal('hard_encumbered', 14, 2)->default(0);
            $table->decimal('spent_amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('PHP');
            $table->timestamps();
            $table->unique(['cost_center_id', 'fiscal_year']);
        });

        // 3. Purchase Requests & PR Lines
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pr_number', 60)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->restrictOnDelete();
            $table->foreignId('procurement_category_id')->nullable()->constrained('procurement_categories')->nullOnDelete();
            $table->decimal('total_estimated_amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('PHP');
            $table->string('priority', 30)->default('medium');
            $table->string('status', 40)->default('draft')->index();
            $table->boolean('is_emergency')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['cost_center_id', 'status']);
        });

        Schema::create('pr_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('gl_account_code', 50)->nullable();
            $table->string('item_description')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('uom', 40)->default('unit');
            $table->decimal('estimated_unit_price', 12, 2)->default(0);
            $table->decimal('estimated_total_price', 12, 2)->default(0);
            $table->date('need_by_date')->nullable();
            $table->boolean('is_contracted_catalog')->default(false);
            $table->foreignId('contract_id')->nullable()->constrained('supplier_contracts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['purchase_request_id', 'line_number']);
        });

        // 4. Sourcing RFQs & RFQ Lines
        Schema::create('sourcing_rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('rfq_number', 60)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('procurement_method', 50)->default('request_for_quotation');
            $table->string('bidding_type', 30)->default('sealed');
            $table->timestamp('submission_deadline');
            $table->string('status', 40)->default('draft')->index();
            $table->text('terms_conditions')->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->decimal('weight_price', 4, 2)->default(0.40);
            $table->decimal('weight_technical', 4, 2)->default(0.30);
            $table->decimal('weight_quality', 4, 2)->default(0.15);
            $table->decimal('weight_lead_time', 4, 2)->default(0.15);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unsealed_at')->nullable();
            $table->foreignId('unsealed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rfq_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sourcing_rfq_id')->constrained('sourcing_rfqs')->cascadeOnDelete();
            $table->foreignId('pr_line_id')->nullable()->constrained('pr_line_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->integer('target_quantity')->default(1);
            $table->string('uom', 40)->default('unit');
            $table->string('item_description')->nullable();
            $table->text('technical_specifications')->nullable();
            $table->decimal('max_budget_unit_price', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['sourcing_rfq_id', 'line_number']);
        });

        // 5. Supplier RFQ Invitations
        Schema::create('rfq_supplier_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sourcing_rfq_id')->constrained('sourcing_rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('portal_token', 100)->unique();
            $table->string('status', 30)->default('invited');
            $table->timestamp('invited_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['sourcing_rfq_id', 'supplier_id']);
        });

        // 6. Extend Supplier Quotes for RFQ and Multi-Line
        Schema::table('supplier_quotes', function (Blueprint $table) {
            // Nullable legacy procurement_request_id foreign key preservation
            $table->unsignedBigInteger('procurement_request_id')->nullable()->change();
            $table->foreignId('sourcing_rfq_id')->nullable()->after('id')->constrained('sourcing_rfqs')->nullOnDelete();
            $table->string('quote_number', 60)->nullable()->after('sourcing_rfq_id');
            $table->decimal('total_bid_amount', 14, 2)->default(0)->after('quoted_price');
            $table->char('currency', 3)->default('PHP')->after('total_bid_amount');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000)->after('currency');
            $table->string('incoterms', 30)->nullable()->after('exchange_rate');
            $table->string('payment_terms', 100)->nullable()->after('incoterms');
            $table->date('validity_end_date')->nullable()->after('payment_terms');
            $table->boolean('is_sealed')->default(true)->after('validity_end_date');
            $table->timestamp('unsealed_at')->nullable()->after('is_sealed');
            $table->boolean('is_awarded')->default(false)->after('unsealed_at');
        });

        Schema::create('quote_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quote_id')->constrained('supplier_quotes')->cascadeOnDelete();
            $table->foreignId('rfq_line_item_id')->nullable()->constrained('rfq_line_items')->cascadeOnDelete();
            $table->decimal('offered_unit_price', 12, 2)->default(0);
            $table->integer('offered_quantity')->default(1);
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('tariffs_cost', 12, 2)->default(0);
            $table->decimal('handling_cost', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('landed_cost', 14, 2)->default(0);
            $table->boolean('technical_compliance')->default(true);
            $table->decimal('technical_score', 5, 2)->default(100.00);
            $table->boolean('is_awarded')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. Sourcing Evaluations
        Schema::create('sourcing_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sourcing_rfq_id')->constrained('sourcing_rfqs')->cascadeOnDelete();
            $table->foreignId('supplier_quote_id')->constrained('supplier_quotes')->cascadeOnDelete();
            $table->foreignId('evaluator_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('commercial_score', 5, 2)->default(0);
            $table->decimal('technical_score', 5, 2)->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('lead_time_score', 5, 2)->default(0);
            $table->decimal('composite_score', 5, 2)->default(0);
            $table->decimal('normalized_landed_cost', 14, 2)->default(0);
            $table->boolean('conflict_of_interest_declared')->default(false);
            $table->text('justification_notes')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->index(['sourcing_rfq_id', 'composite_score']);
        });

        // 8. Approval Chains & Steps
        Schema::create('approval_chains', function (Blueprint $table) {
            $table->id();
            $table->string('chain_type', 50)->index(); // purchase_request, sourcing_award, purchase_order, change_order
            $table->unsignedBigInteger('target_id')->index();
            $table->decimal('total_commitment_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_chain_id')->constrained('approval_chains')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_number');
            $table->string('required_role', 50);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->decimal('threshold_min', 14, 2)->default(0);
            $table->decimal('threshold_max', 14, 2)->nullable();
            $table->text('decision_notes')->nullable();
            $table->string('digital_signature_token', 100)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['approval_chain_id', 'step_number']);
        });

        // 9. Extend Purchase Orders for Enterprise S2P
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->foreignId('purchase_request_id')->nullable()->after('id')->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('sourcing_rfq_id')->nullable()->after('purchase_request_id')->constrained('sourcing_rfqs')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->after('sourcing_rfq_id')->constrained('cost_centers')->nullOnDelete();
            $table->char('currency', 3)->default('PHP')->after('total_amount');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000)->after('currency');
            $table->decimal('total_encumbered_amount', 14, 2)->default(0)->after('exchange_rate');
            $table->string('payment_terms', 100)->nullable()->after('total_encumbered_amount');
            $table->string('incoterms', 30)->nullable()->after('payment_terms');
            $table->string('version', 30)->default('PO-REV1')->after('incoterms');
            $table->unsignedSmallInteger('revision_number')->default(1)->after('version');
            $table->timestamp('dispatched_at')->nullable()->after('status');
            $table->text('cxml_payload')->nullable()->after('dispatched_at');
        });

        Schema::create('po_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('pr_line_id')->nullable()->constrained('pr_line_items')->nullOnDelete();
            $table->foreignId('quote_line_id')->nullable()->constrained('quote_line_items')->nullOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->integer('ordered_quantity')->default(1);
            $table->integer('received_quantity')->default(0);
            $table->integer('invoiced_quantity')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_line_amount', 14, 2)->default(0);
            $table->string('line_status', 30)->default('open');
            $table->timestamps();
            $table->unique(['purchase_order_id', 'line_number']);
        });

        Schema::create('po_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->string('change_order_code', 50);
            $table->text('justification');
            $table->decimal('delta_amount', 14, 2)->default(0);
            $table->decimal('variance_percentage', 5, 2)->default(0);
            $table->json('original_snapshot');
            $table->json('proposed_snapshot');
            $table->boolean('requires_doa_reapproval')->default(false);
            $table->string('status', 30)->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // 10. Procurement Audit Log (immutable event ledger)
        Schema::create('procurement_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_name', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('action_type', 60);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_name', 'entity_id']);
            $table->index(['action_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_audit_logs');
        Schema::dropIfExists('po_revisions');
        Schema::dropIfExists('po_line_items');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_id']);
            $table->dropForeign(['sourcing_rfq_id']);
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn([
                'purchase_request_id',
                'sourcing_rfq_id',
                'cost_center_id',
                'currency',
                'exchange_rate',
                'total_encumbered_amount',
                'payment_terms',
                'incoterms',
                'version',
                'revision_number',
                'dispatched_at',
                'cxml_payload',
            ]);
        });

        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_chains');
        Schema::dropIfExists('sourcing_evaluations');
        Schema::dropIfExists('quote_line_items');

        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->dropForeign(['sourcing_rfq_id']);
            $table->dropColumn([
                'sourcing_rfq_id',
                'quote_number',
                'total_bid_amount',
                'currency',
                'exchange_rate',
                'incoterms',
                'payment_terms',
                'validity_end_date',
                'is_sealed',
                'unsealed_at',
                'is_awarded',
            ]);
        });

        Schema::dropIfExists('rfq_supplier_invitations');
        Schema::dropIfExists('rfq_line_items');
        Schema::dropIfExists('sourcing_rfqs');
        Schema::dropIfExists('pr_line_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('cost_center_budgets');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('procurement_categories');
    }
};
