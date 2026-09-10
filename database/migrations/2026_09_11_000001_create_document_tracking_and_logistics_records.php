<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend Purchase Orders for COA GAM Appendix 61 compliance
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('notes');
            $table->string('mode_of_procurement', 80)->default('public_bidding')->after('delivery_date');
            $table->decimal('penalty_clause_rate', 6, 4)->default(0.0010)->after('mode_of_procurement'); // 1/10 of 1% per day
            $table->string('fund_cluster', 60)->default('01 Regular Agency Fund')->after('penalty_clause_rate');
            $table->date('conforme_date')->nullable()->after('fund_cluster');
            $table->string('conforme_signed_by', 150)->nullable()->after('conforme_date');
            $table->string('entity_name', 150)->default('Philippine General Hospital')->after('conforme_signed_by');
            $table->string('ors_burs_number', 80)->nullable()->after('entity_name');
        });

        // 2. Extend Goods Receipt Notes for Delivery Receipt & Cold Chain tracking
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->string('dr_number', 80)->nullable()->after('grn_number');
            $table->string('sales_invoice_number', 80)->nullable()->after('dr_number');
            $table->string('sscc', 18)->nullable()->after('packing_slip_number');
            $table->boolean('is_cold_chain')->default(false)->after('sscc');
            $table->string('temp_logger_id', 80)->nullable()->after('is_cold_chain');
            $table->decimal('transit_temp_min', 5, 2)->nullable()->after('temp_logger_id');
            $table->decimal('transit_temp_max', 5, 2)->nullable()->after('transit_temp_min');
            $table->boolean('temp_excursion')->default(false)->after('transit_temp_max');
            $table->string('delivery_status', 30)->default('complete')->after('receipt_status');

            $table->index(['dr_number']);
            $table->index(['sales_invoice_number']);
        });

        // 3. Inspection and Acceptance Reports (COA GAM Appendix 50)
        Schema::create('inspection_acceptance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('iar_number', 80)->unique();
            $table->foreignId('goods_receipt_note_id')->unique()->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('invoice_number', 80)->nullable();
            $table->date('iar_date');

            // Technical Inspection Section (Signed by Inspection Officer / Committee)
            $table->date('inspection_date')->nullable();
            $table->foreignId('inspected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('inspection_status', 30)->default('in_order'); // in_order, defective, short_delivery, rejected
            $table->text('inspection_findings')->nullable();

            // Property Custodian Acceptance Section (Signed by Property Custodian / Chief Supply Officer)
            $table->date('acceptance_date')->nullable();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delivery_status', 30)->default('complete'); // complete, partial
            $table->string('status', 30)->default('pending_inspection'); // pending_inspection, inspected_passed, inspected_failed, pending_acceptance, accepted, posted_to_inventory, rejected

            // Liquidated damages calculation (COA GAM Appendix 61: 1/10 of 1% per day of delay)
            $table->unsignedInteger('days_delayed')->default(0);
            $table->decimal('liquidated_damages_amount', 14, 2)->default(0.00);

            // Commission on Audit (COA) 5-Day Mandatory Transmittal Tracking
            $table->date('coa_transmittal_deadline_at')->nullable();
            $table->date('coa_transmitted_at')->nullable();
            $table->string('coa_received_by', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index('iar_date');
        });

        // 4. Central Document Registry (Logistics Documents)
        Schema::create('logistics_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 80)->unique();
            $table->string('document_type', 50); // purchase_order, delivery_receipt, sales_invoice, iar_report, ris_slip, waybill, bill_of_lading, packing_list, certificate_of_analysis, chain_of_custody_record, temperature_log, non_conformance_report, credit_debit_memo, other
            $table->string('title', 200);
            $table->string('reference_number', 100)->nullable();

            // Transactional entity references
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_note_id')->nullable()->constrained('goods_receipt_notes')->nullOnDelete();
            $table->foreignId('inspection_acceptance_report_id')->nullable()->constrained('inspection_acceptance_reports')->nullOnDelete();
            $table->foreignId('material_requisition_id')->nullable()->constrained('material_requisitions')->nullOnDelete();

            // Lifecycle status
            $table->string('status', 30)->default('submitted'); // pending_upload, submitted, received, under_verification, verified, accepted, rejected, correction_required, archived

            // Protected file storage attributes
            $table->string('file_path')->nullable();
            $table->string('file_name', 200)->nullable();
            $table->string('original_name', 200)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('disk', 30)->default('local');
            $table->string('sha256_checksum', 64)->nullable();

            // Dates & NAP Retention Schedule
            $table->date('issued_at')->nullable();
            $table->date('received_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->string('retention_class', 40)->default('financial_10yr'); // operational_2yr, tax_invoice_5yr, financial_10yr, permanent
            $table->date('retention_until')->nullable();

            // Actors
            $table->foreignId('uploaded_by_id')->constrained('users');
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();

            // Versioning and historical preservation
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->foreignId('replaces_document_id')->nullable()->constrained('logistics_documents')->nullOnDelete();
            $table->foreignId('superseded_by_id')->nullable()->constrained('logistics_documents')->nullOnDelete();
            $table->text('revision_reason')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'status']);
            $table->index(['purchase_order_id', 'document_type']);
            $table->index(['supplier_id', 'document_type']);
        });

        // 5. Append-Only Physical & Document Custody Ledger
        Schema::create('chain_of_custody_logs', function (Blueprint $table) {
            $table->id();
            $table->string('custody_number', 80)->unique();
            $table->string('trackable_type');
            $table->unsignedBigInteger('trackable_id');
            $table->string('event_type', 50); // dock_arrival, dock_receiving, transit_temp_verified, quarantine_intake, inspection_handover, inspection_completed, acceptance_custody, inventory_putaway, ris_picking, ward_issuance_handover, coa_transmittal
            $table->foreignId('releasing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('releasing_party_name', 150)->nullable();
            $table->foreignId('receiving_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receiving_party_name', 150)->nullable();
            $table->dateTime('transferred_at');
            $table->string('origin_location', 150)->nullable();
            $table->string('destination_location', 150)->nullable();
            $table->string('package_condition', 50)->default('good_order'); // good_order, damaged_packaging, cold_chain_excursion, tampered_seal, seal_intact
            $table->string('verification_method', 50)->default('credential_auth'); // credential_auth, pin_signature, qr_scan, conforme_signed
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['trackable_type', 'trackable_id']);
            $table->index(['event_type', 'transferred_at']);
        });

        // 6. Inbound Logistics & 3PL Carrier Shipments
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 80)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('carrier_name', 150);
            $table->string('tracking_number', 100)->nullable();
            $table->string('waybill_number', 100)->nullable();
            $table->string('vehicle_plate_number', 50)->nullable();
            $table->string('driver_name', 150)->nullable();
            $table->string('driver_contact', 50)->nullable();
            $table->string('sscc', 18)->nullable(); // GS1 Serial Shipping Container Code
            $table->string('origin_address')->nullable();
            $table->string('destination_facility', 150)->default('Central Hospital Receiving Dock');
            $table->date('dispatch_date')->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->string('status', 30)->default('in_transit'); // created, in_transit, out_for_delivery, arrived_at_dock, received, delayed, diverted, failed_delivery

            // Cold chain monitoring
            $table->boolean('is_cold_chain')->default(false);
            $table->decimal('temp_min', 5, 2)->nullable();
            $table->decimal('temp_max', 5, 2)->nullable();
            $table->string('temp_logger_serial', 80)->nullable();
            $table->boolean('temp_excursion')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('chain_of_custody_logs');
        Schema::dropIfExists('logistics_documents');
        Schema::dropIfExists('inspection_acceptance_reports');

        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            $table->dropIndex(['dr_number']);
            $table->dropIndex(['sales_invoice_number']);
            $table->dropColumn([
                'dr_number',
                'sales_invoice_number',
                'sscc',
                'is_cold_chain',
                'temp_logger_id',
                'transit_temp_min',
                'transit_temp_max',
                'temp_excursion',
                'delivery_status',
            ]);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_date',
                'mode_of_procurement',
                'penalty_clause_rate',
                'fund_cluster',
                'conforme_date',
                'conforme_signed_by',
                'entity_name',
                'ors_burs_number',
            ]);
        });
    }
};
