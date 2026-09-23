<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierAccreditation;
use App\Models\SupplierContact;
use App\Models\SupplierContract;
use App\Models\SupplierPrice;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HospitalMasterDataCompletionSeeder extends Seeder
{
    /**
     * Supplier master data definitions.
     */
    private const SUPPLIERS = [
        1 => [
            'match_name' => 'MedSupply',
            'name' => 'MedSupply Healthcare Corporation',
            'trade_name' => 'MedSupply Phils',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => false,
            'tax_number' => '102-458-791-000',
            'identity_key' => 'tax:102458791000',
            'contact_person' => 'Maria Teresa Santos',
            'email' => 'orders@medsupply-ph.test',
            'phone' => '+63 2 8721 4501',
            'address' => 'Unit 402 Medical Arts Tower, 84 E. Rodriguez Sr. Ave, New Manila, Quezon City, Metro Manila',
            'billing_address' => 'Unit 402 Medical Arts Tower, 84 E. Rodriguez Sr. Ave, New Manila, Quezon City, Metro Manila',
            'delivery_address' => 'HIMS Central Receiving Dock, Building B',
            'standard_lead_time_days' => 5,
            'payment_terms' => 'Net 30 days upon final inspection and acceptance',
            'notes' => 'Accredited institutional provider of certified personal protective equipment and general ward supplies.',
            'contact' => [
                'name' => 'Maria Teresa Santos',
                'position' => 'Institutional Sales Manager',
                'email' => 'maria.santos@medsupply-ph.test',
                'phone' => '+63 2 8721 4501',
                'mobile' => '+63 917 123 4567',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-001',
                'contract_type' => 'Framework Agreement',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days upon inspection and acceptance',
                'delivery_terms' => 'Delivered Duty Paid (DDP) to HIMS Central Warehouse',
            ],
        ],
        2 => [
            'match_name' => 'Bayanihan',
            'name' => 'Bayanihan Community Hospital Supply Cooperative',
            'trade_name' => 'Bayanihan Hospital Supply',
            'business_structure' => 'cooperative',
            'provides_regulated_health_products' => false,
            'tax_number' => '245-731-680-000',
            'identity_key' => 'tax:245731680000',
            'contact_person' => 'Eduardo Ramos',
            'email' => 'procurement@bayanihan-hospital-supply.test',
            'phone' => '+63 2 8555 0146',
            'address' => 'Commonwealth Avenue, Quezon City, Metro Manila',
            'billing_address' => 'Commonwealth Avenue, Quezon City, Metro Manila',
            'delivery_address' => 'Hospital Central Supply Receiving Area',
            'standard_lead_time_days' => 5,
            'payment_terms' => 'Net 30 days after accepted delivery and complete billing documents.',
            'notes' => 'Cooperative supplier of certified hospital linens, protective apparel, and non-regulated ward consumables.',
            'contact' => [
                'name' => 'Eduardo Ramos',
                'position' => 'General Manager',
                'email' => 'e.ramos@bayanihan-hospital-supply.test',
                'phone' => '+63 2 8555 0146',
                'mobile' => '+63 918 555 0146',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-002',
                'contract_type' => 'Institutional Supply Agreement',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days after accepted delivery',
                'delivery_terms' => 'Direct store delivery to Hospital Central Supply Receiving Area',
            ],
        ],
        30001 => [
            'match_name' => 'Apex',
            'name' => 'Apex Medical Supplies Corp',
            'trade_name' => 'ApexMed Surgical Solutions',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => false,
            'tax_number' => '004-892-315-000',
            'identity_key' => 'tax:004892315000',
            'contact_person' => 'Arthur M. Villanueva',
            'email' => 'sales@apexmed.test',
            'phone' => '+63 2 8521 4401',
            'address' => '120 San Marcelino St, Ermita, Manila, 1000 Metro Manila',
            'billing_address' => '120 San Marcelino St, Ermita, Manila, 1000 Metro Manila',
            'delivery_address' => 'HIMS Central Warehouse Receiving Area',
            'standard_lead_time_days' => 7,
            'payment_terms' => 'Net 30 days after complete delivery and submission of sales invoice',
            'notes' => 'Primary contractor for disposable syringes, intravenous cannulas, and surgical wound care dressings.',
            'contact' => [
                'name' => 'Arthur M. Villanueva',
                'position' => 'Government Accounts Supervisor',
                'email' => 'a.villanueva@apexmed.test',
                'phone' => '+63 2 8521 4401',
                'mobile' => '+63 920 852 4401',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-003',
                'contract_type' => 'Master Consumables Agreement',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days after delivery',
                'delivery_terms' => 'Staggered scheduled deliveries to HIMS Central Warehouse',
            ],
        ],
        30002 => [
            'match_name' => 'Sterling',
            'name' => 'Sterling Healthcare Diagnostics Inc',
            'trade_name' => 'Sterling Diagnostics Philippines',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => true,
            'tax_number' => '007-654-129-000',
            'identity_key' => 'tax:007654129000',
            'contact_person' => 'Dr. Clarissa O. Banzon',
            'email' => 'tenders@sterlinghealth.test',
            'phone' => '+63 2 8812 9930',
            'address' => '45 Chino Roces Ave, Makati City, 1231 Metro Manila',
            'billing_address' => 'Finance Dept, 45 Chino Roces Ave, Makati City, 1231 Metro Manila',
            'delivery_address' => 'HIMS Clinical Laboratory & Central Supply Dock',
            'standard_lead_time_days' => 10,
            'payment_terms' => 'Net 30 days from inspection and testing acceptance',
            'notes' => 'Specialized distributor of clinical diagnostics, rapid test cartridges, and monitoring electrodes.',
            'contact' => [
                'name' => 'Dr. Clarissa O. Banzon',
                'position' => 'Clinical Diagnostics Director',
                'email' => 'c.banzon@sterlinghealth.test',
                'phone' => '+63 2 8812 9930',
                'mobile' => '+63 919 812 9930',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-004',
                'contract_type' => 'Laboratory & Diagnostic Supply Contract',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days from inspection pass',
                'delivery_terms' => 'Temperature-controlled delivery to Clinical Laboratory dock',
            ],
        ],
        30003 => [
            'match_name' => 'BioCare',
            'name' => 'BioCare Hospital Solutions Ltd',
            'trade_name' => 'BioCare Critical Care Solutions',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => true,
            'tax_number' => '009-341-872-000',
            'identity_key' => 'tax:009341872000',
            'contact_person' => 'Engr. Victor S. Laurel',
            'email' => 'institutional@biocare-solutions.test',
            'phone' => '+63 2 8633 1188',
            'address' => '88 E. Rodriguez Jr. Ave, Bagumbayan, Quezon City, 1110 Metro Manila',
            'billing_address' => '88 E. Rodriguez Jr. Ave, Bagumbayan, Quezon City, 1110 Metro Manila',
            'delivery_address' => 'HIMS Biomedical Engineering & Central Warehouse',
            'standard_lead_time_days' => 14,
            'payment_terms' => 'Net 45 days upon technical validation and commissioning',
            'notes' => 'Contractor for ICU ventilator breathing circuits, patient monitoring attachments, and clinical biomedical accessories.',
            'contact' => [
                'name' => 'Engr. Victor S. Laurel',
                'position' => 'Biomedical Support & Sales Lead',
                'email' => 'v.laurel@biocare-solutions.test',
                'phone' => '+63 2 8633 1188',
                'mobile' => '+63 917 633 1188',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-005',
                'contract_type' => 'Respiratory & Critical Care Equipment Contract',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 45 days upon technical validation',
                'delivery_terms' => 'White-glove delivery to Biomedical Engineering workshop',
            ],
        ],
        30004 => [
            'match_name' => 'Zuellig',
            'name' => 'Pan-Island Pharmaceuticals Distribution Corp.',
            'trade_name' => 'Pan-Island Healthcare Logistics',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => true,
            'tax_number' => '001-529-634-000',
            'identity_key' => 'tax:001529634000',
            'contact_person' => 'Roberto M. Dela Cruz',
            'email' => 'institutional.orders@panisland-pharma.test',
            'phone' => '+63 2 8822 5500',
            'address' => 'KM 14 West Service Road, Sun Valley, Parañaque City, 1700 Metro Manila',
            'billing_address' => 'KM 14 West Service Road, Sun Valley, Parañaque City, 1700 Metro Manila',
            'delivery_address' => 'HIMS Central Pharmacy Biological Cold Storage (2°C - 8°C)',
            'standard_lead_time_days' => 3,
            'payment_terms' => 'Net 30 days from temperature-verified receipt and cold-chain sign-off',
            'notes' => 'Licensed distributor of hospital vaccines, temperature-monitored biologicals, and IV fluids.',
            'contact' => [
                'name' => 'Roberto M. Dela Cruz',
                'position' => 'Hospital Key Accounts Executive',
                'email' => 'r.delacruz@panisland-pharma.test',
                'phone' => '+63 2 8822 5500',
                'mobile' => '+63 918 822 5500',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-006',
                'contract_type' => 'Vaccines & Temperature-Sensitive Therapeutics Agreement',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days from cold-chain verified receipt',
                'delivery_terms' => 'Continuous refrigerated cold-chain transit (2°C to 8°C)',
            ],
        ],
        30005 => [
            'match_name' => 'Metro Drug',
            'name' => 'Archipelago Health Drug Distribution Inc.',
            'trade_name' => 'Archipelago Pharma Network',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => true,
            'tax_number' => '002-748-910-000',
            'identity_key' => 'tax:002748910000',
            'contact_person' => 'Corazon V. Ramos',
            'email' => 'hospital.sales@archipelago-health.test',
            'phone' => '+63 49 541 2300',
            'address' => 'Commercial Center Boulevard, Don Jose, Santa Rosa, 4026 Laguna',
            'billing_address' => 'Finance & Credit Division, Sta. Rosa Commercial Complex, Santa Rosa, Laguna',
            'delivery_address' => 'HIMS Central Pharmacy Inpatient Dispensing Unit',
            'standard_lead_time_days' => 4,
            'payment_terms' => 'Net 30 days upon receipt of accredited batch release certificates',
            'notes' => 'Authorized supplier of broad-spectrum hospital antibiotics, parenteral infusions, and emergency medications.',
            'contact' => [
                'name' => 'Corazon V. Ramos',
                'position' => 'Regional Hospital Supply Specialist',
                'email' => 'c.ramos@archipelago-health.test',
                'phone' => '+63 49 541 2300',
                'mobile' => '+63 922 541 2300',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-007',
                'contract_type' => 'Essential Medicines & Antibiotics Formulary Contract',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days upon batch release verification',
                'delivery_terms' => 'Dedicated pharmaceutical van delivery to Pharmacy receiving bay',
            ],
        ],
        60001 => [
            'match_name' => 'Andres',
            'name' => 'St. Jude Biomedical & Surgical Systems Corp.',
            'trade_name' => 'St. Jude Surgical Phils',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => true,
            'tax_number' => '005-612-409-000',
            'identity_key' => 'tax:005612409000',
            'contact_person' => 'Gerardo P. Alcantara',
            'email' => 'surgical.orders@stjude-biomedical.test',
            'phone' => '+63 2 8524 8810',
            'address' => '72 San Andres Street, Malate, Manila, 1004 Metro Manila',
            'billing_address' => '72 San Andres Street, Malate, Manila, 1004 Metro Manila',
            'delivery_address' => 'HIMS Operating Room Sterile Storage & Consignment Desk',
            'standard_lead_time_days' => 4,
            'payment_terms' => 'Net 30 days upon surgical utilization report and batch verification',
            'notes' => 'Accredited distributor of surgical titanium plates, osteosynthesis fixation sets, and specialized surgical sutures.',
            'contact' => [
                'name' => 'Gerardo P. Alcantara',
                'position' => 'Operating Theater Consignment Specialist',
                'email' => 'g.alcantara@stjude-biomedical.test',
                'phone' => '+63 2 8524 8810',
                'mobile' => '+63 917 524 8810',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-008',
                'contract_type' => 'Surgical Implants Consignment & Supply Agreement',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days after surgical consumption audit',
                'delivery_terms' => 'Direct to Operating Room sterile consignment cabinet',
            ],
        ],
    ];

    /**
     * Additional realistic hospital suppliers to ensure a complete vendor ecosystem.
     */
    private const ADDITIONAL_SUPPLIERS = [
        [
            'name' => 'Luzon Lifescience Laboratories Inc.',
            'trade_name' => 'Luzon Life Diagnostics',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => true,
            'tax_number' => '006-193-845-000',
            'identity_key' => 'tax:006193845000',
            'contact_person' => 'Dra. Patricia Nicole Gomez',
            'email' => 'hospital-accounts@luzon-lifescience.test',
            'phone' => '+63 2 8631 7720',
            'address' => '15 Pioneer Street, Highway Hills, Mandaluyong City, 1550 Metro Manila',
            'billing_address' => '15 Pioneer Street, Mandaluyong City, 1550 Metro Manila',
            'delivery_address' => 'HIMS Pathology Laboratory & Central Supply Dock',
            'standard_lead_time_days' => 5,
            'payment_terms' => 'Net 30 days upon quality inspection pass',
            'notes' => 'Accredited laboratory diagnostic supplier for clinical pathology, blood banking, and point-of-care test strips.',
            'contact' => [
                'name' => 'Dra. Patricia Nicole Gomez',
                'position' => 'Diagnostic Account Director',
                'email' => 'p.gomez@luzon-lifescience.test',
                'phone' => '+63 2 8631 7720',
                'mobile' => '+63 917 631 7720',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-009',
                'contract_type' => 'Pathology & Blood Bank Reagents Agreement',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days after laboratory acceptance',
                'delivery_terms' => 'Direct delivery to Diagnostic Pathology Laboratory (CC-LAB)',
            ],
        ],
        [
            'name' => 'Archipelago Renal & Dialysis Supplies Corp.',
            'trade_name' => 'Archipelago Dialysis Care',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => true,
            'tax_number' => '008-472-901-000',
            'identity_key' => 'tax:008472901000',
            'contact_person' => 'Ramon Carlo Mendoza',
            'email' => 'procurement@archipelago-dialysis.test',
            'phone' => '+63 46 430 8910',
            'address' => 'Southwoods Industrial Estate, Carmona, 4116 Cavite',
            'billing_address' => 'Southwoods Industrial Estate, Carmona, 4116 Cavite',
            'delivery_address' => 'HIMS Hemodialysis Unit & Central Medical Warehouse',
            'standard_lead_time_days' => 6,
            'payment_terms' => 'Net 30 days from verified delivery',
            'notes' => 'Hospital supplier for hemodialysis consumables, sterile peritoneal solutions, and antiseptic preparations.',
            'contact' => [
                'name' => 'Ramon Carlo Mendoza',
                'position' => 'Institutional Logistics Head',
                'email' => 'r.mendoza@archipelago-dialysis.test',
                'phone' => '+63 46 430 8910',
                'mobile' => '+63 918 430 8910',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-010',
                'contract_type' => 'Dialysis & Infusion Supply Contract',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days from verified receipt',
                'delivery_terms' => 'Palletized delivery to Central Warehouse Bay 2',
            ],
        ],
        [
            'name' => 'Vitalis Respiratory & Anesthesia Systems Inc.',
            'trade_name' => 'Vitalis Medical Phils',
            'business_structure' => 'corporation',
            'provides_regulated_health_products' => true,
            'tax_number' => '003-820-114-000',
            'identity_key' => 'tax:003820114000',
            'contact_person' => 'Ma. Elena Concepcion',
            'email' => 'hospital.bids@vitalis-respiratory.test',
            'phone' => '+63 2 8655 4230',
            'address' => '32 Ortigas Avenue Extension, Rosario, Pasig City, 1609 Metro Manila',
            'billing_address' => '32 Ortigas Avenue Extension, Pasig City, 1609 Metro Manila',
            'delivery_address' => 'HIMS Operating Room Anesthesia & Respiratory Storage',
            'standard_lead_time_days' => 8,
            'payment_terms' => 'Net 30 days upon technical inspection',
            'notes' => 'Provider of high-performance anesthesia circuits, endotracheal accessories, and oxygen delivery hardware.',
            'contact' => [
                'name' => 'Ma. Elena Concepcion',
                'position' => 'Critical Care Product Manager',
                'email' => 'e.concepcion@vitalis-respiratory.test',
                'phone' => '+63 2 8655 4230',
                'mobile' => '+63 920 655 4230',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-011',
                'contract_type' => 'Airway Management & Oxygenation Agreement',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days upon bio-inspection',
                'delivery_terms' => 'Direct delivery to Operating Room Anesthesia storage',
            ],
        ],
        [
            'name' => 'SafeShield Hygiene & Infection Control Co.',
            'trade_name' => 'SafeShield Healthcare',
            'business_structure' => 'partnership',
            'provides_regulated_health_products' => false,
            'tax_number' => '005-391-768-000',
            'identity_key' => 'tax:005391768000',
            'contact_person' => 'Felipe S. Tan',
            'email' => 'sales@safeshield-hygiene.test',
            'phone' => '+63 2 8681 9045',
            'address' => 'Industrial Valley Complex, Marcos Highway, Marikina City, 1800 Metro Manila',
            'billing_address' => 'Industrial Valley Complex, Marcos Highway, Marikina City, 1800 Metro Manila',
            'delivery_address' => 'HIMS Central Supply Decontamination & Storage Facility',
            'standard_lead_time_days' => 4,
            'payment_terms' => 'Net 30 days upon invoice receipt',
            'notes' => 'Contractor for surgical hand scrubs, high-level hospital surface disinfectants, and environmental hygiene.',
            'contact' => [
                'name' => 'Felipe S. Tan',
                'position' => 'Institutional Sales Partner',
                'email' => 'f.tan@safeshield-hygiene.test',
                'phone' => '+63 2 8681 9045',
                'mobile' => '+63 919 681 9045',
            ],
            'contract' => [
                'contract_number' => 'HIMS-CNT-2026-012',
                'contract_type' => 'Hospital Sanitation & Infection Barrier Agreement',
                'starts_at' => '2026-01-01',
                'ends_at' => '2027-12-31',
                'payment_terms' => 'Net 30 days upon delivery',
                'delivery_terms' => 'Direct to Central Supply Decontamination facility',
            ],
        ],
    ];

    /**
     * Master specifications for all items (categories, default locations, barcodes, and primary supplier keys).
     */
    private const ITEM_CATALOG_COMPLETIONS = [
        'PPE-MASK-N95' => [
            'category_code' => 'PPE',
            'location_code' => 'WH-01-A',
            'supplier_key' => 'MedSupply Healthcare Corporation',
            'barcode' => '4800000000018',
            'gtin' => '04800000000018',
        ],
        'PHARMA-PARA-500' => [
            'category_code' => 'PHARMA',
            'location_code' => 'PHARM-01',
            'supplier_key' => 'MedSupply Healthcare Corporation',
            'barcode' => '4800000000025',
            'gtin' => '04800000000025',
        ],
        'PPE-GLOVE-L' => [
            'category_code' => 'PPE',
            'location_code' => 'WH-01-A',
            'supplier_key' => 'MedSupply Healthcare Corporation',
            'barcode' => '4800000000032',
            'gtin' => '04800000000032',
        ],
        'FCAST-MASK-3PLY' => [
            'category_code' => 'PPE',
            'location_code' => 'WH-01',
            'supplier_key' => 'Bayanihan Community Hospital Supply Cooperative',
            'barcode' => '4800000000049',
            'gtin' => '04800000000049',
        ],
        'FCAST-GLOVE-M' => [
            'category_code' => 'PPE',
            'location_code' => 'WH-01',
            'supplier_key' => 'Bayanihan Community Hospital Supply Cooperative',
            'barcode' => '4800000000056',
            'gtin' => '04800000000056',
        ],
        'FCAST-SYRINGE-5ML' => [
            'category_code' => 'MED-CONS',
            'location_code' => 'WH-01',
            'supplier_key' => 'Apex Medical Supplies Corp',
            'barcode' => '4800000000063',
            'gtin' => '04800000000063',
        ],
        'FCAST-IVC-22G' => [
            'category_code' => 'MED-CONS',
            'location_code' => 'WH-01',
            'supplier_key' => 'Apex Medical Supplies Corp',
            'barcode' => '4800000000070',
            'gtin' => '04800000000070',
        ],
        'FCAST-GAUZE-4X4' => [
            'category_code' => 'MED-CONS',
            'location_code' => 'WH-01',
            'supplier_key' => 'Apex Medical Supplies Corp',
            'barcode' => '4800000000087',
            'gtin' => '04800000000087',
        ],
        'FCAST-SALINE-1L' => [
            'category_code' => 'PHARMA',
            'location_code' => 'WH-01',
            'supplier_key' => 'Pan-Island Pharmaceuticals Distribution Corp.',
            'barcode' => '4800000000094',
            'gtin' => '04800000000094',
        ],
        'FCAST-CEFTRI-1G' => [
            'category_code' => 'PHARMA',
            'location_code' => 'WH-01',
            'supplier_key' => 'Archipelago Health Drug Distribution Inc.',
            'barcode' => '4800000000100',
            'gtin' => '04800000000100',
        ],
        'FCAST-ALCOHOL-500' => [
            'category_code' => 'PHARMA',
            'location_code' => 'WH-01',
            'supplier_key' => 'Archipelago Renal & Dialysis Supplies Corp.',
            'barcode' => '4800000000117',
            'gtin' => '04800000000117',
        ],
        'FCAST-ECG-ELECTRODE' => [
            'category_code' => 'DIAG-SUP',
            'location_code' => 'WH-01',
            'supplier_key' => 'Sterling Healthcare Diagnostics Inc',
            'barcode' => '4800000000124',
            'gtin' => '04800000000124',
        ],
        'FCAST-GLUCOSE-STRIP' => [
            'category_code' => 'DIAG-SUP',
            'location_code' => 'WH-01',
            'supplier_key' => 'Sterling Healthcare Diagnostics Inc',
            'barcode' => '4800000000131',
            'gtin' => '04800000000131',
        ],
        'FCAST-CATHETER-16FR' => [
            'category_code' => 'MED-CONS',
            'location_code' => 'WH-01',
            'supplier_key' => 'Apex Medical Supplies Corp',
            'barcode' => '4800000000148',
            'gtin' => '04800000000148',
        ],
        'FCAST-SUTURE-3-0' => [
            'category_code' => 'MED-CONS',
            'location_code' => 'WH-01',
            'supplier_key' => 'St. Jude Biomedical & Surgical Systems Corp.',
            'barcode' => '4800000000155',
            'gtin' => '04800000000155',
        ],
        'MED-VENT-01' => [
            'category_code' => 'MED-CONS',
            'location_code' => 'WH-01',
            'supplier_key' => 'BioCare Hospital Solutions Ltd',
            'barcode' => '4800000000162',
            'gtin' => '04800000000162',
        ],
        'MED-GAUZE-ST' => [
            'category_code' => 'MED-CONS',
            'location_code' => 'WH-01',
            'supplier_key' => 'Apex Medical Supplies Corp',
            'barcode' => '4800000000179',
            'gtin' => '04800000000179',
        ],
        'MED-TITAN-PL' => [
            'category_code' => 'MED',
            'location_code' => 'WH-01',
            'supplier_key' => 'St. Jude Biomedical & Surgical Systems Corp.',
            'barcode' => '4800000000186',
            'gtin' => '04800000000186',
        ],
        'MED-INF-SET' => [
            'category_code' => 'MED-CONS',
            'location_code' => 'WH-01',
            'supplier_key' => 'Sterling Healthcare Diagnostics Inc',
            'barcode' => '4800000000193',
            'gtin' => '04800000000193',
        ],
        'SWS-DEMO-SYRINGE-5ML' => [
            'category_code' => 'SWS-DEMO-MED',
            'location_code' => 'SWS-DEMO-PICK',
            'supplier_key' => 'Apex Medical Supplies Corp',
            'barcode' => '4800000000209',
            'gtin' => '04800000000209',
        ],
        'DRG-MORS-002' => [
            'category_code' => 'SWS-DEMO-PHARMA',
            'location_code' => 'W1-Z3-VAULT-S01-B01',
            'supplier_key' => 'Vitalis Respiratory & Anesthesia Systems Inc.',
            'barcode' => '4800000000216',
            'gtin' => '04800000000216',
        ],
        'DRG-RABV-003' => [
            'category_code' => 'SWS-DEMO-PHARMA',
            'location_code' => 'W1-Z2-COLD-R01-B01',
            'supplier_key' => 'Pan-Island Pharmaceuticals Distribution Corp.',
            'barcode' => '4800000000223',
            'gtin' => '04800000000223',
        ],
        'DRG-DOBU-004' => [
            'category_code' => 'SWS-DEMO-PHARMA',
            'location_code' => 'W1-Z1-A01-R01-B01',
            'supplier_key' => 'Archipelago Health Drug Distribution Inc.',
            'barcode' => '4800000000230',
            'gtin' => '04800000000230',
        ],
        'DRG-DOPA-005' => [
            'category_code' => 'SWS-DEMO-PHARMA',
            'location_code' => 'W1-Z1-A01-R01-B01',
            'supplier_key' => 'Archipelago Health Drug Distribution Inc.',
            'barcode' => '4800000000247',
            'gtin' => '04800000000247',
        ],
        'MED-STNT-006' => [
            'category_code' => 'SWS-DEMO-SURG',
            'location_code' => 'W1-OR-CONS-R01-B01',
            'supplier_key' => 'St. Jude Biomedical & Surgical Systems Corp.',
            'barcode' => '4800000000254',
            'gtin' => '04800000000254',
        ],
        'VAC-RAB-VER05' => [
            'category_code' => 'PHARMA',
            'location_code' => 'COLD-01-A',
            'supplier_key' => 'Pan-Island Pharmaceuticals Distribution Corp.',
            'barcode' => '4800000000261',
            'gtin' => '04800000000261',
        ],
        'ANT-MER-1G00' => [
            'category_code' => 'PHARMA',
            'location_code' => 'MAIN-A1-01',
            'supplier_key' => 'Archipelago Health Drug Distribution Inc.',
            'barcode' => '4800000000278',
            'gtin' => '04800000000278',
        ],
        'PPE-GOWN-XL' => [
            'category_code' => 'PPE',
            'location_code' => 'WH-01',
            'supplier_key' => 'Bayanihan Community Hospital Supply Cooperative',
            'barcode' => '4800000000285',
            'gtin' => '04800000000285',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $audit = app(AuditLogger::class);
            $adminUser = User::query()->where('role', 'super_administrator')->first()
                ?? User::query()->first();

            // 1. Process and enrich existing suppliers
            $supplierMap = [];
            foreach (self::SUPPLIERS as $knownId => $def) {
                $supplier = Supplier::query()->find($knownId)
                    ?? Supplier::query()->where('name', 'LIKE', '%'.$def['match_name'].'%')->first();

                if (! $supplier) {
                    $supplier = new Supplier();
                }

                $wasNew = ! $supplier->exists;

                $supplier->name = $def['name'];
                $supplier->trade_name = $def['trade_name'];
                $supplier->business_structure = $def['business_structure'];
                $supplier->provides_regulated_health_products = $def['provides_regulated_health_products'];
                $supplier->tax_number = $def['tax_number'];
                $supplier->identity_key = $def['identity_key'];
                $supplier->contact_person = $def['contact_person'];
                $supplier->email = $def['email'];
                $supplier->phone = $def['phone'];
                $supplier->address = $def['address'];
                $supplier->billing_address = $def['billing_address'];
                $supplier->delivery_address = $def['delivery_address'];
                $supplier->standard_lead_time_days = $def['standard_lead_time_days'];
                $supplier->payment_terms = $def['payment_terms'];
                $supplier->notes = $def['notes'];
                $supplier->status = SupplierStatus::Active;
                $supplier->accreditation_status = SupplierAccreditationStatus::Approved;
                $supplier->accreditation_expires_at = '2027-09-30';
                $supplier->approved_by = $adminUser?->id;
                $supplier->last_reviewed_at = now();
                $supplier->save();

                $supplierMap[$def['name']] = $supplier;

                // Sync Primary Contact
                $this->syncSupplierContact($supplier, $def['contact']);

                // Sync Master Contract
                $this->syncSupplierContract($supplier, $def['contract'], $adminUser?->id);

                // Sync Accreditation Record
                $this->syncSupplierAccreditation($supplier, $adminUser?->id);

                if ($wasNew) {
                    $audit->record(
                        AuditAction::CreatedSupplier,
                        target: $supplier,
                        targetName: $supplier->name,
                        description: 'Created realistic hospital supplier record.',
                        newValues: ['name' => $supplier->name, 'status' => 'active'],
                    );
                }
            }

            // 2. Add additional fictional hospital suppliers
            foreach (self::ADDITIONAL_SUPPLIERS as $def) {
                $supplier = Supplier::query()->where('name', $def['name'])
                    ->orWhere('tax_number', $def['tax_number'])
                    ->first();

                if (! $supplier) {
                    $supplier = new Supplier();
                }

                $wasNew = ! $supplier->exists;

                $supplier->name = $def['name'];
                $supplier->trade_name = $def['trade_name'];
                $supplier->business_structure = $def['business_structure'];
                $supplier->provides_regulated_health_products = $def['provides_regulated_health_products'];
                $supplier->tax_number = $def['tax_number'];
                $supplier->identity_key = $def['identity_key'];
                $supplier->contact_person = $def['contact_person'];
                $supplier->email = $def['email'];
                $supplier->phone = $def['phone'];
                $supplier->address = $def['address'];
                $supplier->billing_address = $def['billing_address'];
                $supplier->delivery_address = $def['delivery_address'];
                $supplier->standard_lead_time_days = $def['standard_lead_time_days'];
                $supplier->payment_terms = $def['payment_terms'];
                $supplier->notes = $def['notes'];
                $supplier->status = SupplierStatus::Active;
                $supplier->accreditation_status = SupplierAccreditationStatus::Approved;
                $supplier->accreditation_expires_at = '2027-09-30';
                $supplier->approved_by = $adminUser?->id;
                $supplier->last_reviewed_at = now();
                $supplier->save();

                $supplierMap[$def['name']] = $supplier;

                $this->syncSupplierContact($supplier, $def['contact']);
                $this->syncSupplierContract($supplier, $def['contract'], $adminUser?->id);
                $this->syncSupplierAccreditation($supplier, $adminUser?->id);

                if ($wasNew) {
                    $audit->record(
                        AuditAction::CreatedSupplier,
                        target: $supplier,
                        targetName: $supplier->name,
                        description: 'Created realistic specialty hospital vendor.',
                        newValues: ['name' => $supplier->name, 'status' => 'active'],
                    );
                }
            }

            // 3. Complete Inventory Master Data (categories, default locations, barcodes, supplier links)
            $categories = ItemCategory::query()->get()->keyBy('code');
            $locations = StorageLocation::query()->get()->keyBy('code');

            foreach (self::ITEM_CATALOG_COMPLETIONS as $sku => $meta) {
                $item = InventoryItem::query()->where('sku', $sku)->first();
                if (! $item) {
                    continue;
                }

                $category = $categories->get($meta['category_code']);
                $location = $locations->get($meta['location_code']);
                $supplier = $supplierMap[$meta['supplier_key']] ?? Supplier::query()->where('name', $meta['supplier_key'])->first();

                if ($category && ($item->category_id === null || $item->category_id !== $category->id)) {
                    $item->category_id = $category->id;
                }

                if ($location && ($item->default_location_id === null || $item->default_location_id !== $location->id)) {
                    $item->default_location_id = $location->id;
                }

                if ($supplier && ($item->supplier_id === null || $item->supplier_id !== $supplier->id)) {
                    $item->supplier_id = $supplier->id;
                }

                if (empty($item->barcode_value) || empty($item->gtin)) {
                    $item->barcode_value = $meta['barcode'];
                    $item->gtin = $meta['gtin'];
                }

                $item->save();

                // 4. Ensure supplier_products and supplier_prices exist
                if ($supplier) {
                    $product = SupplierProduct::query()->firstOrCreate(
                        [
                            'supplier_id' => $supplier->id,
                            'item_id' => $item->id,
                        ],
                        [
                            'supplier_sku' => $item->sku,
                            'supplier_product_name' => $item->name,
                            'unit' => $item->unit,
                            'minimum_order_quantity' => 1,
                            'lead_time_days' => $supplier->standard_lead_time_days ?? 5,
                            'is_preferred' => true,
                            'is_active' => true,
                        ]
                    );

                    $contract = $supplier->contracts()->where('status', 'active')->latest()->first();
                    $unitPrice = (float) ($item->unit_cost ?? 0);
                    if ($unitPrice <= 0) {
                        $unitPrice = 120.00;
                    }

                    $existingPrice = SupplierPrice::query()->where('supplier_product_id', $product->id)->first();
                    if (! $existingPrice) {
                        SupplierPrice::query()->create([
                            'supplier_product_id' => $product->id,
                            'supplier_contract_id' => $contract?->id,
                            'unit_price' => $unitPrice,
                            'currency' => 'PHP',
                            'minimum_order_quantity' => 1,
                            'effective_from' => '2026-01-01',
                            'effective_until' => '2027-12-31',
                            'notes' => 'Contracted hospital formulary catalog pricing.',
                            'created_by' => $adminUser?->id,
                        ]);
                    }
                }
            }

            // 5. Stock Level Reconciliation
            // Ensure items with quantity_on_hand have an ItemStockLevel row
            $itemsWithStock = InventoryItem::query()->where('quantity_on_hand', '>', 0)->get();
            foreach ($itemsWithStock as $item) {
                $locationId = $item->default_location_id ?? 1; // Default to WH-01
                $stockSum = (int) $item->stockLevels()->sum('quantity');

                if ($stockSum === 0 && (int) $item->quantity_on_hand > 0) {
                    ItemStockLevel::query()->firstOrCreate(
                        [
                            'item_id' => $item->id,
                            'storage_location_id' => $locationId,
                            'item_batch_id' => null,
                        ],
                        [
                            'quantity' => (int) $item->quantity_on_hand,
                            'reserved_quantity' => 0,
                            'quarantined_quantity' => 0,
                            'blocked_quantity' => 0,
                            'in_transit_quantity' => 0,
                        ]
                    );
                }
            }

            // Clear stale unbacked reserved quantities where no active requisitions exist
            $activeReservedByItem = DB::table('material_requisition_lines')
                ->join('material_requisitions', 'material_requisitions.id', '=', 'material_requisition_lines.material_requisition_id')
                ->whereIn('material_requisitions.status', ['pending_approval', 'approved', 'partially_issued'])
                ->groupBy('material_requisition_lines.item_id')
                ->pluck(DB::raw('SUM(material_requisition_lines.reserved_quantity) as total'), 'material_requisition_lines.item_id')
                ->all();

            foreach (InventoryItem::query()->get() as $item) {
                $expectedReserved = (int) ($activeReservedByItem[$item->id] ?? 0);
                if ($expectedReserved === 0) {
                    ItemStockLevel::query()->where('item_id', $item->id)->update(['reserved_quantity' => 0]);
                }

                $totalQty = (int) $item->stockLevels()->sum('quantity');
                $totalRes = (int) $item->stockLevels()->sum('reserved_quantity');

                $item->quantity_on_hand = $totalQty;
                $item->reserved_quantity = $totalRes;
                $item->total_value = round($totalQty * (float) ($item->unit_cost ?? 0), 2);
                $item->save();
            }

            // 6. Fix PO Line Item 3 received_quantity anomaly
            $poLine3 = PurchaseOrderLine::query()->find(3);
            if ($poLine3 && (int) $poLine3->received_quantity === 1000 && (int) $poLine3->ordered_quantity === 500) {
                $poLine3->received_quantity = 500;
                $poLine3->save();
            }
        });
    }

    private function syncSupplierContact(Supplier $supplier, array $contact): void
    {
        SupplierContact::query()->updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'name' => $contact['name'],
            ],
            [
                'contact_type' => 'primary',
                'position' => $contact['position'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'mobile' => $contact['mobile'],
                'is_primary' => true,
                'is_active' => true,
            ]
        );
    }

    private function syncSupplierContract(Supplier $supplier, array $contract, ?int $responsibleUserId): void
    {
        SupplierContract::query()->updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'contract_number' => $contract['contract_number'],
            ],
            [
                'contract_type' => $contract['contract_type'],
                'starts_at' => $contract['starts_at'],
                'ends_at' => $contract['ends_at'],
                'status' => 'active',
                'payment_terms' => $contract['payment_terms'],
                'delivery_terms' => $contract['delivery_terms'],
                'responsible_user_id' => $responsibleUserId,
                'notes' => 'Active procurement agreement verified by hospital administration.',
            ]
        );
    }

    private function syncSupplierAccreditation(Supplier $supplier, ?int $decidedById): void
    {
        SupplierAccreditation::query()->updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'cycle_number' => 1,
            ],
            [
                'status' => 'approved',
                'submitted_by' => $decidedById,
                'submitted_at' => '2026-01-01 08:00:00',
                'decided_by' => $decidedById,
                'decided_at' => '2026-01-02 09:30:00',
                'valid_from' => '2026-01-02',
                'expires_at' => '2027-09-30',
                'decision_notes' => 'Accreditation approved for institutional hospital procurement.',
            ]
        );
    }
}
