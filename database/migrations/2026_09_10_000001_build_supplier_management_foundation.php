<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('trade_name')->nullable()->after('name');
            $table->string('business_structure', 50)->nullable()->after('trade_name');
            $table->boolean('provides_regulated_health_products')->default(false)->after('business_structure');
            $table->text('billing_address')->nullable()->after('address');
            $table->text('delivery_address')->nullable()->after('billing_address');
            $table->string('identity_key')->nullable()->unique()->after('tax_number');
            $table->string('accreditation_status', 30)->default('draft')->index()->after('status');
            $table->date('accreditation_expires_at')->nullable()->index()->after('accreditation_status');
            $table->unsignedSmallInteger('standard_lead_time_days')->nullable()->after('accreditation_expires_at');
            $table->text('payment_terms')->nullable()->after('standard_lead_time_days');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('last_reviewed_at')->nullable()->after('approved_by');
            $table->text('suspension_reason')->nullable()->after('last_reviewed_at');
            $table->index(['status', 'accreditation_status'], 'suppliers_procurement_eligibility_index');
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('name');
            $table->string('contact_type', 40)->default('primary');
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['supplier_id', 'is_active']);
        });

        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('verification_status', 30)->default('pending');
            $table->boolean('required_for_accreditation')->default(false);
            $table->boolean('blocks_procurement_when_invalid')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('supplier_documents')->nullOnDelete();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->index(['supplier_id', 'verification_status'], 'supplier_documents_verification_index');
            $table->index(['supplier_id', 'expires_at'], 'supplier_documents_expiry_index');
            $table->index(['supplier_id', 'is_current'], 'supplier_documents_current_index');
        });

        Schema::create('supplier_accreditations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->string('status', 30);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'cycle_number']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('contract_number');
            $table->string('contract_type')->nullable();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('payment_terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'contract_number']);
            $table->index(['supplier_id', 'ends_at']);
        });

        Schema::create('supplier_compliance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('supplier_document_id')->nullable()->constrained('supplier_documents')->nullOnDelete();
            $table->foreignId('supplier_contract_id')->nullable()->constrained('supplier_contracts')->nullOnDelete();
            $table->string('source_key')->unique();
            $table->string('type', 40);
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->date('due_date');
            $table->string('message');
            $table->timestamp('first_detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'status'], 'supplier_compliance_alerts_status_index');
            $table->index(['status', 'due_date'], 'supplier_compliance_alerts_due_index');
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->string('supplier_product_name')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('brand')->nullable();
            $table->string('pack_size')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('minimum_order_quantity')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'item_id']);
            $table->index(['item_id', 'is_active']);
        });

        Schema::create('supplier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_id')->constrained('supplier_products')->restrictOnDelete();
            $table->foreignId('supplier_contract_id')->nullable()->constrained('supplier_contracts')->nullOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->unsignedInteger('minimum_order_quantity')->default(1);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_product_id', 'effective_from'], 'supplier_prices_effective_index');
        });

        // A supplier is a historical counterparty. Prevent an application-level
        // delete from cascading away financial/procurement evidence.
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
        Schema::table('supplier_quotes', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });

        Schema::dropIfExists('supplier_prices');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_compliance_alerts');
        Schema::dropIfExists('supplier_contracts');
        Schema::dropIfExists('supplier_accreditations');
        Schema::dropIfExists('supplier_documents');
        Schema::dropIfExists('supplier_contacts');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_procurement_eligibility_index');
            $table->dropUnique(['identity_key']);
            $table->dropIndex(['accreditation_status']);
            $table->dropIndex(['accreditation_expires_at']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'trade_name',
                'business_structure',
                'provides_regulated_health_products',
                'billing_address',
                'delivery_address',
                'identity_key',
                'accreditation_status',
                'accreditation_expires_at',
                'standard_lead_time_days',
                'payment_terms',
                'last_reviewed_at',
                'suspension_reason',
            ]);
        });
    }
};
