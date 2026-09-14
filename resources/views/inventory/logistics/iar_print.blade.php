<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $iar->iar_number }} — Inspection and Acceptance Report</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            background: #f1f5f9;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            padding: 24px 0;
        }
        @media print {
            body {
                padding: 0 !important;
                margin: 0 !important;
                background: #ffffff !important;
            }
        }
    </style>
</head>
<body>
    @include('inventory.logistics.partials.iar_document')

    @if($autoPrint)
        <script>
            window.addEventListener('load', () => window.print(), { once: true });
        </script>
    @endif
</body>
</html>
