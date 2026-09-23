<?php

namespace App\Services\Privacy;

class DsarPdfBuilder
{
    private array $pages = [];
    private string $currentStream = "";
    private float $y = 765.0;
    private int $pageIndex = 0;
    private string $documentTitle;
    private ?string $documentSubtitle;
    private string $documentRef;

    // Standard A4 Dimensions in PDF Points (72 pts/inch: 210mm x 297mm)
    public const PAGE_WIDTH = 595.28;
    public const PAGE_HEIGHT = 841.89;
    public const MARGIN_LEFT = 50.0;
    public const CONTENT_WIDTH = 495.28;
    public const BOTTOM_MARGIN = 62.0;
    public const TOP_START = 765.0;

    /**
     * Standard Helvetica character widths per 1,000 units.
     */
    private static array $charWidths = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, '\'' => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556,
        '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
        'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
        'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
        'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556,
        '`' => 222, 'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
        'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556,
        'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722,
        'x' => 500, 'y' => 500, 'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
    ];

    public function __construct(string $title, ?string $subtitle = null, string $ref = '')
    {
        $this->documentTitle = $title;
        $this->documentSubtitle = $subtitle;
        $this->documentRef = $ref;
        $this->newPage(isFirst: true);
    }

    public static function make(string $title, ?string $subtitle = null, string $ref = ''): self
    {
        return new self($title, $subtitle, $ref);
    }

    /**
     * Start a fresh page in the PDF stream.
     */
    private function newPage(bool $isFirst = false): void
    {
        if (! empty($this->currentStream)) {
            $this->pages[] = $this->currentStream;
        }

        $this->pageIndex++;
        $this->currentStream = "0 0 0 RG\n0 0 0 rg\n";

        // Top Institutional Navy Header Bar
        $this->currentStream .= "0.08 0.22 0.45 rg\n" . self::MARGIN_LEFT . " 810 " . self::CONTENT_WIDTH . " 3.5 re f\n";

        // Top Running Header
        $refText = $this->documentRef ? " | Ref: {$this->documentRef}" : "";
        $this->currentStream .= "BT\n/F1 7.5 Tf\n0.32 0.38 0.45 rg\n" . self::MARGIN_LEFT . " 796 Td\n(" .
            self::escape("Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium (DJNRMHS) - HIMS" . $refText) .
            ") Tj\nET\n";

        // Running Header Divider Line
        $this->currentStream .= "0.84 0.88 0.93 RG\n0.5 w\n" . self::MARGIN_LEFT . " 790 " . self::CONTENT_WIDTH . " 0 re S\n";

        $this->y = self::TOP_START;

        if ($isFirst) {
            // Main Document Title
            $this->currentStream .= "BT\n/F1 14 Tf\n0.06 0.16 0.32 rg\n" . self::MARGIN_LEFT . " {$this->y} Td\n(" .
                self::escape($this->documentTitle) . ") Tj\nET\n";
            $this->y -= 17.0;

            if ($this->documentSubtitle) {
                $this->currentStream .= "BT\n/F2 8.5 Tf\n0.35 0.40 0.48 rg\n" . self::MARGIN_LEFT . " {$this->y} Td\n(" .
                    self::escape($this->documentSubtitle) . ") Tj\nET\n";
                $this->y -= 15.0;
            }

            // Title Divider Rule
            $this->currentStream .= "0.80 0.84 0.90 RG\n0.75 w\n" . self::MARGIN_LEFT . " {$this->y} " . self::CONTENT_WIDTH . " 0 re S\n";
            $this->y -= 16.0;
        }
    }

    /**
     * Check vertical space and spawn a new page when approaching the bottom margin.
     */
    public function checkSpace(float $heightNeeded, ?callable $onNewPage = null): void
    {
        if (($this->y - $heightNeeded) < self::BOTTOM_MARGIN) {
            $this->newPage();
            if ($onNewPage !== null) {
                $onNewPage($this);
            }
        }
    }

    /**
     * Add a formal section heading with generous spacing and keep-with-next protection.
     */
    public function addSectionHeading(string $title, float $keepWithNextHeight = 65.0): self
    {
        // Require room for heading (~22pt) PLUS subsequent content (~65pt) to prevent orphaned headings
        $this->checkSpace(22.0 + $keepWithNextHeight);

        // Top spacing before heading (only if not at the very top of a fresh page)
        if ($this->y < (self::TOP_START - 20.0)) {
            $this->y -= 14.0;
        }

        // Left accent indicator badge (deep blue bar)
        $barHeight = 13.0;
        $barY = $this->y - 2.0;
        $this->currentStream .= "0.12 0.35 0.65 rg\n" . self::MARGIN_LEFT . " {$barY} 3.5 {$barHeight} re f\n";

        // Heading title text
        $this->currentStream .= "BT\n/F1 10.5 Tf\n0.06 0.14 0.28 rg\n" . (self::MARGIN_LEFT + 9.0) . " {$this->y} Td\n(" .
            self::escape($title) . ") Tj\nET\n";

        $this->y -= 16.0;

        return $this;
    }

    /**
     * Add a formal executive profile summary block with two balanced, non-overlapping columns.
     */
    public function addProfileCard(
        string $cardTitle,
        array $leftFields,
        array $rightFields,
        float $spaceAfter = 14.0
    ): self {
        $leftRowCount = count($leftFields);
        $rightRowCount = count($rightFields);
        $maxRows = max($leftRowCount, $rightRowCount);

        $rowLineHeight = 16.0;
        $headerHeight = 22.0;
        $cardHeight = ($maxRows * $rowLineHeight) + $headerHeight + 12.0;

        $this->checkSpace($cardHeight + 6.0);
        $boxY = $this->y - $cardHeight;

        // Container background and border
        $this->currentStream .= "0.975 0.985 0.995 rg\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$cardHeight} re f\n";
        $this->currentStream .= "0.82 0.86 0.92 RG\n0.75 w\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$cardHeight} re S\n";

        // Card header banner
        $bannerY = $this->y - $headerHeight;
        $this->currentStream .= "0.93 0.955 0.985 rg\n" . self::MARGIN_LEFT . " {$bannerY} " . self::CONTENT_WIDTH . " {$headerHeight} re f\n";
        $this->currentStream .= "0.82 0.86 0.92 RG\n0.5 w\n" . self::MARGIN_LEFT . " {$bannerY} " . self::CONTENT_WIDTH . " 0 re S\n";

        // Card banner title
        $this->currentStream .= "BT\n/F1 8.5 Tf\n0.08 0.20 0.40 rg\n" . (self::MARGIN_LEFT + 12.0) . " " . ($this->y - 14.0) . " Td\n(" .
            self::escape($cardTitle) . ") Tj\nET\n";

        $textY = $bannerY - 13.0;

        for ($i = 0; $i < $maxRows; $i++) {
            // Left Column
            if (isset($leftFields[$i])) {
                $lbl = $leftFields[$i]['label'] ?? '';
                $val = (string) ($leftFields[$i]['value'] ?? '');

                $this->currentStream .= "BT\n/F1 7.5 Tf\n0.35 0.40 0.48 rg\n62 {$textY} Td\n(" .
                    self::escape($lbl . ':') . ") Tj\nET\n";
                $this->currentStream .= "BT\n/F2 7.5 Tf\n0.10 0.14 0.22 rg\n148 {$textY} Td\n(" .
                    self::escape($val) . ") Tj\nET\n";
            }

            // Right Column
            if (isset($rightFields[$i])) {
                $lbl = $rightFields[$i]['label'] ?? '';
                $val = (string) ($rightFields[$i]['value'] ?? '');

                $this->currentStream .= "BT\n/F1 7.5 Tf\n0.35 0.40 0.48 rg\n305 {$textY} Td\n(" .
                    self::escape($lbl . ':') . ") Tj\nET\n";
                $this->currentStream .= "BT\n/F2 7.5 Tf\n0.10 0.14 0.22 rg\n398 {$textY} Td\n(" .
                    self::escape($val) . ") Tj\nET\n";
            }

            $textY -= $rowLineHeight;
        }

        $this->y = $boxY - $spaceAfter;

        return $this;
    }

    /**
     * Add a body paragraph with natural wrapping and comfortable line height.
     */
    public function addParagraph(string $text, bool $bold = false, float $spaceAfter = 8.0): self
    {
        $fontSize = 8.5;
        $lineHeight = 13.0;
        $lines = $this->wrapText($text, self::CONTENT_WIDTH, $fontSize, $bold);
        $font = $bold ? '/F1' : '/F2';
        $color = $bold ? '0.08 0.14 0.22 rg' : '0.18 0.22 0.28 rg';

        foreach ($lines as $line) {
            $this->checkSpace(14.0);
            $this->currentStream .= "BT\n{$font} {$fontSize} Tf\n{$color}\n" . self::MARGIN_LEFT . " {$this->y} Td\n(" .
                self::escape($line) . ") Tj\nET\n";
            $this->y -= $lineHeight;
        }

        $this->y -= ($spaceAfter - 3.0);

        return $this;
    }

    /**
     * Add an indented bullet list item with a crisp vector bullet dot.
     */
    public function addBulletPoint(string $text, float $spaceAfter = 6.0): self
    {
        $fontSize = 8.5;
        $lineHeight = 13.0;
        $textX = self::MARGIN_LEFT + 15.0;
        $maxWidth = self::CONTENT_WIDTH - 15.0;

        $lines = $this->wrapText($text, $maxWidth, $fontSize, false);
        $totalHeight = (count($lines) * $lineHeight) + $spaceAfter;
        $this->checkSpace($totalHeight);

        // Vector bullet square dot at x = MARGIN_LEFT + 5
        $bulletX = self::MARGIN_LEFT + 4.5;
        $bulletY = $this->y - 7.5;
        $this->currentStream .= "0.12 0.35 0.65 rg\n{$bulletX} {$bulletY} 3 3 re f\n";

        // Text lines
        $textY = $this->y - 8.0;
        foreach ($lines as $line) {
            $this->currentStream .= "BT\n/F2 {$fontSize} Tf\n0.18 0.22 0.28 rg\n{$textX} {$textY} Td\n(" .
                self::escape($line) . ") Tj\nET\n";
            $textY -= $lineHeight;
        }

        $this->y = $textY - ($spaceAfter - 3.0);

        return $this;
    }

    /**
     * Add a highlighted callout box with comfortable padding and left accent stripe.
     */
    public function addCallout(string $text, string $type = 'info', ?string $title = null, float $spaceAfter = 12.0): self
    {
        $fontSize = 8.0;
        $lineHeight = 12.5;
        $maxWidth = self::CONTENT_WIDTH - 24.0;
        $lines = $this->wrapText($text, $maxWidth, $fontSize, false);

        $titleOffset = $title ? 15.0 : 0.0;
        $boxHeight = (count($lines) * $lineHeight) + 16.0 + $titleOffset;

        $this->checkSpace($boxHeight + 6.0);
        $boxY = $this->y - $boxHeight;

        $bgColor = $type === 'warning' ? '0.99 0.97 0.92 rg' : '0.95 0.97 1.0 rg';
        $borderColor = $type === 'warning' ? '0.90 0.80 0.60 RG' : '0.75 0.85 0.95 RG';
        $accentColor = $type === 'warning' ? '0.80 0.50 0.10 rg' : '0.12 0.40 0.70 rg';

        $this->currentStream .= "{$bgColor}\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$boxHeight} re f\n";
        $this->currentStream .= "{$borderColor}\n0.6 w\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$boxHeight} re S\n";
        $this->currentStream .= "{$accentColor}\n" . self::MARGIN_LEFT . " {$boxY} 3.5 {$boxHeight} re f\n";

        $textY = $this->y - 12.0;

        if ($title) {
            $this->currentStream .= "BT\n/F1 8.5 Tf\n0.10 0.22 0.42 rg\n" . (self::MARGIN_LEFT + 12.0) . " {$textY} Td\n(" .
                self::escape($title) . ") Tj\nET\n";
            $textY -= 14.0;
        }

        foreach ($lines as $line) {
            $this->currentStream .= "BT\n/F2 {$fontSize} Tf\n0.15 0.18 0.22 rg\n" . (self::MARGIN_LEFT + 12.0) . " {$textY} Td\n(" .
                self::escape($line) . ") Tj\nET\n";
            $textY -= $lineHeight;
        }

        $this->y = $boxY - $spaceAfter;

        return $this;
    }

    /**
     * Add a 2x2 grid of distinct institutional metric boxes.
     */
    public function addStatsGrid(array $items, float $spaceAfter = 14.0): self
    {
        $chunks = array_chunk($items, 2);
        $boxWidth = (self::CONTENT_WIDTH - 12.0) / 2.0;
        $boxHeight = 36.0;

        $totalHeight = (count($chunks) * ($boxHeight + 8.0));
        $this->checkSpace($totalHeight);

        foreach ($chunks as $row) {
            $boxY = $this->y - $boxHeight;

            // Left Metric Box
            if (isset($row[0])) {
                $lbl = strtoupper($row[0]['label'] ?? '');
                $val = (string) ($row[0]['value'] ?? '');
                $leftX = self::MARGIN_LEFT;

                $this->currentStream .= "0.975 0.985 0.995 rg\n{$leftX} {$boxY} {$boxWidth} {$boxHeight} re f\n";
                $this->currentStream .= "0.85 0.88 0.93 RG\n0.6 w\n{$leftX} {$boxY} {$boxWidth} {$boxHeight} re S\n";

                $this->currentStream .= "BT\n/F1 6.5 Tf\n0.40 0.45 0.52 rg\n" . ($leftX + 10.0) . " " . ($this->y - 13.0) . " Td\n(" .
                    self::escape($lbl) . ") Tj\nET\n";
                $this->currentStream .= "BT\n/F1 9 Tf\n0.08 0.16 0.30 rg\n" . ($leftX + 10.0) . " " . ($this->y - 27.0) . " Td\n(" .
                    self::escape($val) . ") Tj\nET\n";
            }

            // Right Metric Box
            if (isset($row[1])) {
                $lbl = strtoupper($row[1]['label'] ?? '');
                $val = (string) ($row[1]['value'] ?? '');
                $rightX = self::MARGIN_LEFT + $boxWidth + 12.0;

                $this->currentStream .= "0.975 0.985 0.995 rg\n{$rightX} {$boxY} {$boxWidth} {$boxHeight} re f\n";
                $this->currentStream .= "0.85 0.88 0.93 RG\n0.6 w\n{$rightX} {$boxY} {$boxWidth} {$boxHeight} re S\n";

                $this->currentStream .= "BT\n/F1 6.5 Tf\n0.40 0.45 0.52 rg\n" . ($rightX + 10.0) . " " . ($this->y - 13.0) . " Td\n(" .
                    self::escape($lbl) . ") Tj\nET\n";
                $this->currentStream .= "BT\n/F1 9 Tf\n0.08 0.16 0.30 rg\n" . ($rightX + 10.0) . " " . ($this->y - 27.0) . " Td\n(" .
                    self::escape($val) . ") Tj\nET\n";
            }

            $this->y = $boxY - 8.0;
        }

        $this->y -= ($spaceAfter - 8.0);

        return $this;
    }

    /**
     * Add side-by-side institutional contact cards with independent multi-line wrapping.
     */
    public function addContactPanels(array $picDetails, array $dpoDetails, float $spaceAfter = 14.0): self
    {
        $panelWidth = (self::CONTENT_WIDTH - 12.0) / 2.0;
        $fontSize = 7.5;
        $lineHeight = 11.5;

        // Wrap PIC details
        $picWrapped = [];
        $picLinesCount = 0;
        foreach ($picDetails as $item) {
            $lbl = $item['label'] . ':';
            $valLines = $this->wrapText((string) $item['value'], $panelWidth - 20.0, $fontSize);
            $picWrapped[] = ['label' => $lbl, 'lines' => $valLines];
            $picLinesCount += 1 + count($valLines);
        }

        // Wrap DPO details
        $dpoWrapped = [];
        $dpoLinesCount = 0;
        foreach ($dpoDetails as $item) {
            $lbl = $item['label'] . ':';
            $valLines = $this->wrapText((string) $item['value'], $panelWidth - 20.0, $fontSize);
            $dpoWrapped[] = ['label' => $lbl, 'lines' => $valLines];
            $dpoLinesCount += 1 + count($valLines);
        }

        $maxLines = max($picLinesCount, $dpoLinesCount);
        $panelHeight = ($maxLines * $lineHeight) + 38.0;

        $this->checkSpace($panelHeight + 8.0);
        $boxY = $this->y - $panelHeight;

        $leftX = self::MARGIN_LEFT;
        $rightX = self::MARGIN_LEFT + $panelWidth + 12.0;
        $headerY = $this->y - 20.0;

        // Left Panel (PIC)
        $this->currentStream .= "0.975 0.985 0.995 rg\n{$leftX} {$boxY} {$panelWidth} {$panelHeight} re f\n";
        $this->currentStream .= "0.82 0.86 0.92 RG\n0.75 w\n{$leftX} {$boxY} {$panelWidth} {$panelHeight} re S\n";
        $this->currentStream .= "0.93 0.955 0.985 rg\n{$leftX} {$headerY} {$panelWidth} 20 re f\n";
        $this->currentStream .= "0.82 0.86 0.92 RG\n0.5 w\n{$leftX} {$headerY} {$panelWidth} 0 re S\n";
        $this->currentStream .= "BT\n/F1 7.5 Tf\n0.08 0.20 0.40 rg\n" . ($leftX + 10.0) . " " . ($this->y - 13.5) . " Td\n(" .
            self::escape("PERSONAL INFORMATION CONTROLLER (PIC)") . ") Tj\nET\n";

        $textY = $headerY - 12.0;
        foreach ($picWrapped as $field) {
            $this->currentStream .= "BT\n/F1 7 Tf\n0.35 0.40 0.48 rg\n" . ($leftX + 10.0) . " {$textY} Td\n(" .
                self::escape($field['label']) . ") Tj\nET\n";
            $textY -= $lineHeight;
            foreach ($field['lines'] as $line) {
                $this->currentStream .= "BT\n/F2 7.5 Tf\n0.10 0.14 0.22 rg\n" . ($leftX + 10.0) . " {$textY} Td\n(" .
                    self::escape($line) . ") Tj\nET\n";
                $textY -= $lineHeight;
            }
            $textY -= 2.0;
        }

        // Right Panel (DPO)
        $this->currentStream .= "0.975 0.985 0.995 rg\n{$rightX} {$boxY} {$panelWidth} {$panelHeight} re f\n";
        $this->currentStream .= "0.82 0.86 0.92 RG\n0.75 w\n{$rightX} {$boxY} {$panelWidth} {$panelHeight} re S\n";
        $this->currentStream .= "0.93 0.955 0.985 rg\n{$rightX} {$headerY} {$panelWidth} 20 re f\n";
        $this->currentStream .= "0.82 0.86 0.92 RG\n0.5 w\n{$rightX} {$headerY} {$panelWidth} 0 re S\n";
        $this->currentStream .= "BT\n/F1 7.5 Tf\n0.08 0.20 0.40 rg\n" . ($rightX + 10.0) . " " . ($this->y - 13.5) . " Td\n(" .
            self::escape("DATA PROTECTION OFFICER (DPO)") . ") Tj\nET\n";

        $textY = $headerY - 12.0;
        foreach ($dpoWrapped as $field) {
            $this->currentStream .= "BT\n/F1 7 Tf\n0.35 0.40 0.48 rg\n" . ($rightX + 10.0) . " {$textY} Td\n(" .
                self::escape($field['label']) . ") Tj\nET\n";
            $textY -= $lineHeight;
            foreach ($field['lines'] as $line) {
                $this->currentStream .= "BT\n/F2 7.5 Tf\n0.10 0.14 0.22 rg\n" . ($rightX + 10.0) . " {$textY} Td\n(" .
                    self::escape($line) . ") Tj\nET\n";
                $textY -= $lineHeight;
            }
            $textY -= 2.0;
        }

        $this->y = $boxY - $spaceAfter;

        return $this;
    }

    /**
     * Add a fully responsive, multi-page, multi-line table with vertical cell expansion and repeating headers.
     */
    public function addTable(
        array $headers,
        array $rows,
        ?array $colWidths = null,
        float $spaceAfter = 14.0
    ): self {
        $colCount = count($headers);
        if ($colCount === 0) {
            return $this;
        }

        // Scale column widths to match CONTENT_WIDTH exactly
        if ($colWidths === null || count($colWidths) !== $colCount) {
            $defaultWidth = self::CONTENT_WIDTH / $colCount;
            $colWidths = array_fill(0, $colCount, $defaultWidth);
        } else {
            $sum = array_sum($colWidths);
            if (abs($sum - self::CONTENT_WIDTH) > 0.5 && $sum > 0) {
                $scale = self::CONTENT_WIDTH / $sum;
                foreach ($colWidths as $i => $w) {
                    $colWidths[$i] = round($w * $scale, 2);
                }
            }
        }

        // Column start X coordinates
        $colX = [];
        $currentX = self::MARGIN_LEFT;
        foreach ($colWidths as $w) {
            $colX[] = $currentX;
            $currentX += $w;
        }

        // Closure to render the table header block
        $renderHeader = function () use ($headers, $colWidths, $colX) {
            $headerHeight = 20.0;
            $this->currentStream .= "0.91 0.94 0.975 rg\n" . self::MARGIN_LEFT . " " . ($this->y - $headerHeight) . " " . self::CONTENT_WIDTH . " {$headerHeight} re f\n";
            $this->currentStream .= "0.75 0.80 0.88 RG\n0.75 w\n" . self::MARGIN_LEFT . " " . ($this->y - $headerHeight) . " " . self::CONTENT_WIDTH . " {$headerHeight} re S\n";

            foreach ($headers as $idx => $headerText) {
                $xPos = $colX[$idx] + 7.0;
                $yPos = $this->y - 13.5;
                $this->currentStream .= "BT\n/F1 8 Tf\n0.06 0.16 0.32 rg\n{$xPos} {$yPos} Td\n(" .
                    self::escape($headerText) . ") Tj\nET\n";
            }
            $this->y -= $headerHeight;
        };

        // Estimate first row height to prevent orphaned table header
        $firstRowHeight = 24.0;
        if (! empty($rows)) {
            $firstCellLines = [];
            foreach ($rows[0] as $cIdx => $cellText) {
                $w = $colWidths[$cIdx] ?? 100;
                $firstCellLines[] = count($this->wrapText((string) $cellText, $w - 14.0, 7.5, $cIdx === 0));
            }
            $maxFirstLines = max($firstCellLines ?: [1]);
            $firstRowHeight = ($maxFirstLines * 11.5) + 12.0;
        }

        // Ensure room for header + first data row
        $this->checkSpace(20.0 + $firstRowHeight + 5.0);
        $renderHeader();

        // Render data rows
        foreach ($rows as $rIdx => $row) {
            $cellLines = [];
            foreach ($row as $cIdx => $cellText) {
                if ($cIdx >= $colCount) {
                    break;
                }
                $w = $colWidths[$cIdx];
                $cellLines[$cIdx] = $this->wrapText((string) $cellText, $w - 14.0, 7.5, $cIdx === 0);
            }

            $lineCounts = array_map('count', $cellLines);
            $maxLines = max($lineCounts ?: [1]);
            $rowHeight = ($maxLines * 11.5) + 10.0; // 5pt top padding + 5pt bottom padding

            // Break page if row exceeds remaining space
            if (($this->y - $rowHeight) < self::BOTTOM_MARGIN) {
                $this->newPage();
                $renderHeader(); // Re-render table header on new page!
            }

            // Alternating zebra striping
            $rowBg = ($rIdx % 2 === 0) ? '1 1 1 rg' : '0.982 0.988 0.995 rg';
            $this->currentStream .= "{$rowBg}\n" . self::MARGIN_LEFT . " " . ($this->y - $rowHeight) . " " . self::CONTENT_WIDTH . " {$rowHeight} re f\n";
            $this->currentStream .= "0.88 0.90 0.93 RG\n0.4 w\n" . self::MARGIN_LEFT . " " . ($this->y - $rowHeight) . " " . self::CONTENT_WIDTH . " {$rowHeight} re S\n";

            // Render cell text line by line
            foreach ($cellLines as $cIdx => $lines) {
                $xPos = $colX[$cIdx] + 7.0;
                $font = ($cIdx === 0) ? '/F1' : '/F2';
                $color = ($cIdx === 0) ? '0.08 0.16 0.28 rg' : '0.15 0.18 0.22 rg';

                $textY = $this->y - 12.0;
                foreach ($lines as $line) {
                    $this->currentStream .= "BT\n{$font} 7.5 Tf\n{$color}\n{$xPos} {$textY} Td\n(" .
                        self::escape($line) . ") Tj\nET\n";
                    $textY -= 11.5;
                }
            }

            $this->y -= $rowHeight;
        }

        $this->y -= $spaceAfter;

        return $this;
    }

    /**
     * Add a formal signature and certification block.
     */
    public function addSignatureBlock(
        string $officerName,
        string $officerTitle,
        string $date,
        ?string $packageHash = null
    ): self {
        $this->checkSpace(85.0);
        $this->y -= 12.0;

        // Dividing rule
        $this->currentStream .= "0.82 0.86 0.92 RG\n0.75 w\n" . self::MARGIN_LEFT . " {$this->y} " . self::CONTENT_WIDTH . " 0 re S\n";
        $this->y -= 18.0;

        // Left signature line
        $sigLineY = $this->y - 18.0;
        $this->currentStream .= "0.25 0.30 0.38 RG\n1 w\n" . (self::MARGIN_LEFT + 5.0) . " {$sigLineY} 190 0 re S\n";
        $this->currentStream .= "BT\n/F1 8.5 Tf\n0.08 0.16 0.28 rg\n" . (self::MARGIN_LEFT + 5.0) . " " . ($this->y - 30.0) . " Td\n(" .
            self::escape($officerName) . ") Tj\nET\n";
        $this->currentStream .= "BT\n/F2 7.5 Tf\n0.35 0.40 0.48 rg\n" . (self::MARGIN_LEFT + 5.0) . " " . ($this->y - 42.0) . " Td\n(" .
            self::escape($officerTitle) . ") Tj\nET\n";

        // Right verification metadata block
        $rightX = self::MARGIN_LEFT + 265.0;
        $this->currentStream .= "BT\n/F1 8 Tf\n0.08 0.16 0.28 rg\n{$rightX} {$this->y} Td\n(" .
            self::escape("Institutional Certification & Attestation") . ") Tj\nET\n";
        $this->currentStream .= "BT\n/F2 7.5 Tf\n0.30 0.35 0.42 rg\n{$rightX} " . ($this->y - 13.0) . " Td\n(" .
            self::escape("Attestation Date: " . $date) . ") Tj\nET\n";

        if ($packageHash) {
            $shortHash = substr($packageHash, 0, 32) . '...';
            $this->currentStream .= "BT\n/F2 7 Tf\n0.40 0.45 0.52 rg\n{$rightX} " . ($this->y - 25.0) . " Td\n(" .
                self::escape("Package SHA-256: " . $shortHash) . ") Tj\nET\n";
        }

        $this->currentStream .= "BT\n/F2 7 Tf\n0.40 0.45 0.52 rg\n{$rightX} " . ($this->y - ($packageHash ? 37.0 : 25.0)) . " Td\n(" .
            self::escape("Official Electronic Seal: DJNRMHS-DPO-CERTIFIED") . ") Tj\nET\n";

        $this->y -= 58.0;

        return $this;
    }

    /**
     * Backward-compatible key-value grid wrapper using generous row height and safe column division.
     */
    public function addKeyValueGrid(array $pairs): self
    {
        $chunks = array_chunk($pairs, 2);
        foreach ($chunks as $row) {
            $this->checkSpace(20.0);

            if (isset($row[0])) {
                $label = $row[0]['label'] ?? '';
                $val = $row[0]['value'] ?? '';
                $this->currentStream .= "BT\n/F1 7.5 Tf\n0.35 0.40 0.48 rg\n" . (self::MARGIN_LEFT + 5.0) . " {$this->y} Td\n(" .
                    self::escape($label . ':') . ") Tj\nET\n";
                $this->currentStream .= "BT\n/F2 7.5 Tf\n0.10 0.14 0.22 rg\n" . (self::MARGIN_LEFT + 95.0) . " {$this->y} Td\n(" .
                    self::escape($val) . ") Tj\nET\n";
            }

            if (isset($row[1])) {
                $label = $row[1]['label'] ?? '';
                $val = $row[1]['value'] ?? '';
                $this->currentStream .= "BT\n/F1 7.5 Tf\n0.35 0.40 0.48 rg\n" . (self::MARGIN_LEFT + 255.0) . " {$this->y} Td\n(" .
                    self::escape($label . ':') . ") Tj\nET\n";
                $this->currentStream .= "BT\n/F2 7.5 Tf\n0.10 0.14 0.22 rg\n" . (self::MARGIN_LEFT + 350.0) . " {$this->y} Td\n(" .
                    self::escape($val) . ") Tj\nET\n";
            }

            $this->y -= 15.0;
        }

        $this->y -= 8.0;

        return $this;
    }

    /**
     * Backward-compatible detail card wrapper.
     */
    public function addDetailCard(array $lines, ?string $cardTitle = null): self
    {
        $titleOffset = $cardTitle ? 16.0 : 0.0;
        $lineCount = count($lines);
        $cardHeight = ($lineCount * 14.0) + 16.0 + $titleOffset;

        $this->checkSpace($cardHeight + 8.0);
        $boxY = $this->y - $cardHeight;

        $this->currentStream .= "0.975 0.985 0.995 rg\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$cardHeight} re f\n";
        $this->currentStream .= "0.82 0.86 0.92 RG\n0.75 w\n" . self::MARGIN_LEFT . " {$boxY} " . self::CONTENT_WIDTH . " {$cardHeight} re S\n";

        $textY = $this->y - 12.0;

        if ($cardTitle) {
            $this->currentStream .= "BT\n/F1 8.5 Tf\n0.08 0.20 0.40 rg\n" . (self::MARGIN_LEFT + 12.0) . " {$textY} Td\n(" .
                self::escape($cardTitle) . ") Tj\nET\n";
            $textY -= 15.0;
        }

        foreach ($lines as $line) {
            $this->currentStream .= "BT\n/F2 7.5 Tf\n0.15 0.18 0.22 rg\n" . (self::MARGIN_LEFT + 12.0) . " {$textY} Td\n(" .
                self::escape($line) . ") Tj\nET\n";
            $textY -= 13.5;
        }

        $this->y = $boxY - 14.0;

        return $this;
    }

    /**
     * Compute string width in points based on standard Helvetica character metrics.
     */
    public static function getTextWidth(string $text, float $fontSize, bool $bold = false): float
    {
        $units = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];
            $units += self::$charWidths[$c] ?? 550;
        }
        if ($bold) {
            $units *= 1.05;
        }

        return $units * ($fontSize / 1000.0);
    }

    /**
     * Wrap text accurately to the maximum width in points.
     */
    public function wrapText(string $text, float $maxWidth, float $fontSize, bool $bold = false): array
    {
        $text = self::sanitizeTypography($text);
        $rawParagraphs = explode("\n", $text);
        $lines = [];

        foreach ($rawParagraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $words = preg_split('/\s+/', $paragraph);
            $currentLine = '';

            foreach ($words as $word) {
                $candidate = ($currentLine === '') ? $word : $currentLine . ' ' . $word;
                $candWidth = self::getTextWidth($candidate, $fontSize, $bold);

                if ($candWidth <= $maxWidth) {
                    $currentLine = $candidate;
                } else {
                    if ($currentLine !== '') {
                        $lines[] = $currentLine;
                        $currentLine = '';
                    }

                    // If single word exceeds maxWidth, break into character chunks
                    $wordWidth = self::getTextWidth($word, $fontSize, $bold);
                    if ($wordWidth > $maxWidth) {
                        $chunk = '';
                        $wordLen = strlen($word);
                        for ($c = 0; $c < $wordLen; $c++) {
                            $testChunk = $chunk . $word[$c];
                            if (self::getTextWidth($testChunk, $fontSize, $bold) > $maxWidth && $chunk !== '') {
                                $lines[] = $chunk;
                                $chunk = $word[$c];
                            } else {
                                $chunk = $testChunk;
                            }
                        }
                        $currentLine = $chunk;
                    } else {
                        $currentLine = $word;
                    }
                }
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return $lines;
    }

    /**
     * Sanitize non-ASCII typographic characters into clean equivalents.
     */
    public static function sanitizeTypography(string $text): string
    {
        $replacements = [
            "\u{2014}" => ' - ', // em dash
            "\u{2013}" => '-',   // en dash
            "\u{2022}" => '-',   // bullet
            "\u{201C}" => '"',   // left double quote
            "\u{201D}" => '"',   // right double quote
            "\u{2018}" => "'",   // left single quote
            "\u{2019}" => "'",   // right single quote
            "\u{2026}" => '...', // ellipsis
            "\u{00A0}" => ' ',   // non-breaking space
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        // Strip non-printable or unsupported multi-byte characters
        return preg_replace('/[^\x20-\x7E\r\n\t]/', '', $text);
    }

    /**
     * Escape PDF string literals.
     */
    private static function escape(string $text): string
    {
        $sanitized = self::sanitizeTypography($text);

        return strtr($sanitized, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
        ]);
    }

    /**
     * Render the complete PDF binary document.
     */
    public function render(): string
    {
        if (! empty($this->currentStream)) {
            $this->pages[] = $this->currentStream;
            $this->currentStream = "";
        }

        $totalPages = count($this->pages);

        // Append footers to each page with actual total page count
        foreach ($this->pages as $pIdx => &$stream) {
            $pageNum = $pIdx + 1;
            $footerY = 38.0;

            // Footer dividing rule
            $stream .= "0.82 0.86 0.92 RG\n0.5 w\n" . self::MARGIN_LEFT . " 48 " . self::CONTENT_WIDTH . " 0 re S\n";

            // Left footer title
            $stream .= "BT\n/F2 7 Tf\n0.40 0.45 0.52 rg\n" . self::MARGIN_LEFT . " {$footerY} Td\n(" .
                self::escape("Hospital Information Management System (HIMS) | Republic Act No. 10173 Official Disclosure") .
                ") Tj\nET\n";

            // Right footer page numbering
            $pageText = "Page {$pageNum} of {$totalPages}";
            $pageWidth = self::getTextWidth($pageText, 7.0, false);
            $pageRightX = self::MARGIN_LEFT + self::CONTENT_WIDTH - $pageWidth;
            $stream .= "BT\n/F1 7 Tf\n0.30 0.35 0.42 rg\n{$pageRightX} {$footerY} Td\n(" .
                self::escape($pageText) . ") Tj\nET\n";
        }
        unset($stream);

        // Build PDF Structure
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        // Obj 1: Catalog
        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n";

        // Obj 2: Pages root
        $kids = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $pageObjId = 2 + $i;
            $kids[] = "{$pageObjId} 0 R";
        }
        $kidsStr = implode(" ", $kids);

        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<</Type/Pages/Count {$totalPages}/Kids[{$kidsStr}]>>\nendobj\n";

        $f1Id = 3 + $totalPages;
        $f2Id = 4 + $totalPages;
        $firstContentId = 5 + $totalPages;

        for ($i = 1; $i <= $totalPages; $i++) {
            $pageObjId = 2 + $i;
            $contentObjId = $firstContentId + ($i - 1);

            $offsets[$pageObjId] = strlen($pdf);
            $pdf .= "{$pageObjId} 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 595.28 841.89]/Resources<</Font<</F1 {$f1Id} 0 R/F2 {$f2Id} 0 R>>>>/Contents {$contentObjId} 0 R>>\nendobj\n";
        }

        // Fonts
        $offsets[$f1Id] = strlen($pdf);
        $pdf .= "{$f1Id} 0 obj\n<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold>>\nendobj\n";

        $offsets[$f2Id] = strlen($pdf);
        $pdf .= "{$f2Id} 0 obj\n<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>\nendobj\n";

        // Content streams
        for ($i = 1; $i <= $totalPages; $i++) {
            $contentObjId = $firstContentId + ($i - 1);
            $streamData = $this->pages[$i - 1];
            $streamLen = strlen($streamData);

            $offsets[$contentObjId] = strlen($pdf);
            $pdf .= "{$contentObjId} 0 obj\n<</Length {$streamLen}>>\nstream\n{$streamData}\nendstream\nendobj\n";
        }

        $totalObjs = $firstContentId + $totalPages - 1;

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($totalObjs + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<</Size " . ($totalObjs + 1) . "/Root 1 0 R>>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
