<?php

namespace App\Support;

class DemoPdfBuilder
{
    /**
     * Build a formatted single-page PDF document binary with institutional header, detail cards, styled tables, and footer.
     *
     * @param  array<int, array{heading?: string, lines?: array<string>, table?: array{headers: array<string>, rows: array<array<string>>}}>  $sections
     */
    public static function create(string $title, array $sections, ?string $subtitle = null): string
    {
        $stream = "";

        // Default stroke and fill to solid black
        $stream .= "0 0 0 RG\n0 0 0 rg\n";

        // Top Accent Bar (Navy Blue)
        $stream .= "0.08 0.22 0.45 rg\n50 748 512 4 re f\n";

        // Document Title
        $stream .= "BT\n/F1 13.5 Tf\n0.08 0.18 0.35 rg\n50 728 Td\n(".self::escape($title).") Tj\nET\n";

        $y = 712;
        if ($subtitle !== null) {
            $stream .= "BT\n/F2 8.5 Tf\n0.35 0.4 0.48 rg\n50 {$y} Td\n(".self::escape($subtitle).") Tj\nET\n";
            $y -= 14;
        }

        // Header Divider Line
        $stream .= "0.82 0.86 0.92 RG\n1 w\n50 {$y} 512 0 re S\n";
        $y -= 16;

        foreach ($sections as $section) {
            if (! empty($section['heading'])) {
                // Section header with blue left indicator bar
                $stream .= "0.12 0.35 0.65 rg\n50 ".($y - 2)." 3 12 re f\n";
                $stream .= "BT\n/F1 9.5 Tf\n0.1 0.18 0.3 rg\n58 {$y} Td\n(".self::escape($section['heading']).") Tj\nET\n";
                $y -= 16;
            }

            if (! empty($section['lines'])) {
                $lineCount = count($section['lines']);
                $boxHeight = ($lineCount * 14) + 8;
                $boxY = $y - $boxHeight + 8;

                // Detail card background & border
                $stream .= "0.97 0.98 0.99 rg\n50 {$boxY} 512 {$boxHeight} re f\n";
                $stream .= "0.88 0.91 0.95 RG\n0.5 w\n50 {$boxY} 512 {$boxHeight} re S\n";

                $textY = $y - 4;
                foreach ($section['lines'] as $line) {
                    $stream .= "BT\n/F2 8.5 Tf\n0.15 0.18 0.22 rg\n60 {$textY} Td\n(".self::escape($line).") Tj\nET\n";
                    $textY -= 14;
                }

                $y = $boxY - 14;
            }

            if (! empty($section['table'])) {
                $headers = $section['table']['headers'] ?? [];
                $rows = $section['table']['rows'] ?? [];
                $colX = [50, 260, 340, 410, 475];

                // Table Header Container
                $stream .= "0.9 0.93 0.97 rg\n50 ".($y - 14)." 512 18 re f\n";
                $stream .= "0.75 0.8 0.88 RG\n0.75 w\n50 ".($y - 14)." 512 18 re S\n";

                foreach ($headers as $idx => $headerText) {
                    $xPos = $colX[$idx] ?? 50;
                    $stream .= "BT\n/F1 8 Tf\n0.08 0.18 0.32 rg\n".($xPos + 6)." ".($y - 10)." Td\n(".self::escape($headerText).") Tj\nET\n";
                }

                $y -= 14;

                foreach ($rows as $rIdx => $row) {
                    $rowHeight = 18;
                    $rowBg = ($rIdx % 2 === 0) ? '1 1 1 rg' : '0.98 0.98 0.99 rg';

                    $stream .= "{$rowBg}\n50 ".($y - $rowHeight)." 512 {$rowHeight} re f\n";
                    $stream .= "0.85 0.88 0.92 RG\n0.5 w\n50 ".($y - $rowHeight)." 512 {$rowHeight} re S\n";

                    foreach ($row as $cIdx => $cellText) {
                        $xPos = $colX[$cIdx] ?? 50;
                        $font = ($cIdx === 4 || $cIdx === 0) ? '/F1' : '/F2';
                        $color = ($cIdx === 4) ? '0.08 0.25 0.55 rg' : '0.15 0.18 0.22 rg';
                        $stream .= "BT\n{$font} 8 Tf\n{$color}\n".($xPos + 6)." ".($y - 12)." Td\n(".self::escape($cellText).") Tj\nET\n";
                    }

                    $y -= $rowHeight;
                }

                $y -= 14;
            }
        }

        // Institutional Footer
        $stream .= "0.8 0.84 0.9 RG\n0.75 w\n50 50 512 0 re S\n";
        $stream .= "BT\n/F2 7.5 Tf\n0.4 0.45 0.5 rg\n50 38 Td\n(Hospital Information Management System | Official Electronic Records Archive | Verified Digital Document) Tj\nET\n";

        $streamLen = strlen($stream);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n";

        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<</Type/Pages/Count 1/Kids[3 0 R]>>\nendobj\n";

        $offsets[3] = strlen($pdf);
        $pdf .= "3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<</Font<</F1 4 0 R/F2 5 0 R>>>>/Contents 6 0 R>>\nendobj\n";

        $offsets[4] = strlen($pdf);
        $pdf .= "4 0 obj\n<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold>>\nendobj\n";

        $offsets[5] = strlen($pdf);
        $pdf .= "5 0 obj\n<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>\nendobj\n";

        $offsets[6] = strlen($pdf);
        $pdf .= "6 0 obj\n<</Length {$streamLen}>>\nstream\n{$stream}\nendstream\nendobj\n";

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 7\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<</Size 7/Root 1 0 R>>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        return strtr($text, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
        ]);
    }
}
