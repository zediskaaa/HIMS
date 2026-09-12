<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

class ChatAttachmentProcessor
{
    public const MAX_TABLE_ROWS = 300;
    public const MAX_TEXT_CHARS = 150000;
    public const MAX_FILE_SIZE_BYTES = 36700160; // 35MB
    public const MAX_INLINE_PAYLOAD_BYTES = 12582912; // 12MB limit for base64 inlineData to prevent Gemini 413

    /**
     * @var array<string, string>
     */
    public const ALLOWED_EXTENSIONS = [
        'pdf' => 'application/pdf',
        'csv' => 'text/csv',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * Process and extract content from an uploaded chat attachment.
     *
     * @return array{
     *     name: string,
     *     extension: string,
     *     mime_type: string,
     *     size: int,
     *     formatted_size: string,
     *     type: string,
     *     text_content: ?string,
     *     inline_data: ?array{mime_type: string, base64: string},
     *     row_count: ?int,
     *     is_truncated: bool
     * }
     */
    public function process(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw new InvalidArgumentException('Uploaded file is inaccessible or missing.');
        }

        $size = $file->getSize();
        if ($size <= 0) {
            throw new InvalidArgumentException('The attached file is empty (0 bytes).');
        }

        if ($size > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidArgumentException('The attached file exceeds the maximum allowed size of 35MB.');
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_BASENAME);
        $extension = strtolower($file->getClientOriginalExtension());

        if (! array_key_exists($extension, self::ALLOWED_EXTENSIONS)) {
            throw new InvalidArgumentException("Unsupported file type (.{$extension}). Please attach a PDF, CSV, Excel, DOCX, TXT, JPG, or PNG file.");
        }

        $mimeType = $file->getMimeType() ?: self::ALLOWED_EXTENSIONS[$extension];

        return match ($extension) {
            'csv' => $this->processCsv($realPath, $originalName, $size, $mimeType),
            'xlsx' => $this->processXlsx($realPath, $originalName, $size, $mimeType),
            'docx' => $this->processDocx($realPath, $originalName, $size, $mimeType),
            'txt' => $this->processTxt($realPath, $originalName, $size, $mimeType),
            'pdf' => $this->processPdf($realPath, $originalName, $size, $mimeType),
            'jpg', 'jpeg', 'png' => $this->processImage($realPath, $originalName, $size, $mimeType, $extension),
        };
    }

    /**
     * Process CSV file with low-memory streaming and comprehensive dataset analytics.
     *
     * @return array<string, mixed>
     */
    private function processCsv(string $path, string $originalName, int $size, string $mimeType): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new InvalidArgumentException('Unable to open CSV file for reading.');
        }

        // Detect and handle UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $firstLine = fgets($handle);
        if ($firstLine === false || trim($firstLine) === '') {
            fclose($handle);
            throw new InvalidArgumentException('The attached CSV file is empty.');
        }

        $delimiter = $this->detectDelimiter($firstLine);

        // Rewind and position right after BOM
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") {
            fread($handle, 3);
        }

        $rows = [];
        $totalRows = 0;
        $headers = [];
        $stockColIndex = null;
        $reorderColIndex = null;
        $zeroStockCount = 0;
        $lowStockCount = 0;

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            if (empty($headers)) {
                $headers = array_map(fn ($cell) => trim((string) $cell), $row);
                // Locate stock and reorder columns for dataset-wide statistics
                foreach ($headers as $idx => $header) {
                    $lowerHeader = strtolower($header);
                    if ($stockColIndex === null && preg_match('/\b(stock|quantity|qty|on[_\s-]?hand|available|count)\b/i', $lowerHeader)) {
                        $stockColIndex = $idx;
                    }
                    if ($reorderColIndex === null && preg_match('/\b(reorder|re-order|threshold|minimum|min[_\s-]?stock)\b/i', $lowerHeader)) {
                        $reorderColIndex = $idx;
                    }
                }
                $rows[] = $headers;

                continue;
            }

            $totalRows++;
            $cleanRow = array_map(fn ($cell) => trim((string) $cell), $row);

            // Calculate dataset-wide statistics across all rows
            if ($stockColIndex !== null && isset($cleanRow[$stockColIndex])) {
                $stockVal = filter_var($cleanRow[$stockColIndex], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if (is_numeric($stockVal)) {
                    $numStock = (float) $stockVal;
                    if ($numStock <= 0) {
                        $zeroStockCount++;
                    }
                    if ($reorderColIndex !== null && isset($cleanRow[$reorderColIndex])) {
                        $reorderVal = filter_var($cleanRow[$reorderColIndex], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        if (is_numeric($reorderVal) && $numStock <= (float) $reorderVal) {
                            $lowStockCount++;
                        }
                    }
                }
            }

            // Keep sample rows up to MAX_TABLE_ROWS
            if (count($rows) <= self::MAX_TABLE_ROWS) {
                $rows[] = $cleanRow;
            }
        }

        fclose($handle);

        if ($totalRows === 0 && count($rows) <= 1) {
            throw new InvalidArgumentException('No readable tabular records found in the attached CSV file.');
        }

        $markdownTable = $this->formatRowsToMarkdownTable($rows);
        $isTruncated = $totalRows > self::MAX_TABLE_ROWS;

        // Build comprehensive dataset overview
        $stats = [];
        $stats[] = "\n\n### Full Dataset Analytics for `{$originalName}` ({$this->formatBytes($size)}):";
        $stats[] = "- **Total Records:** {$totalRows} rows analyzed";
        if (! empty($headers)) {
            $stats[] = '- **Columns Detected ('.count($headers).'):** '.implode(', ', array_slice($headers, 0, 12));
        }
        if ($stockColIndex !== null) {
            $stats[] = "- **Items with Zero / Depleted Stock:** {$zeroStockCount}";
            if ($reorderColIndex !== null) {
                $stats[] = "- **Items Below Reorder Threshold:** {$lowStockCount}";
            }
        }

        if ($isTruncated) {
            $stats[] = '*(Displaying first '.self::MAX_TABLE_ROWS." detailed rows of {$totalRows} total rows).*";
        }

        $finalContent = implode("\n", $stats)."\n\n### Sample Tabular Records:\n".$markdownTable;

        return [
            'name' => $originalName,
            'extension' => 'csv',
            'mime_type' => $mimeType,
            'size' => $size,
            'formatted_size' => $this->formatBytes($size),
            'type' => 'spreadsheet',
            'text_content' => $finalContent,
            'inline_data' => null,
            'row_count' => $totalRows,
            'is_truncated' => $isTruncated,
        ];
    }

    /**
     * Process XLSX using native OpenXML with dataset statistics.
     *
     * @return array<string, mixed>
     */
    private function processXlsx(string $path, string $originalName, int $size, string $mimeType): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Unable to open the Excel (.xlsx) file. It may be corrupted or password-protected.');
        }

        // 1. Read shared strings
        $sharedStrings = [];
        $sstXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sstXml !== false) {
            $cleanSst = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $sstXml);
            $sst = @simplexml_load_string($cleanSst ?: '');
            if ($sst && isset($sst->si)) {
                foreach ($sst->si as $si) {
                    $raw = strip_tags($si->asXML());
                    $sharedStrings[] = trim(html_entity_decode($raw, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                }
            }
        }

        // 2. Read first worksheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat && str_starts_with($stat['name'], 'xl/worksheets/sheet') && str_ends_with($stat['name'], '.xml')) {
                    $sheetXml = $zip->getFromIndex($i);
                    break;
                }
            }
        }

        $zip->close();

        if ($sheetXml === false) {
            throw new InvalidArgumentException('No readable worksheet found inside the Excel (.xlsx) file.');
        }

        $cleanSheetXml = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $sheetXml);
        $sheet = @simplexml_load_string($cleanSheetXml ?: '');
        if ($sheet === false || ! isset($sheet->sheetData->row)) {
            throw new InvalidArgumentException('The Excel worksheet is empty or malformed.');
        }

        $rows = [];
        $totalRows = 0;
        $headers = [];
        $stockColIndex = null;
        $reorderColIndex = null;
        $zeroStockCount = 0;
        $lowStockCount = 0;

        foreach ($sheet->sheetData->row as $rowNode) {
            $rowCells = [];
            foreach ($rowNode->c as $cell) {
                $val = isset($cell->v) ? (string) $cell->v : '';
                $type = isset($cell['t']) ? (string) $cell['t'] : '';

                if ($type === 's' && is_numeric($val)) {
                    $val = $sharedStrings[(int) $val] ?? $val;
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $val = (string) $cell->is->t;
                }

                $rowCells[] = trim($val);
            }

            if (empty(array_filter($rowCells, fn ($c) => $c !== ''))) {
                continue;
            }

            if (empty($headers)) {
                $headers = $rowCells;
                foreach ($headers as $idx => $header) {
                    $lowerHeader = strtolower($header);
                    if ($stockColIndex === null && preg_match('/\b(stock|quantity|qty|on[_\s-]?hand|available|count)\b/i', $lowerHeader)) {
                        $stockColIndex = $idx;
                    }
                    if ($reorderColIndex === null && preg_match('/\b(reorder|re-order|threshold|minimum|min[_\s-]?stock)\b/i', $lowerHeader)) {
                        $reorderColIndex = $idx;
                    }
                }
                $rows[] = $headers;

                continue;
            }

            $totalRows++;

            if ($stockColIndex !== null && isset($rowCells[$stockColIndex])) {
                $stockVal = filter_var($rowCells[$stockColIndex], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if (is_numeric($stockVal)) {
                    $numStock = (float) $stockVal;
                    if ($numStock <= 0) {
                        $zeroStockCount++;
                    }
                    if ($reorderColIndex !== null && isset($rowCells[$reorderColIndex])) {
                        $reorderVal = filter_var($rowCells[$reorderColIndex], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        if (is_numeric($reorderVal) && $numStock <= (float) $reorderVal) {
                            $lowStockCount++;
                        }
                    }
                }
            }

            if (count($rows) <= self::MAX_TABLE_ROWS) {
                $rows[] = $rowCells;
            }
        }

        if ($totalRows === 0 && count($rows) <= 1) {
            throw new InvalidArgumentException('No tabular rows found in the attached Excel worksheet.');
        }

        $markdownTable = $this->formatRowsToMarkdownTable($rows);
        $isTruncated = $totalRows > self::MAX_TABLE_ROWS;

        $stats = [];
        $stats[] = "\n\n### Full Dataset Analytics for `{$originalName}` ({$this->formatBytes($size)}):";
        $stats[] = "- **Total Records:** {$totalRows} rows analyzed";
        if (! empty($headers)) {
            $stats[] = '- **Columns Detected ('.count($headers).'):** '.implode(', ', array_slice($headers, 0, 12));
        }
        if ($stockColIndex !== null) {
            $stats[] = "- **Items with Zero / Depleted Stock:** {$zeroStockCount}";
            if ($reorderColIndex !== null) {
                $stats[] = "- **Items Below Reorder Threshold:** {$lowStockCount}";
            }
        }

        if ($isTruncated) {
            $stats[] = '*(Displaying first '.self::MAX_TABLE_ROWS." detailed rows of {$totalRows} total rows).*";
        }

        $finalContent = implode("\n", $stats)."\n\n### Sample Tabular Records:\n".$markdownTable;

        return [
            'name' => $originalName,
            'extension' => 'xlsx',
            'mime_type' => $mimeType,
            'size' => $size,
            'formatted_size' => $this->formatBytes($size),
            'type' => 'spreadsheet',
            'text_content' => $finalContent,
            'inline_data' => null,
            'row_count' => $totalRows,
            'is_truncated' => $isTruncated,
        ];
    }

    /**
     * Process DOCX file into readable text with extended character limit.
     *
     * @return array<string, mixed>
     */
    private function processDocx(string $path, string $originalName, int $size, string $mimeType): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Unable to open the Word (.docx) document. It may be corrupted or password-protected.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new InvalidArgumentException('No document body found inside the Word (.docx) file.');
        }

        $converted = str_replace(
            ['</w:p>', '</w:tr>', '<w:tab/>', '<w:br/>'],
            ["\n", "\n", "\t", "\n"],
            $xml
        );
        $text = trim(strip_tags($converted));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?: '';

        if ($text === '') {
            throw new InvalidArgumentException('The attached Word (.docx) document does not contain readable text.');
        }

        $isTruncated = false;
        $charCount = mb_strlen($text);
        if ($charCount > self::MAX_TEXT_CHARS) {
            $text = mb_substr($text, 0, self::MAX_TEXT_CHARS)."\n\n*(Document truncated: showing first ".number_format(self::MAX_TEXT_CHARS).' of '.number_format($charCount).' characters for AI analysis)*';
            $isTruncated = true;
        }

        return [
            'name' => $originalName,
            'extension' => 'docx',
            'mime_type' => $mimeType,
            'size' => $size,
            'formatted_size' => $this->formatBytes($size),
            'type' => 'document',
            'text_content' => $text,
            'inline_data' => null,
            'row_count' => null,
            'is_truncated' => $isTruncated,
        ];
    }

    /**
     * Process plain text file with extended character capacity.
     *
     * @return array<string, mixed>
     */
    private function processTxt(string $path, string $originalName, int $size, string $mimeType): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new InvalidArgumentException('Unable to open text file.');
        }

        $content = fread($handle, (int) min($size, self::MAX_TEXT_CHARS * 4));
        fclose($handle);

        if ($content === false || trim($content) === '') {
            throw new InvalidArgumentException('The attached text file is empty.');
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1, Windows-1252, ASCII');
        }

        $isTruncated = false;
        $charCount = mb_strlen($content);
        if ($charCount > self::MAX_TEXT_CHARS) {
            $content = mb_substr($content, 0, self::MAX_TEXT_CHARS)."\n\n*(Text truncated: showing first ".number_format(self::MAX_TEXT_CHARS).' of '.number_format($charCount).' characters for AI analysis)*';
            $isTruncated = true;
        }

        return [
            'name' => $originalName,
            'extension' => 'txt',
            'mime_type' => $mimeType,
            'size' => $size,
            'formatted_size' => $this->formatBytes($size),
            'type' => 'text',
            'text_content' => trim($content),
            'inline_data' => null,
            'row_count' => null,
            'is_truncated' => $isTruncated,
        ];
    }

    /**
     * Process PDF file with multimodal inline_data for files <= 12MB,
     * and robust text stream extraction for large PDFs up to 35MB.
     *
     * @return array<string, mixed>
     */
    private function processPdf(string $path, string $originalName, int $size, string $mimeType): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new InvalidArgumentException('Unable to open PDF file.');
        }

        $header = fread($handle, 1024);
        if ($header === false || ! str_contains($header, '%PDF')) {
            fclose($handle);
            throw new InvalidArgumentException('The attached file does not have a valid PDF header.');
        }

        // Read PDF raw content
        rewind($handle);
        $rawBytes = stream_get_contents($handle);
        fclose($handle);

        if ($rawBytes === false || strlen($rawBytes) === 0) {
            throw new InvalidArgumentException('The attached PDF file is empty.');
        }

        // Extract text from uncompressed and compressed streams
        $extractedText = $this->extractPdfText($rawBytes);

        // Gemini REST API has a 20MB request body limit.
        // Base64 expands binary size by 33%, so 12MB binary becomes ~16MB base64.
        $inlineData = null;
        if ($size <= self::MAX_INLINE_PAYLOAD_BYTES) {
            $inlineData = [
                'mime_type' => 'application/pdf',
                'base64' => base64_encode($rawBytes),
            ];
        }

        $textContent = $extractedText ?: 'PDF document attached for multimodal AI analysis.';
        if ($inlineData === null) {
            $textContent = "[Large PDF: {$originalName} ({$this->formatBytes($size)})]\n\n".($extractedText ?: 'Text extraction completed from large PDF document.');
        }

        return [
            'name' => $originalName,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => $size,
            'formatted_size' => $this->formatBytes($size),
            'type' => 'document',
            'text_content' => $textContent,
            'inline_data' => $inlineData,
            'row_count' => null,
            'is_truncated' => false,
        ];
    }

    /**
     * Process image file with image verification and Gemini base64 inline_data.
     *
     * @return array<string, mixed>
     */
    private function processImage(string $path, string $originalName, int $size, string $mimeType, string $extension): array
    {
        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            throw new InvalidArgumentException('The attached image is corrupted or not a valid JPG/PNG file.');
        }

        $realMime = $imageInfo['mime'] ?? $mimeType;
        if (! in_array($realMime, ['image/jpeg', 'image/png', 'image/jpg'], true)) {
            throw new InvalidArgumentException('Unsupported image format. Please attach a valid JPG or PNG image.');
        }

        $rawBytes = file_get_contents($path);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            throw new InvalidArgumentException('The attached image file is empty.');
        }

        $base64 = base64_encode($rawBytes);

        return [
            'name' => $originalName,
            'extension' => $extension,
            'mime_type' => $realMime,
            'size' => $size,
            'formatted_size' => $this->formatBytes($size),
            'type' => 'image',
            'text_content' => "Image attached ({$imageInfo[0]}x{$imageInfo[1]}px, {$this->formatBytes($size)}).",
            'inline_data' => [
                'mime_type' => $realMime,
                'base64' => $base64,
            ],
            'row_count' => null,
            'is_truncated' => false,
        ];
    }

    /**
     * Convert an array of row arrays into a clean markdown table.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function formatRowsToMarkdownTable(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $maxCols = 0;
        foreach ($rows as $r) {
            $maxCols = max($maxCols, count($r));
        }
        $maxCols = min($maxCols, 15);

        $headers = array_pad(array_slice($rows[0], 0, $maxCols), $maxCols, '');
        $headerLine = '| '.implode(' | ', array_map(fn ($h) => str_replace(['|', "\n", "\r"], ['-', ' ', ' '], (string) $h ?: '-'), $headers)).' |';
        $dividerLine = '|'.str_repeat(' --- |', $maxCols);

        $bodyLines = [];
        for ($i = 1; $i < count($rows); $i++) {
            $cells = array_pad(array_slice($rows[$i], 0, $maxCols), $maxCols, '');
            $cleanCells = array_map(fn ($c) => str_replace(['|', "\n", "\r"], ['-', ' ', ' '], (string) $c), $cells);
            $bodyLines[] = '| '.implode(' | ', $cleanCells).' |';
        }

        return $headerLine."\n".$dividerLine."\n".implode("\n", $bodyLines);
    }

    /**
     * Detect delimiter for CSV parsing.
     */
    private function detectDelimiter(string $firstLine): string
    {
        $candidates = [',', ';', "\t", '|'];
        $bestDelim = ',';
        $maxCount = 0;

        foreach ($candidates as $delim) {
            $count = substr_count($firstLine, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelim = $delim;
            }
        }

        return $bestDelim;
    }

    /**
     * Extract readable text strings from raw uncompressed and FlateDecode compressed PDF streams.
     */
    private function extractPdfText(string $pdf): string
    {
        $text = '';

        // 1. Uncompressed text blocks
        if (preg_match_all('/BT[\s\S]*?ET/', $pdf, $matches)) {
            foreach ($matches[0] as $block) {
                if (preg_match_all('/\((.*?)\)\s*Tj/', $block, $tjMatches)) {
                    $text .= implode(' ', $tjMatches[1])."\n";
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/', $block, $tjMatches)) {
                    foreach ($tjMatches[1] as $inner) {
                        preg_match_all('/\((.*?)\)/', $inner, $stringParts);
                        $text .= implode('', $stringParts[1] ?? []).' ';
                    }
                    $text .= "\n";
                }
            }
        }

        // 2. Decompress stream blocks if uncompressed text was minimal
        if (strlen(trim($text)) < 80 && preg_match_all('/stream\r?\n([\s\S]*?)\r?\nendstream/', $pdf, $streamMatches)) {
            foreach ($streamMatches[1] as $stream) {
                $decompressed = @gzuncompress($stream);
                if ($decompressed !== false && preg_match_all('/BT[\s\S]*?ET/', $decompressed, $decompressedMatches)) {
                    foreach ($decompressedMatches[0] as $block) {
                        if (preg_match_all('/\((.*?)\)\s*Tj/', $block, $tjMatches)) {
                            $text .= implode(' ', $tjMatches[1])."\n";
                        }
                        if (preg_match_all('/\[(.*?)\]\s*TJ/', $block, $tjMatches)) {
                            foreach ($tjMatches[1] as $inner) {
                                preg_match_all('/\((.*?)\)/', $inner, $stringParts);
                                $text .= implode('', $stringParts[1] ?? []).' ';
                            }
                            $text .= "\n";
                        }
                    }
                }
                if (strlen($text) >= self::MAX_TEXT_CHARS) {
                    break;
                }
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Format file size in human-readable units.
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }
}
