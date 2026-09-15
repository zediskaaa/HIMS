<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\ChainOfCustodyLog;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\LogisticsDocument;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Shipment;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Support\DemoPdfBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class LogisticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure realistic users exist
        $inventoryManager = User::active()->role(UserRole::InventoryManager)->oldest('id')->first() ?? User::firstOrCreate(
            ['email' => 'manager.inv@hims.local'],
            [
                'name' => 'Dr. Maria Santos, RPh',
                'password' => bcrypt('Password123!'),
                'role' => UserRole::InventoryManager,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $warehouseStaff = User::active()->role(UserRole::WarehouseStaff)->oldest('id')->first() ?? User::firstOrCreate(
            ['email' => 'dock.officer@hims.local'],
            [
                'name' => 'Eduardo Reyes',
                'password' => bcrypt('Password123!'),
                'role' => UserRole::WarehouseStaff,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 2. Ensure realistic Healthcare Suppliers
        $zuellig = Supplier::firstOrCreate(
            ['name' => 'Zuellig Pharma Philippines, Inc.'],
            [
                'contact_person' => 'Roberto Cruz',
                'email' => 'orders@zuelligpharma.com.ph',
                'phone' => '+63 2 8988 8888',
                'address' => 'KM 14 West Service Road, South Superhighway, Parañaque City, Metro Manila',
                'status' => 'active',
            ]
        );

        $metroDrug = Supplier::firstOrCreate(
            ['name' => 'Metro Drug, Inc.'],
            [
                'contact_person' => 'Corazon Ramos',
                'email' => 'hospital_sales@metrodrug.com.ph',
                'phone' => '+63 2 8837 0000',
                'address' => 'Sta. Rosa Commercial Complex, Santa Rosa, Laguna',
                'status' => 'active',
            ]
        );

        // 3. Ensure Storage Location
        $coldStorage = StorageLocation::firstOrCreate(
            ['code' => 'COLD-01-A'],
            [
                'name' => 'Walk-In Biological Cold Room A (2°-8°C)',
                'type' => 'room',
                'zone' => 'Cold Chain Zone',
                'aisle' => 'C1',
                'rack' => '01',
                'shelf' => 'A',
                'bin' => '01',
                'status' => 'active',
            ]
        );

        $centralStorage = StorageLocation::firstOrCreate(
            ['code' => 'MAIN-A1-01'],
            [
                'name' => 'Main Warehouse Rack A1',
                'type' => 'shelf',
                'zone' => 'Ambient Dry Goods',
                'aisle' => 'A',
                'rack' => '01',
                'shelf' => 'A',
                'bin' => '01',
                'status' => 'active',
            ]
        );

        // 4. Ensure Inventory Items
        $rabiesVaccine = InventoryItem::firstOrCreate(
            ['sku' => 'VAC-RAB-VER05'],
            [
                'name' => 'Verorab Inactivated Rabies Vaccine 0.5mL Vial + Diluent',
                'generic_name' => 'Rabies Vaccine, Inactivated (Wistar Rabies PM/WI38 1503-3M strain)',
                'unit' => 'vial',
                'unit_cost' => 1450.00,
                'reorder_level' => 50,
                'storage_temp_min' => 2.0,
                'storage_temp_max' => 8.0,
                'temperature_classification' => 'COLD_CHAIN',
                'supplier_id' => $zuellig->id,
                'default_location_id' => $coldStorage->id,
                'quantity_on_hand' => 250,
                'status' => 'active',
            ]
        );

        $meropenem = InventoryItem::firstOrCreate(
            ['sku' => 'ANT-MER-1G00'],
            [
                'name' => 'Meropenem Trihydrate 1g Powder for Injection',
                'generic_name' => 'Meropenem',
                'unit' => 'vial',
                'unit_cost' => 450.00,
                'reorder_level' => 200,
                'storage_temp_min' => 15.0,
                'storage_temp_max' => 30.0,
                'temperature_classification' => 'ROOM_TEMPERATURE',
                'supplier_id' => $metroDrug->id,
                'default_location_id' => $centralStorage->id,
                'quantity_on_hand' => 800,
                'status' => 'active',
            ]
        );

        // 5. Purchase Order 1: Rabies Vaccine (Zuellig)
        $poRabies = PurchaseOrder::firstOrCreate(
            ['po_number' => 'PO-2026-09-0145'],
            [
                'supplier_id' => $zuellig->id,
                'item_id' => $rabiesVaccine->id,
                'quantity' => 500,
                'unit_cost' => 1450.00,
                'total_amount' => 725000.00,
                'status' => PurchaseOrderStatus::Approved->value,
                'delivery_date' => now()->toDateString(),
                'mode_of_procurement' => 'Public Bidding (RA 9184 Sec. 10)',
                'fund_cluster' => '01 - Regular Agency Fund',
                'entity_name' => 'HOSPITAL INFORMATION MANAGEMENT SYSTEM',
                'ors_burs_number' => 'ORS-2026-09-00941',
                'penalty_clause_rate' => 0.00100,
                'conforme_date' => now()->subDays(5)->toDateString(),
                'conforme_signed_by' => 'Roberto Cruz (VP Sales, Zuellig Pharma)',
                'notes' => 'Standard COA GAM App. 61 terms with 1/10 of 1% liquidated damages per calendar day of delay.',
            ]
        );

        PurchaseOrderLine::firstOrCreate(
            ['purchase_order_id' => $poRabies->id, 'item_id' => $rabiesVaccine->id],
            [
                'line_number' => 1,
                'ordered_quantity' => 500,
                'received_quantity' => 500,
                'unit_price' => 1450.00,
                'total_line_amount' => 725000.00,
            ]
        );

        // Inbound Shipment 1 (Cold Chain, Arrived at Dock)
        $shipmentRabies = Shipment::firstOrCreate(
            ['shipment_number' => 'SHP-2026-00001'],
            [
                'purchase_order_id' => $poRabies->id,
                'supplier_id' => $zuellig->id,
                'carrier_name' => 'Zuellig Pharma Cold Logistics',
                'tracking_number' => 'ZP-CL-8899221',
                'waybill_number' => 'WB-MNL-00912',
                'vehicle_plate_number' => 'NDO-9821',
                'driver_name' => 'Danilo Bautista',
                'driver_contact' => '+63 917 555 1234',
                'sscc' => '000123456700000015',
                'origin_address' => 'Zuellig Pharma National Distribution Center, Santa Rosa, Laguna',
                'destination_facility' => 'HIMS Central Receiving Dock',
                'dispatch_date' => now()->subDay()->toDateString(),
                'estimated_delivery_date' => now()->toDateString(),
                'actual_delivery_date' => now()->toDateString(),
                'status' => 'arrived_at_dock',
                'is_cold_chain' => true,
                'temp_min' => 3.4,
                'temp_max' => 5.6,
                'temp_logger_serial' => 'SEN-LOG-ZP-9941',
                'temp_excursion' => false,
                'notes' => 'Vaccine shipment intact. Thermal logger reading within 2.0°C - 8.0°C compliant range.',
            ]
        );

        // Goods Receipt Note 1
        $grnRabies = GoodsReceiptNote::firstOrCreate(
            ['grn_number' => 'GRN-2026-09-001'],
            [
                'purchase_order_id' => $poRabies->id,
                'supplier_id' => $zuellig->id,
                'received_by_id' => $warehouseStaff->id,
                'received_at' => now(),
                'receipt_status' => 'received',
                'delivery_status' => 'arrived_at_dock',
                'dr_number' => 'DR-ZP-889922',
                'sales_invoice_number' => 'SI-2026-088192',
                'sscc' => '000123456700000015',
                'is_cold_chain' => true,
                'temp_logger_id' => 'SEN-LOG-ZP-9941',
                'transit_temp_min' => 3.4,
                'transit_temp_max' => 5.6,
                'temp_excursion' => false,
                'carrier_name' => 'Zuellig Pharma Cold Logistics',
                'notes' => 'Received at central cold storage loading bay.',
            ]
        );

        GoodsReceiptNoteLine::firstOrCreate(
            ['goods_receipt_note_id' => $grnRabies->id, 'item_id' => $rabiesVaccine->id],
            [
                'ordered_quantity' => 500,
                'received_quantity' => 500,
                'accepted_quantity' => 500,
                'rejected_quantity' => 0,
                'batch_number' => 'VER-2026-881',
                'expiry_date' => now()->addYears(2)->toDateString(),
                'destination_location_id' => $coldStorage->id,
                'unit_cost' => 1450.00,
            ]
        );

        // COA GAM App. 50 IAR 1: Pending Technical Inspection
        $iarRabies = InspectionAcceptanceReport::firstOrCreate(
            ['iar_number' => 'IAR-2026-09-00001'],
            [
                'goods_receipt_note_id' => $grnRabies->id,
                'purchase_order_id' => $poRabies->id,
                'supplier_id' => $zuellig->id,
                'invoice_number' => 'SI-2026-088192',
                'iar_date' => now()->toDateString(),
                'status' => 'pending_inspection',
                'delivery_status' => 'complete',
                'days_delayed' => 0,
                'liquidated_damages_amount' => 0.00,
                'coa_transmittal_deadline_at' => now()->addDays(5)->toDateString(),
            ]
        );

        // Chain of Custody for Rabies Vaccine
        if (! ChainOfCustodyLog::where('custody_number', 'COC-202609-000001')->exists()) {
            ChainOfCustodyLog::create([
                'custody_number' => 'COC-202609-000001',
                'trackable_type' => Shipment::class,
                'trackable_id' => $shipmentRabies->id,
                'event_type' => 'dock_arrival',
                'releasing_party_name' => 'Danilo Bautista (Zuellig Fleet)',
                'receiving_user_id' => $warehouseStaff->id,
                'receiving_party_name' => $warehouseStaff->name.' (Receiving Officer)',
                'transferred_at' => now()->subHours(2),
                'origin_location' => 'Zuellig Santa Rosa Logistics Hub',
                'destination_location' => 'HIMS Central Receiving Dock Bay 1',
                'package_condition' => 'good_order',
                'verification_method' => 'credential_auth',
                'notes' => 'Dock intake complete. Verified temperature range: 3.4°C to 5.6°C. Zero excursion.',
                'ip_address' => '127.0.0.1',
            ]);
        }

        // 6. Purchase Order 2: Delayed Delivery Demonstrating Liquidated Damages
        // Expected 14 days ago, delivered 4 days ago -> 10 days delayed
        $poDelayed = PurchaseOrder::firstOrCreate(
            ['po_number' => 'PO-2026-08-0098'],
            [
                'supplier_id' => $metroDrug->id,
                'item_id' => $meropenem->id,
                'quantity' => 1000,
                'unit_cost' => 450.00,
                'total_amount' => 450000.00,
                'status' => PurchaseOrderStatus::Fulfilled->value,
                'delivery_date' => now()->subDays(14)->toDateString(),
                'mode_of_procurement' => 'Competitive Bidding',
                'fund_cluster' => '01 - Regular Agency Fund',
                'entity_name' => 'HOSPITAL INFORMATION MANAGEMENT SYSTEM',
                'ors_burs_number' => 'ORS-2026-08-00812',
                'penalty_clause_rate' => 0.00100,
                'conforme_date' => now()->subDays(30)->toDateString(),
                'conforme_signed_by' => 'Corazon Ramos (Metro Drug Sales)',
                'notes' => 'COA GAM App. 61 Liquidated Damages Clause: 1/10 of 1% (0.001) per day of delay on total value.',
            ]
        );

        PurchaseOrderLine::firstOrCreate(
            ['purchase_order_id' => $poDelayed->id, 'item_id' => $meropenem->id],
            [
                'line_number' => 1,
                'ordered_quantity' => 1000,
                'received_quantity' => 1000,
                'unit_price' => 450.00,
                'total_line_amount' => 450000.00,
            ]
        );

        $shipmentDelayed = Shipment::firstOrCreate(
            ['shipment_number' => 'SHP-2026-00002'],
            [
                'purchase_order_id' => $poDelayed->id,
                'supplier_id' => $metroDrug->id,
                'carrier_name' => '2GO Express Hospital Freight',
                'tracking_number' => '2GO-MD-991204',
                'waybill_number' => 'WB-LAG-8812',
                'vehicle_plate_number' => 'CBH-4412',
                'driver_name' => 'Arnel Pineda',
                'driver_contact' => '+63 918 888 4321',
                'sscc' => '376123450000100082',
                'origin_address' => 'Metro Drug Central Distribution Facility, Sta. Rosa',
                'destination_facility' => 'HIMS Central Receiving Dock',
                'dispatch_date' => now()->subDays(5)->toDateString(),
                'estimated_delivery_date' => now()->subDays(14)->toDateString(),
                'actual_delivery_date' => now()->subDays(4)->toDateString(),
                'status' => 'received',
                'is_cold_chain' => false,
                'notes' => 'Delayed delivery due to supplier factory logistics backlog.',
            ]
        );

        $grnDelayed = GoodsReceiptNote::firstOrCreate(
            ['grn_number' => 'GRN-2026-09-002'],
            [
                'purchase_order_id' => $poDelayed->id,
                'supplier_id' => $metroDrug->id,
                'received_by_id' => $warehouseStaff->id,
                'received_at' => now()->subDays(4),
                'receipt_status' => 'received',
                'delivery_status' => 'arrived_at_dock',
                'dr_number' => 'DR-MD-772110',
                'sales_invoice_number' => 'SI-2026-044190',
                'carrier_name' => '2GO Express Hospital Freight',
                'notes' => '10 days delivery delay recorded.',
            ]
        );

        GoodsReceiptNoteLine::firstOrCreate(
            ['goods_receipt_note_id' => $grnDelayed->id, 'item_id' => $meropenem->id],
            [
                'ordered_quantity' => 1000,
                'received_quantity' => 1000,
                'accepted_quantity' => 1000,
                'rejected_quantity' => 0,
                'batch_number' => 'MRP-2609-01',
                'expiry_date' => now()->addYears(3)->toDateString(),
                'destination_location_id' => $centralStorage->id,
                'unit_cost' => 450.00,
            ]
        );

        // COA GAM App. 50 IAR 2: Inspected, Accepted, with ₱4,500 Liquidated Damages!
        // 10 days delay * 0.001 * ₱450,000 = ₱4,500.00
        $iarDelayed = InspectionAcceptanceReport::firstOrCreate(
            ['iar_number' => 'IAR-2026-09-00002'],
            [
                'goods_receipt_note_id' => $grnDelayed->id,
                'purchase_order_id' => $poDelayed->id,
                'supplier_id' => $metroDrug->id,
                'invoice_number' => 'SI-2026-044190',
                'iar_date' => now()->subDays(4)->toDateString(),
                'status' => 'accepted',
                'delivery_status' => 'complete',
                'inspected_by_id' => $warehouseStaff->id,
                'inspection_date' => now()->subDays(4)->toDateString(),
                'inspection_status' => 'in_order',
                'inspection_findings' => 'Inspected and verified 1,000 vials Meropenem 1g against CoA specifications. Batch MRP-2609-01 authentic.',
                'accepted_by_id' => $inventoryManager->id,
                'acceptance_date' => now()->subDays(3)->toDateString(),
                'days_delayed' => 10,
                'liquidated_damages_amount' => 4500.00,
                'coa_transmittal_deadline_at' => now()->addDays(1)->toDateString(),
                'notes' => 'Accepted in full. Liquidated damages assessed for 10 calendar days delay per COA GAM App. 61.',
            ]
        );

        // 7. Seed Authentic PDF Records in Storage
        Storage::disk('local')->makeDirectory('logistics_documents');

        $drPdfContent = DemoPdfBuilder::create(
            title: 'ZUELLIG PHARMA PHILIPPINES, INC. - DELIVERY RECEIPT',
            sections: [
                [
                    'heading' => 'DELIVERY & CONSIGNMENT PARTICULARS',
                    'lines' => [
                        'Supplier / Carrier: Zuellig Pharma Philippines, Inc. (Cold Chain Fleet)',
                        'Dispatch Facility: KM 14 West Service Road, South Superhighway, Paranaque City',
                        'Recipient: Hospital Information Management System (Central Receiving Dock Bay 1)',
                        'Purchase Order Ref: PO-2026-09-0145 | Waybill: WB-MNL-00912 | Plate: NDO-9821',
                        'Delivery Date: '.now()->toDateString().' | Mode: Cold Chain Direct Fleet Transfer',
                    ],
                ],
                [
                    'heading' => 'DELIVERED INVENTORY & BATCH SPECIFICATIONS',
                    'table' => [
                        'headers' => ['Item / Product Name', 'Batch / Lot No.', 'Expiry Date', 'Quantity', 'Unit Cost (PHP)'],
                        'rows' => [
                            ['Verorab Inactivated Rabies Vaccine 0.5mL + Diluent', 'VER-2026-881', now()->addYears(2)->toDateString(), '500 vials', '1,450.00'],
                        ],
                    ],
                ],
                [
                    'heading' => 'RECEIVING INSPECTION & COLD CHAIN COMPLIANCE',
                    'lines' => [
                        'Storage Requirement: Biological Cold Chain Protocol (2.0 deg C to 8.0 deg C)',
                        'Sensor Logger SEN-LOG-ZP-9941: Transit Range 3.4 deg C - 5.6 deg C (Temperature Excursion: None)',
                        'Receiving Officer: Eduardo Reyes (Warehouse Receiving Staff)',
                        'Physical Inspection: Packaging intact, seal unbroken, tamper-evident indicators valid.',
                    ],
                ],
            ],
            subtitle: 'Healthcare Logistics & Central Receiving Documentation | DR No: DR-ZP-889922'
        );

        $docPath1 = 'logistics_documents/demo_dr_889922.pdf';
        Storage::disk('local')->put($docPath1, $drPdfContent);

        $doc1 = LogisticsDocument::updateOrCreate(
            ['tracking_number' => 'DOC-DR-202609-00001'],
            [
                'document_type' => DocumentType::DeliveryReceipt,
                'reference_number' => 'DR-ZP-889922',
                'title' => 'Zuellig Pharma Delivery Receipt DR-ZP-889922',
                'purchase_order_id' => $poRabies->id,
                'goods_receipt_note_id' => $grnRabies->id,
                'supplier_id' => $zuellig->id,
                'file_path' => $docPath1,
                'file_name' => 'demo_dr_889922.pdf',
                'original_name' => 'Zuellig_DR_889922.pdf',
                'file_size_bytes' => strlen($drPdfContent),
                'mime_type' => 'application/pdf',
                'disk' => 'local',
                'sha256_checksum' => hash('sha256', $drPdfContent),
                'version_number' => 1,
                'status' => 'verified',
                'uploaded_by_id' => $warehouseStaff->id,
                'verified_by_id' => $inventoryManager->id,
                'verified_at' => now()->subHours(1),
                'retention_class' => 'operational_2yr',
                'retention_until' => now()->addYears(2),
                'verification_notes' => 'DR matches PO quantities and physical shipment stamp.',
            ]
        );

        $siPdfContent = DemoPdfBuilder::create(
            title: 'ZUELLIG PHARMA PHILIPPINES, INC. - ELECTRONIC SALES INVOICE',
            sections: [
                [
                    'heading' => 'TAXPAYER & INVOICE DETAILS',
                    'lines' => [
                        'Seller: Zuellig Pharma Philippines, Inc. | VAT Reg TIN: 000-123-456-000',
                        'Customer: Hospital Information Management System | Fund Cluster: 01 Regular Agency Fund',
                        'Billing Address: Central Medical Logistics & Supply Division, Manila, Philippines',
                        'Purchase Order Ref: PO-2026-09-0145 | Invoice Date: '.now()->toDateString().' | Terms: Net 30 Days',
                    ],
                ],
                [
                    'heading' => 'INVOICED LINE ITEMS & VALUES',
                    'table' => [
                        'headers' => ['Item Description', 'Qty / Unit', 'Unit Price', 'Tax Status', 'Total (PHP)'],
                        'rows' => [
                            ['Verorab Inactivated Rabies Vaccine 0.5mL Vial', '500 vials', '1,450.00', 'VAT-Exempt', '725,000.00'],
                        ],
                    ],
                ],
                [
                    'heading' => 'FINANCIAL SUMMARY & BIR CERTIFICATION',
                    'lines' => [
                        'Total Net Amount Due: PHP 725,000.00 (Seven Hundred Twenty-Five Thousand Pesos Only)',
                        'VAT Status: Zero-Rated / Exempt under Republic Act 10963 (TRAIN Law)',
                        'Payment Terms: Net 30 Calendar Days via Authorized Government Depository Bank (LBP)',
                        'BIR Digital Certification: Official electronic invoice archived pursuant to RA 11976 regulations.',
                    ],
                ],
            ],
            subtitle: 'BIR Electronic Invoice (RA 11976 Ease of Paying Taxes Compliant) | SI No: SI-2026-088192'
        );

        $docPath2 = 'logistics_documents/demo_si_088192.pdf';
        Storage::disk('local')->put($docPath2, $siPdfContent);

        $doc2 = LogisticsDocument::updateOrCreate(
            ['tracking_number' => 'DOC-INV-202609-00002'],
            [
                'document_type' => DocumentType::SalesInvoice,
                'reference_number' => 'SI-2026-088192',
                'title' => 'Zuellig Pharma BIR Electronic Sales Invoice SI-2026-088192',
                'purchase_order_id' => $poRabies->id,
                'goods_receipt_note_id' => $grnRabies->id,
                'supplier_id' => $zuellig->id,
                'file_path' => $docPath2,
                'file_name' => 'demo_si_088192.pdf',
                'original_name' => 'Zuellig_SI_088192_BIR_RA11976.pdf',
                'file_size_bytes' => strlen($siPdfContent),
                'mime_type' => 'application/pdf',
                'disk' => 'local',
                'sha256_checksum' => hash('sha256', $siPdfContent),
                'version_number' => 1,
                'status' => 'submitted',
                'uploaded_by_id' => $warehouseStaff->id,
                'retention_class' => 'tax_invoice_5yr',
                'retention_until' => now()->addYears(5),
            ]
        );
    }
}
