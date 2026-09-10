<?php

namespace Database\Seeders;

use App\Models\DpriReferencePrice;
use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class ProcessReviewDemoSeeder extends Seeder
{
    public function run(): void
    {
        $dpriPrices = [
            [
                'pndf_code' => 'PNDF-AMOX-500',
                'drug_name' => 'Amoxicillin',
                'dosage_form_strength' => '500 mg capsule',
                'unit_of_measure' => 'capsule',
                'ceiling_price' => 3.5000,
                'edition_year' => 2026,
                'notes' => 'DOH DPRI 11th Edition (2026), Section 6: Anti-infectives.',
            ],
            [
                'pndf_code' => 'PNDF-PARA-500',
                'drug_name' => 'Paracetamol',
                'dosage_form_strength' => '500 mg tablet',
                'unit_of_measure' => 'tablet',
                'ceiling_price' => 1.2000,
                'edition_year' => 2026,
                'notes' => 'DOH DPRI 11th Edition (2026), Section 2: Analgesics.',
            ],
            [
                'pndf_code' => 'PNDF-CEFU-500',
                'drug_name' => 'Cefuroxime',
                'dosage_form_strength' => '500 mg tablet',
                'unit_of_measure' => 'tablet',
                'ceiling_price' => 18.5000,
                'edition_year' => 2026,
                'notes' => 'DOH DPRI 11th Edition (2026), Second-generation Cephalosporin.',
            ],
            [
                'pndf_code' => 'PNDF-NACL-09-1L',
                'drug_name' => '0.9% Sodium Chloride (Normal Saline)',
                'dosage_form_strength' => '1000 mL IV infusion bottle',
                'unit_of_measure' => 'bottle',
                'ceiling_price' => 45.0000,
                'edition_year' => 2026,
                'notes' => 'DOH DPRI 11th Edition (2026), Intravenous Solutions.',
            ],
            [
                'pndf_code' => 'PNDF-AZIT-500',
                'drug_name' => 'Azithromycin',
                'dosage_form_strength' => '500 mg tablet',
                'unit_of_measure' => 'tablet',
                'ceiling_price' => 24.0000,
                'edition_year' => 2026,
                'notes' => 'DOH DPRI 11th Edition (2026), Macrolide antibiotic.',
            ],
            [
                'pndf_code' => 'PNDF-INS-7030',
                'drug_name' => 'Biphasic Isophane Insulin (70/30)',
                'dosage_form_strength' => '100 IU/mL, 10 mL vial',
                'unit_of_measure' => 'vial',
                'ceiling_price' => 280.0000,
                'edition_year' => 2026,
                'notes' => 'Cold chain (2C-8C) refrigerated biological, DOH DPRI 2026.',
            ],
        ];

        foreach ($dpriPrices as $data) {
            DpriReferencePrice::updateOrCreate(
                ['pndf_code' => $data['pndf_code'], 'edition_year' => $data['edition_year']],
                $data
            );
        }

        // Link existing InventoryItems by generic/name matching
        $items = InventoryItem::whereNull('pndf_code')->get();
        foreach ($items as $item) {
            if (str_contains(strtolower($item->name), 'amoxicillin') || str_contains(strtolower($item->generic_name ?? ''), 'amoxicillin')) {
                $item->update(['pndf_code' => 'PNDF-AMOX-500']);
            } elseif (str_contains(strtolower($item->name), 'paracetamol') || str_contains(strtolower($item->generic_name ?? ''), 'paracetamol')) {
                $item->update(['pndf_code' => 'PNDF-PARA-500']);
            } elseif (str_contains(strtolower($item->name), 'cefuroxime') || str_contains(strtolower($item->generic_name ?? ''), 'cefuroxime')) {
                $item->update(['pndf_code' => 'PNDF-CEFU-500']);
            } elseif (str_contains(strtolower($item->name), 'saline') || str_contains(strtolower($item->generic_name ?? ''), 'saline')) {
                $item->update(['pndf_code' => 'PNDF-NACL-09-1L']);
            } elseif (str_contains(strtolower($item->name), 'insulin') || str_contains(strtolower($item->generic_name ?? ''), 'insulin')) {
                $item->update(['pndf_code' => 'PNDF-INS-7030']);
            }
        }
    }
}
