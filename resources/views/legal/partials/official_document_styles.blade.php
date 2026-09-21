<style>
    /* ==========================================================================
       HIMS OFFICIAL INSTITUTIONAL DOCUMENT & PRINT SYSTEM
       Engineered for high-contrast, print-safe document generation (PDF/Paper)
       Compatible with A4 standard, monochrome printers, photocopies, and digital PDFs.
       ========================================================================== */

    /* Document CSS Tokens */
    :root {
        --doc-ink: #111827;           /* Near-black #111827 for crisp body readability */
        --doc-heading: #000000;       /* Pitch black #000000 for authoritative headings */
        --doc-muted: #374151;         /* Deep neutral gray #374151 for secondary metadata */
        --doc-line: #374151;          /* Dark line #374151 for high-contrast borders */
        --doc-line-light: #6b7280;    /* Mid-tone line #6b7280 for inner table dividers */
        --doc-bg-header: #f3f4f6;     /* Light gray #f3f4f6 for legible table header blocks */
        --doc-accent: #1e3a8a;        /* Restrained institutional navy #1e3a8a */
    }

    /* Standard Page Geometry */
    @page {
        size: A4 portrait;
        margin: 18mm 18mm 20mm 18mm;
        @top-right {
            content: "{{ $documentRef ?? 'HIMS-POLICY-DOC' }}";
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            font-weight: 700;
            color: #374151;
        }
        @bottom-left {
            content: "{{ config('privacy.hospital_name', 'HIMS Healthcare') }} — Official Operational Governance";
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #374151;
        }
        @bottom-right {
            content: "Page " counter(page);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            font-weight: 700;
            color: #374151;
        }
    }

    /* Base Document Sheet (Screen Mode) */
    .doc-sheet {
        font-family: Arial, Helvetica, 'Liberation Sans', sans-serif;
        color: var(--doc-ink);
        background-color: #ffffff;
        line-height: 1.55;
    }

    /* Screen Mode Dark Mode: Keep legible on screen without sacrificing print */
    .dark .doc-sheet {
        background-color: #0f172a;
        color: #f1f5f9;
        border-color: #334155;
    }
    .dark .doc-sheet .doc-table th,
    .dark .doc-sheet .doc-meta-label {
        background-color: #1e293b;
        color: #ffffff;
        border-color: #475569;
    }
    .dark .doc-sheet .doc-table td,
    .dark .doc-sheet .doc-meta-value {
        background-color: #0f172a;
        color: #e2e8f0;
        border-color: #334155;
    }
    .dark .doc-sheet .doc-zebra {
        background-color: rgba(30, 41, 59, 0.4);
    }
    .dark .doc-sheet .doc-callout {
        background-color: #1e293b;
        border-color: #475569;
        color: #f1f5f9;
    }
    .dark .doc-sheet .doc-sec-title {
        color: #ffffff;
        border-color: #475569;
    }
    .dark .doc-sheet .doc-masthead {
        border-color: #f1f5f9;
    }
    .dark .doc-sheet .doc-rule {
        border-color: #475569;
    }

    /* Common Document Element Styles (Screen + Baseline) */
    .doc-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
        line-height: 1.45;
    }
    .doc-table th {
        background-color: var(--doc-bg-header);
        color: var(--doc-heading);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-size: 8.5pt;
        padding: 5.5pt 7.5pt;
        border: 1pt solid var(--doc-line);
        vertical-align: top;
    }
    .doc-table td {
        padding: 5.5pt 7.5pt;
        border: 1pt solid var(--doc-line-light);
        color: var(--doc-ink);
        vertical-align: top;
    }
    .doc-meta-label {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 8.5pt;
        letter-spacing: 0.03em;
        background-color: var(--doc-bg-header);
        color: var(--doc-heading);
        border: 1pt solid var(--doc-line) !important;
        padding: 5pt 7pt !important;
    }
    .doc-meta-value {
        font-size: 9.5pt;
        color: var(--doc-ink);
        border: 1pt solid var(--doc-line-light) !important;
        padding: 5pt 7pt !important;
    }
    .doc-callout {
        border: 1pt solid var(--doc-line);
        border-left: 3.5pt solid var(--doc-accent);
        background-color: #f8fafc;
        padding: 8pt 10pt;
        margin: 7pt 0;
        font-size: 9.5pt;
        line-height: 1.5;
        color: var(--doc-ink);
    }
    .doc-body-p {
        font-size: 10.5pt;
        line-height: 1.55;
        color: var(--doc-ink);
        text-align: justify;
        margin-bottom: 8pt;
    }
    .doc-sec-title {
        font-size: 13pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--doc-heading);
        border-bottom: 1.25pt solid var(--doc-heading);
        padding-bottom: 2.5pt;
        margin-top: 14pt;
        margin-bottom: 6pt;
    }
    .doc-subsec-title {
        font-size: 10.5pt;
        font-weight: 700;
        color: var(--doc-heading);
        margin-top: 6pt;
        margin-bottom: 2pt;
    }

    /* ==========================================================================
       STRICT PRINT & PDF EXPORT ISOLATION
       Forces high-contrast pure light theme, defeating all dark-mode styles,
       Tailwind dark:* classes, transparent/muted text, and browser quirks.
       ========================================================================== */
    @media print {
        /* Reset print viewport and destroy screen UI */
        html, body {
            background: #ffffff !important;
            background-color: #ffffff !important;
            color: #111827 !important;
            color-scheme: light !important;
            font-family: Arial, Helvetica, 'Liberation Sans', sans-serif !important;
            font-size: 10.5pt !important;
            line-height: 1.55 !important;
            padding: 0 !important;
            margin: 0 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .no-print,
        .no-print * {
            display: none !important;
        }

        /* Enforce paper sheet format on print */
        .doc-sheet {
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
            background: #ffffff !important;
            background-color: #ffffff !important;
            color: #111827 !important;
        }

        /* DEFEAT ANY LEAKING DARK-MODE OR MUTED UTILITIES ON ALL DESCENDANTS */
        .doc-sheet,
        .doc-sheet * {
            color: #111827 !important;
            text-shadow: none !important;
            filter: none !important;
            opacity: 1 !important;
            box-sizing: border-box !important;
        }

        /* High-Contrast Headings & Authority Elements */
        .doc-sheet h1,
        .doc-sheet h2,
        .doc-sheet h3,
        .doc-sheet h4,
        .doc-sheet strong,
        .doc-sheet b,
        .doc-sheet th,
        .doc-sec-title,
        .doc-subsec-title {
            color: #000000 !important;
            font-weight: 700 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .doc-sec-title {
            border-bottom: 1.25pt solid #000000 !important;
            color: #000000 !important;
        }

        .doc-masthead {
            border-bottom: 2pt solid #000000 !important;
        }

        .doc-rule {
            border-top: 1pt solid #4b5563 !important;
        }

        /* Body Paragraphs & Lists */
        .doc-sheet p,
        .doc-sheet li,
        .doc-body-p {
            color: #1f2937 !important;
            font-size: 10.5pt !important;
            line-height: 1.55 !important;
            text-align: justify !important;
        }

        /* Tables & Metadata Contrast */
        .doc-table {
            width: 100% !important;
            border-collapse: collapse !important;
            border: 1pt solid #374151 !important;
        }

        .doc-table th {
            background-color: #f3f4f6 !important;
            color: #000000 !important;
            font-weight: 700 !important;
            border: 1pt solid #4b5563 !important;
            padding: 5pt 7pt !important;
            font-size: 8.5pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .doc-table td {
            background-color: #ffffff !important;
            color: #111827 !important;
            border: 1pt solid #6b7280 !important;
            padding: 5pt 7pt !important;
            font-size: 9pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .doc-meta-label {
            background-color: #f3f4f6 !important;
            color: #000000 !important;
            font-weight: 700 !important;
            border: 1pt solid #4b5563 !important;
            font-size: 8.5pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .doc-meta-value {
            background-color: #ffffff !important;
            color: #111827 !important;
            font-weight: 500 !important;
            border: 1pt solid #6b7280 !important;
            font-size: 9pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .doc-zebra {
            background-color: #f9fafb !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .doc-callout {
            border: 1pt solid #4b5563 !important;
            border-left: 3.5pt solid #111827 !important;
            background-color: #f9fafb !important;
            color: #111827 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Page Breaks Hygiene */
        h1, h2, h3, h4,
        .doc-sec-title,
        .doc-subsec-title {
            page-break-after: avoid !important;
            break-after: avoid !important;
        }

        .keep-together,
        .doc-table,
        .doc-sig-block,
        .doc-control-block,
        .doc-callout,
        tr,
        section {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .page-break {
            page-break-before: always !important;
            break-before: page !important;
        }

        a {
            text-decoration: none !important;
            color: inherit !important;
        }

        /* Suppress printed URL strings */
        a[href]:after {
            content: none !important;
        }
    }
</style>
