<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->string('barcode_value', 100)->nullable()->unique()->after('code');
            $table->string('storage_classification', 40)->nullable()->after('zone');
            $table->string('temperature_classification', 40)->nullable()->after('storage_classification');
            $table->string('capacity_unit', 30)->default('units')->after('capacity');
            $table->boolean('is_receiving_staging')->default(false)->after('capacity_unit');
            $table->boolean('is_quarantine')->default(false)->after('is_receiving_staging');
            $table->boolean('is_pick_face')->default(false)->after('is_quarantine');
            $table->boolean('is_reserve')->default(false)->after('is_pick_face');
            $table->boolean('is_dispatch_staging')->default(false)->after('is_reserve');
            $table->boolean('is_returns_area')->default(false)->after('is_dispatch_staging');
            $table->boolean('is_damaged_stock')->default(false)->after('is_returns_area');
            $table->unsignedInteger('sort_sequence')->default(0)->after('is_damaged_stock');

            $table->index(['type', 'status']);
            $table->index(['parent_id', 'sort_sequence']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('barcode_value', 100)->nullable()->unique()->after('sku');
            $table->string('gtin', 14)->nullable()->unique()->after('barcode_value');
            $table->boolean('is_expiry_tracked')->default(false)->after('is_serial_tracked');
            $table->string('storage_classification', 40)->nullable()->after('is_expiry_tracked');
            $table->string('temperature_classification', 40)->nullable()->after('storage_classification');
            $table->unsignedInteger('pick_face_minimum')->default(0)->after('temperature_classification');
            $table->unsignedInteger('pick_face_maximum')->default(0)->after('pick_face_minimum');
        });

        Schema::create('storage_location_category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_location_id')->constrained('storage_locations')->cascadeOnDelete();
            $table->foreignId('item_category_id')->constrained('item_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['storage_location_id', 'item_category_id'], 'location_category_rule_unique');
        });

        Schema::create('warehouse_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_number', 64)->unique();
            $table->string('task_type', 40);
            $table->string('status', 30)->default('ready');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('source_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->unsignedInteger('requested_quantity')->default(0);
            $table->unsignedInteger('completed_quantity')->default(0);
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('reference');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('recommendation_reason')->nullable();
            $table->text('override_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'due_at']);
            $table->index(['assigned_to_id', 'status']);
            $table->index(['task_type', 'status']);
        });

        Schema::create('warehouse_task_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_task_id')->constrained('warehouse_tasks')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['warehouse_task_id', 'created_at']);
        });

        Schema::create('barcode_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 255)->unique();
            $table->string('symbology', 30)->default('internal');
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });

        Schema::create('warehouse_scan_events', function (Blueprint $table) {
            $table->id();
            $table->string('scan_identifier', 64)->unique();
            $table->foreignId('warehouse_task_id')->nullable()->constrained('warehouse_tasks')->nullOnDelete();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->text('raw_value');
            $table->text('normalized_value');
            $table->string('symbology', 30)->default('unknown');
            $table->string('resolved_type', 30)->nullable();
            $table->unsignedBigInteger('resolved_id')->nullable();
            $table->string('outcome', 20);
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('scanned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['warehouse_task_id', 'sequence_number']);
            $table->index(['outcome', 'created_at']);
        });

        Schema::create('warehouse_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('exception_number', 64)->unique();
            $table->foreignId('warehouse_task_id')->nullable()->constrained('warehouse_tasks')->nullOnDelete();
            $table->string('exception_type', 50);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open');
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->text('details');
            $table->foreignId('raised_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('serial_number', 100);
            $table->string('status', 30)->default('quarantined');
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->unique(['item_id', 'serial_number'], 'inventory_serial_item_unique');
            $table->index(['storage_location_id', 'status']);
        });

        Schema::create('warehouse_label_prints', function (Blueprint $table) {
            $table->id();
            $table->string('label_number', 64)->unique();
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');
            $table->string('template', 30);
            $table->json('payload');
            $table->unsignedInteger('copies')->default(1);
            $table->foreignId('printed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('printed_at');
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_label_prints');
        Schema::dropIfExists('inventory_serials');
        Schema::dropIfExists('warehouse_exceptions');
        Schema::dropIfExists('warehouse_scan_events');
        Schema::dropIfExists('barcode_aliases');
        Schema::dropIfExists('warehouse_task_events');
        Schema::dropIfExists('warehouse_tasks');
        Schema::dropIfExists('storage_location_category_rules');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['barcode_value']);
            $table->dropUnique(['gtin']);
            $table->dropColumn(['barcode_value', 'gtin', 'is_expiry_tracked', 'storage_classification', 'temperature_classification', 'pick_face_minimum', 'pick_face_maximum']);
        });

        Schema::table('storage_locations', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropIndex(['parent_id', 'sort_sequence']);
            $table->dropUnique(['barcode_value']);
            $table->dropColumn([
                'barcode_value', 'storage_classification', 'temperature_classification', 'capacity_unit',
                'is_receiving_staging', 'is_quarantine', 'is_pick_face', 'is_reserve',
                'is_dispatch_staging', 'is_returns_area', 'is_damaged_stock', 'sort_sequence',
            ]);
        });
    }
};
