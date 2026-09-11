<?php

namespace App\Services\Import;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ImportTemplateGenerator
{
    /**
     * Define template schemas and sample rows.
     *
     * @return array{headers: array<string>, sample_rows: array<int, array<string, mixed>>}
     */
    public function getTemplateData(string $target): array
    {
        return match ($target) {
            'items' => [
                'headers' => [
                    'sku',
                    'name',
                    'description',
                    'category',
                    'unit',
                    'unit_cost',
                    'reorder_level',
                    'safety_stock',
                    'critical_level',
                    'expiry_alert_days',
                    'default_location',
                    'requires_cold_chain',
                    'is_dangerous_drug',
                ],
                'sample_rows' => [
                    [
                        'sku' => 'MED-PARA-500',
                        'name' => 'Paracetamol 500mg Tablet',
                        'description' => 'Analgesic and antipyretic oral tablet',
                        'category' => 'Medicines & Pharmaceuticals',
                        'unit' => 'tablet',
                        'unit_cost' => '1.50',
                        'reorder_level' => '500',
                        'safety_stock' => '200',
                        'critical_level' => '100',
                        'expiry_alert_days' => '30',
                        'default_location' => 'Main Store',
                        'requires_cold_chain' => 'no',
                        'is_dangerous_drug' => 'no',
                    ],
                    [
                        'sku' => 'BIO-RAB-01',
                        'name' => 'Rabies Vaccine 2.5 IU / mL',
                        'description' => 'Purified Vero cell rabies biological vaccine',
                        'category' => 'Vaccines & Biologicals',
                        'unit' => 'vial',
                        'unit_cost' => '850.00',
                        'reorder_level' => '50',
                        'safety_stock' => '20',
                        'critical_level' => '10',
                        'expiry_alert_days' => '60',
                        'default_location' => 'Cold Chain Storage',
                        'requires_cold_chain' => 'yes',
                        'is_dangerous_drug' => 'no',
                    ],
                ],
            ],
            'locations' => [
                'headers' => [
                    'code',
                    'name',
                    'type',
                    'zone',
                    'capacity',
                    'storage_classification',
                    'temperature_classification',
                    'status',
                ],
                'sample_rows' => [
                    [
                        'code' => 'MAIN-ZONE-A',
                        'name' => 'Main Warehouse Zone A - Fast Movers',
                        'type' => 'warehouse',
                        'zone' => 'Zone A',
                        'capacity' => '5000',
                        'storage_classification' => 'ambient',
                        'temperature_classification' => 'ambient',
                        'status' => 'active',
                    ],
                    [
                        'code' => 'COLD-VAULT-01',
                        'name' => 'Pharmacy Cold Vault Room 1 (2°C - 8°C)',
                        'type' => 'cold_vault',
                        'zone' => 'Cold Chain',
                        'capacity' => '1200',
                        'storage_classification' => 'cold_chain',
                        'temperature_classification' => 'refrigerated',
                        'status' => 'active',
                    ],
                ],
            ],
            'suppliers' => [
                'headers' => [
                    'name',
                    'contact_person',
                    'email',
                    'phone',
                    'address',
                    'tax_number',
                    'payment_terms',
                    'standard_lead_time_days',
                ],
                'sample_rows' => [
                    [
                        'name' => 'Zuellig Pharma Corporation',
                        'contact_person' => 'Maria Santos (Account Exec)',
                        'email' => 'hospital.sales@zuelligpharma.com',
                        'phone' => '+63 2 8900 1234',
                        'address' => 'Km 14 West Service Rd, Taguig, Metro Manila',
                        'tax_number' => '000-123-456-000',
                        'payment_terms' => '30 Days Net',
                        'standard_lead_time_days' => '7',
                    ],
                    [
                        'name' => 'Metro Drug Distribution Inc.',
                        'contact_person' => 'Juan Dela Cruz',
                        'email' => 'orders@metrodrug.com.ph',
                        'phone' => '+63 2 8845 6789',
                        'address' => 'Mañalac Ave, Bagumbayan, Taguig City',
                        'tax_number' => '001-987-654-000',
                        'payment_terms' => '45 Days Net',
                        'standard_lead_time_days' => '5',
                    ],
                ],
            ],
            default => throw new \InvalidArgumentException("Unknown target [{$target}]."),
        };
    }

    /**
     * Download CSV template.
     */
    public function downloadCsv(string $target): StreamedResponse
    {
        $data = $this->getTemplateData($target);
        $filename = "hims-{$target}-template.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, $data['headers']);
            foreach ($data['sample_rows'] as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download JSON template.
     */
    public function downloadJson(string $target): StreamedResponse
    {
        $data = $this->getTemplateData($target);
        $filename = "hims-{$target}-template.json";

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data['sample_rows'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download Excel (.xlsx) template generated using native ZipArchive and OpenXML.
     */
    public function downloadXlsx(string $target): StreamedResponse
    {
        $data = $this->getTemplateData($target);
        $filename = "hims-{$target}-template.xlsx";

        return response()->streamDownload(function () use ($data) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_tpl_');
            $zip = new ZipArchive();
            if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // [Content_Types].xml
                $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
                    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
                    <Default Extension="xml" ContentType="application/xml"/>
                    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
                    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
                    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
                </Types>');

                // _rels/.rels
                $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/package/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
                </Relationships>');

                // xl/_rels/workbook.xml.rels
                $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/spreadsheetml/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
                    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/spreadsheetml/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
                </Relationships>');

                // xl/workbook.xml
                $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                    <sheets>
                        <sheet name="Template" sheetId="1" r:id="rId1"/>
                    </sheets>
                </workbook>');

                // Collect all unique strings for sharedStrings.xml
                $allStrings = [];
                $stringMap = [];
                $getStringIdx = function ($str) use (&$allStrings, &$stringMap) {
                    $str = (string) $str;
                    if (! isset($stringMap[$str])) {
                        $stringMap[$str] = count($allStrings);
                        $allStrings[] = $str;
                    }

                    return $stringMap[$str];
                };

                // Index headers
                foreach ($data['headers'] as $header) {
                    $getStringIdx($header);
                }
                // Index sample rows
                foreach ($data['sample_rows'] as $row) {
                    foreach ($row as $val) {
                        $getStringIdx($val);
                    }
                }

                // Build xl/sharedStrings.xml
                $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($allStrings).'" uniqueCount="'.count($allStrings).'">';
                foreach ($allStrings as $s) {
                    $sstXml .= '<si><t>'.htmlspecialchars($s, ENT_XML1, 'UTF-8').'</t></si>';
                }
                $sstXml .= '</sst>';
                $zip->addFromString('xl/sharedStrings.xml', $sstXml);

                // Build xl/worksheets/sheet1.xml
                $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                    <sheetData>';

                // Row 1: Headers
                $sheetXml .= '<row r="1">';
                foreach ($data['headers'] as $colIdx => $h) {
                    $colLetter = $this->indexToColumnLetter($colIdx);
                    $sIdx = $getStringIdx($h);
                    $sheetXml .= '<c r="'.$colLetter.'1" t="s"><v>'.$sIdx.'</v></c>';
                }
                $sheetXml .= '</row>';

                // Sample rows
                foreach ($data['sample_rows'] as $rIdx => $row) {
                    $rowNum = $rIdx + 2;
                    $sheetXml .= '<row r="'.$rowNum.'">';
                    $cIdx = 0;
                    foreach ($row as $val) {
                        $colLetter = $this->indexToColumnLetter($cIdx);
                        $sIdx = $getStringIdx($val);
                        $sheetXml .= '<c r="'.$colLetter.$rowNum.'" t="s"><v>'.$sIdx.'</v></c>';
                        $cIdx++;
                    }
                    $sheetXml .= '</row>';
                }

                $sheetXml .= '</sheetData></worksheet>';
                $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
                $zip->close();

                readfile($tmpFile);
                @unlink($tmpFile);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function indexToColumnLetter(int $index): string
    {
        $letters = '';
        while ($index >= 0) {
            $letters = chr($index % 26 + 65).$letters;
            $index = intdiv($index, 26) - 1;
        }

        return $letters;
    }
}
