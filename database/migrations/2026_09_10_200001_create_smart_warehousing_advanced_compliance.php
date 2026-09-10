<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->string('aisle', 30)->nullable()->after('zone');
            $table->string('rack', 30)->nullable()->after('aisle');
            $table->string('shelf', 30)->nullable()->after('rack');
            $table->string('bin', 30)->nullable()->after('shelf');
            $table->boolean('is_narcotics_vault')->default(false)->after('is_damaged_stock');
            $table->boolean('is_hazardous_containment')->default(false)->after('is_narcotics_vault');
            $table->decimal('max_weight_kg', 8, 2)->nullable()->after('capacity');
            $table->boolean('is_frozen_for_count')->default(false)->after('is_hazardous_containment');
            $table->boolean('excursion_hold')->default(false)->after('is_frozen_for_count');

            $table->index(['is_narcotics_vault', 'status']);
            $table->index(['excursion_hold', 'status']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('generic_name', 255)->nullable()->after('name');
            $table->string('brand_name', 255)->nullable()->after('generic_name');
            $table->string('dosage_form_strength', 255)->nullable()->after('brand_name');
            $table->string('regulatory_category', 40)->default('GENERAL_RX')->after('dosage_form_strength');
            $table->string('lasa_group_code', 20)->nullable()->after('regulatory_category');
            $table->decimal('storage_temp_min', 4, 2)->nullable()->after('temperature_classification');
            $table->decimal('storage_temp_max', 4, 2)->nullable()->after('storage_temp_min');
            $table->string('fda_cpr_number', 50)->nullable()->after('storage_temp_max');
            $table->boolean('is_consignment')->default(false)->after('fda_cpr_number');

            $table->index(['regulatory_category', 'status']);
            $table->index(['lasa_group_code']);
            $table->index(['is_consignment', 'status']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('hash', 64)->nullable()->after('remarks');
            $table->string('previous_hash', 64)->nullable()->after('hash');

            $table->index(['hash']);
        });

        Schema::create('iot_telemetry_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sensor_id', 60);
            $table->foreignId('storage_location_id')->constrained('storage_locations')->cascadeOnDelete();
            $table->decimal('temperature_celsius', 4, 2);
            $table->decimal('relative_humidity_pct', 4, 2)->nullable();
            $table->string('excursion_status', 20)->default('normal'); // normal, warning, excursion
            $table->string('resulting_event', 100)->nullable();
            $table->dateTime('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['storage_location_id', 'recorded_at']);
            $table->index(['sensor_id', 'recorded_at']);
            $table->index(['excursion_status', 'recorded_at']);
        });

        Schema::create('pdea_dangerous_drugs_register', function (Blueprint $table) {
            $table->id();
            $table->string('register_number', 64)->unique();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('pdea_spf_number', 50)->nullable(); // Yellow Prescription serial
            $table->string('physician_s2_license', 30)->nullable();
            $table->string('prescriber_name', 255)->nullable();
            $table->string('patient_encounter_id', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('running_balance');
            $table->foreignId('custodian_id')->constrained('users');
            $table->foreignId('witness_pharmacist_id')->constrained('users');
            $table->dateTime('witness_authenticated_at');
            $table->text('notes')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['item_id', 'recorded_at']);
            $table->index(['pdea_spf_number']);
            $table->index(['physician_s2_license']);
        });

        Schema::create('surgical_consignment_bill_onlys', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 64)->unique();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('inventory_serial_id')->nullable()->constrained('inventory_serials')->nullOnDelete();
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('patient_encounter_id', 100);
            $table->string('operating_suite', 100);
            $table->string('surgeon_name', 255);
            $table->unsignedInteger('implanted_quantity')->default(1);
            $table->string('status', 30)->default('pending_po'); // pending_po, po_generated, closed
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('recorded_by_id')->constrained('users');
            $table->dateTime('implanted_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_encounter_id']);
            $table->index(['status', 'implanted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surgical_consignment_bill_onlys');
        Schema::dropIfExists('pdea_dangerous_drugs_register');
        Schema::dropIfExists('iot_telemetry_logs');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['hash']);
            $table->dropColumn(['hash', 'previous_hash']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['regulatory_category', 'status']);
            $table->dropIndex(['lasa_group_code']);
            $table->dropIndex(['is_consignment', 'status']);
            $table->dropColumn([
                'generic_name', 'brand_name', 'dosage_form_strength',
                'regulatory_category', 'lasa_group_code',
                'storage_temp_min', 'storage_temp_max',
                'fda_cpr_number', 'is_consignment',
            ]);
        });

        Schema::table('storage_locations', function (Blueprint $table) {
            $table->dropIndex(['is_narcotics_vault', 'status']);
            $table->dropIndex(['excursion_hold', 'status']);
            $table->dropColumn([
                'aisle', 'rack', 'shelf', 'bin',
                'is_narcotics_vault', 'is_hazardous_containment',
                'max_weight_kg', 'is_frozen_for_count', 'excursion_hold',
            ]);
        });
    }
};
