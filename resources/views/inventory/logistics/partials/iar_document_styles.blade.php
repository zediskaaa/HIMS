<style>
    .iar-document,
    .iar-document * {
        box-sizing: border-box;
    }

    .iar-document {
        --iar-ink: #111827;
        --iar-muted: #4b5563;
        --iar-line: #6b7280;
        --iar-border-light: #9ca3af;
        --iar-soft: #f9fafb;
        --iar-accent: #1e40af;
        width: 216mm;
        min-height: 279mm;
        margin: 0 auto;
        padding: 9mm 11mm 11mm;
        background: #ffffff;
        color: var(--iar-ink);
        font: 8pt/1.3 Arial, Helvetica, sans-serif;
        box-shadow: 0 4px 24px rgba(15, 23, 42, .12);
    }

    .iar-document p,
    .iar-document h1,
    .iar-document h2,
    .iar-document h3 {
        margin: 0;
    }

    /* Masthead Header */
    .iar-document__masthead {
        display: grid;
        grid-template-columns: 18mm minmax(0, 1fr) 42mm;
        gap: 3mm;
        align-items: center;
        padding-bottom: 2.5mm;
        border-bottom: 1.2pt solid var(--iar-ink);
    }

    .iar-document__logo {
        width: 15mm;
        height: 15mm;
        object-fit: contain;
    }

    .iar-document__logo-placeholder {
        width: 15mm;
        height: 15mm;
        border: .75pt solid var(--iar-line);
        display: grid;
        place-items: center;
        color: var(--iar-muted);
        font-size: 6.5pt;
        font-weight: 700;
        letter-spacing: .08em;
    }

    .iar-document__identity {
        text-align: center;
        overflow-wrap: anywhere;
    }

    .iar-document__republic {
        color: var(--iar-muted);
        font: 7pt Georgia, 'Times New Roman', serif;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .iar-document__organization {
        margin-top: .4mm !important;
        font: 700 10.5pt/1.2 Georgia, 'Times New Roman', serif;
        text-transform: uppercase;
    }

    .iar-document__system {
        margin-top: .3mm !important;
        color: var(--iar-muted);
        font-size: 6.8pt;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .iar-document__form-meta {
        border-left: .75pt solid var(--iar-line);
        padding-left: 2.5mm;
        font-size: 6.8pt;
        line-height: 1.4;
    }

    .iar-document__form-meta strong {
        display: block;
        color: var(--iar-accent);
        font-size: 7.5pt;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    /* Title Block */
    .iar-document__title-block {
        padding: 2.5mm 0 2mm;
        text-align: center;
    }

    .iar-document__appendix {
        color: var(--iar-accent);
        font-size: 6.5pt;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .iar-document__title {
        margin-top: .6mm !important;
        font: 700 13pt/1.15 Georgia, 'Times New Roman', serif;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .iar-document__subtitle {
        margin-top: .5mm !important;
        color: var(--iar-muted);
        font-size: 7.2pt;
    }

    /* Document Sections */
    .iar-document__section {
        margin-top: 2.5mm;
    }

    .iar-document__section-title {
        padding: 1.2mm 2mm;
        border: .75pt solid var(--iar-line);
        border-bottom: 0;
        background: var(--iar-soft);
        color: var(--iar-ink);
        font-size: 7pt;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    /* Metadata Grid */
    .iar-document__metadata {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border: .75pt solid var(--iar-line);
    }

    .iar-document__metadata-column + .iar-document__metadata-column {
        border-left: .75pt solid var(--iar-line);
    }

    .iar-document__field {
        display: grid;
        grid-template-columns: 32mm minmax(0, 1fr);
        min-height: 5.5mm;
        border-bottom: .5pt solid #d1d5db;
    }

    .iar-document__metadata-column .iar-document__field:last-child {
        border-bottom: 0;
    }

    .iar-document__field-label,
    .iar-document__field-value {
        padding: 1.1mm 1.8mm;
        overflow-wrap: anywhere;
    }

    .iar-document__field-label {
        background: #fafafa;
        color: #374151;
        font-size: 6.8pt;
        font-weight: 700;
    }

    .iar-document__field-value {
        border-left: .5pt solid #d1d5db;
        font-size: 7.2pt;
    }

    .iar-document__mono {
        font-family: 'Courier New', Courier, monospace;
        font-variant-numeric: tabular-nums;
    }

    /* Table Styles */
    .iar-document__table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        border: .75pt solid var(--iar-ink);
        font-size: 7.2pt;
    }

    .iar-document__table thead {
        display: table-header-group;
    }

    .iar-document__table tfoot {
        display: table-row-group;
    }

    .iar-document__table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .iar-document__table th,
    .iar-document__table td {
        border: .5pt solid var(--iar-line);
        padding: 1.4mm 1.5mm;
        vertical-align: top;
        overflow-wrap: anywhere;
    }

    .iar-document__table th {
        background: var(--iar-soft);
        font-size: 6.5pt;
        font-weight: 700;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .iar-document__table .iar-document__number {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .iar-document__table .iar-document__center {
        text-align: center;
    }

    .iar-document__item-name {
        font-weight: 700;
        color: #111827;
    }

    .iar-document__item-detail {
        margin-top: .4mm !important;
        color: var(--iar-muted);
        font-size: 6.5pt;
    }

    .iar-document__table tfoot td {
        border-top: 1.2pt solid var(--iar-ink);
        background: #fafafa;
        font-weight: 700;
    }

    .iar-document__total-label {
        text-align: right;
        text-transform: uppercase;
        font-weight: 700;
        font-size: 7pt;
    }

    /* Inspection & Acceptance Signoff Panels */
    .iar-document__signoff {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border: .75pt solid var(--iar-ink);
        margin-top: 2.5mm;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .iar-document__signoff-panel {
        display: flex;
        min-height: 48mm;
        flex-direction: column;
        padding: 2.5mm;
    }

    .iar-document__signoff-panel + .iar-document__signoff-panel {
        border-left: .75pt solid var(--iar-ink);
    }

    .iar-document__signoff-title {
        padding-bottom: 1.2mm;
        border-bottom: .75pt solid var(--iar-line);
        text-align: center;
        font-size: 7.5pt;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .iar-document__signoff-body {
        flex: 1;
        padding-top: 1.5mm;
        font-size: 7pt;
    }

    .iar-document__signoff-row {
        display: grid;
        grid-template-columns: 26mm minmax(0, 1fr);
        gap: 1.5mm;
        margin-bottom: 1.2mm;
    }

    .iar-document__signoff-label {
        font-weight: 700;
    }

    .iar-document__check {
        display: grid;
        grid-template-columns: 5mm minmax(0, 1fr);
        gap: 1mm;
        margin-top: 1.2mm;
        line-height: 1.3;
    }

    .iar-document__check-box {
        font: 700 7.5pt 'Courier New', monospace;
        white-space: nowrap;
    }

    .iar-document__narrative {
        margin-top: 1.5mm;
        padding: 1.4mm 1.8mm;
        border: .5pt solid #d1d5db;
        background: #fafafa;
        overflow-wrap: anywhere;
        font-size: 6.8pt;
    }

    .iar-document__narrative strong {
        display: block;
        margin-bottom: .4mm;
        font-size: 6.5pt;
        text-transform: uppercase;
    }

    .iar-document__signature {
        margin-top: 3.5mm;
        text-align: center;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .iar-document__signature-name {
        min-height: 5.5mm;
        padding: 0 2mm .8mm;
        border-bottom: .75pt solid var(--iar-ink);
        font-weight: 700;
        font-size: 7.8pt;
        overflow-wrap: anywhere;
    }

    .iar-document__signature-role {
        padding-top: .8mm;
        color: var(--iar-muted);
        font-size: 6.5pt;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    /* Statutory Compliance Bar */
    .iar-document__compliance {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 3mm;
        align-items: start;
        padding: 1.8mm 2.5mm;
        border: .75pt solid var(--iar-line);
        background: var(--iar-soft);
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        font-size: 6.8pt;
        margin-top: 2mm;
    }

    .iar-document__compliance strong {
        color: var(--iar-ink);
    }

    .iar-document__verification {
        color: var(--iar-muted);
        font: 6pt/1.3 'Courier New', Courier, monospace;
        text-align: right;
        overflow-wrap: anywhere;
    }

    /* Dedicated COA & Supporting Documents Attachment Page */
    .iar-document__attachment-page {
        page-break-before: always;
        break-before: page;
        margin-top: 6mm;
        padding-top: 5mm;
        border-top: .75pt dashed #cbd5e1;
    }

    .iar-document__attachment-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding-bottom: 2mm;
        border-bottom: 1.2pt solid var(--iar-ink);
        margin-bottom: 3mm;
    }

    .iar-document__attachment-title {
        font: 700 11.5pt/1.2 Georgia, 'Times New Roman', serif;
        text-transform: uppercase;
        color: var(--iar-ink);
    }

    .iar-document__attachment-subtitle {
        color: var(--iar-muted);
        font-size: 7pt;
        margin-top: .4mm !important;
    }

    .iar-document__attachment-card {
        border: .75pt solid var(--iar-line);
        padding: 3mm;
        margin-bottom: 3mm;
        background: #fff;
    }

    .iar-document__attachment-note {
        margin-top: 1.2mm !important;
        color: var(--iar-muted);
        font-size: 6.5pt;
    }

    .iar-document__record-footer {
        display: flex;
        justify-content: space-between;
        gap: 4mm;
        margin-top: 3mm;
        padding-top: 1.5mm;
        border-top: .5pt solid #d1d5db;
        color: var(--iar-muted);
        font-size: 6.5pt;
    }

    @media screen and (max-width: 850px) {
        .iar-document {
            width: 100%;
            min-height: 0;
            padding: 16px;
        }
    }

    @media print {
        @page {
            size: Letter portrait;
            margin: 10mm 12mm 12mm 12mm;

            @bottom-left {
                content: "IAR {{ $iar->iar_number }}  |  Generated {{ now()->format('Y-m-d H:i') }}";
                color: #5b6472;
                font: 6.5pt Arial, Helvetica, sans-serif;
            }

            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
                color: #5b6472;
                font: 6.5pt Arial, Helvetica, sans-serif;
            }
        }

        body {
            padding: 0 !important;
            margin: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
        }

        .iar-document {
            width: 100% !important;
            max-width: 100% !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: none !important;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .iar-document__attachment-page {
            border-top: none !important;
            padding-top: 0 !important;
            margin-top: 0 !important;
        }

        .iar-document__record-footer {
            display: none !important;
        }
    }
</style>
