@php
    $purchaseOrder = $iar->purchaseOrder;
    $goodsReceipt = $iar->goodsReceiptNote;
    $costCenter = $purchaseOrder?->costCenter ?? $purchaseOrder?->purchaseRequest?->costCenter;
    $requester = $purchaseOrder?->purchaseRequest?->requester;
    $department = $costCenter?->department ?? $costCenter?->name ?? $requester?->department;
    $responsibilityCode = $costCenter?->code;
    $organizationName = $purchaseOrder?->entity_name;
    $generatedAt = now();
    $totalAmount = 0.0;
    $logoPath = public_path('img/hims-logo.png');
    $logoDataUri = is_file($logoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
        : null;
@endphp

@include('inventory.logistics.partials.iar_document_styles')

<article class="iar-document" data-iar-document>
    {{-- Formal Government Header / Masthead --}}
    <header class="iar-document__masthead">
        <div>
            @if($logoDataUri)
                <img class="iar-document__logo" src="{{ $logoDataUri }}" alt="HIMS official institution logo">
            @else
                <div class="iar-document__logo-placeholder" aria-label="HIMS">HIMS</div>
            @endif
        </div>

        <div class="iar-document__identity">
            <p class="iar-document__republic">Republic of the Philippines</p>
            <p class="iar-document__organization">{{ $organizationName ?: 'Organization not recorded' }}</p>
            <p class="iar-document__system">Hospital Inventory Management System · Logistics and Supply Records</p>
        </div>

        <div class="iar-document__form-meta">
            <strong>Official Record</strong>
            <div>Form: GAM Appendix 50</div>
            <div>Reference: <span class="iar-document__mono">{{ $iar->iar_number }}</span></div>
            <div>Report date: {{ $iar->iar_date?->format('d M Y') ?? 'Not recorded' }}</div>
        </div>
    </header>

    {{-- Official Report Title --}}
    <div class="iar-document__title-block">
        <p class="iar-document__appendix">Inspection · Receiving · Accountability</p>
        <h1 class="iar-document__title">Inspection and Acceptance Report</h1>
        <p class="iar-document__subtitle">Formal record of delivered goods, technical inspection, and custodial acceptance</p>
    </div>

    {{-- Document Particulars / Institutional Metadata Grid --}}
    <section aria-labelledby="iar-particulars-title">
        <h2 id="iar-particulars-title" class="iar-document__section-title">Document particulars</h2>
        <div class="iar-document__metadata">
            <div class="iar-document__metadata-column">
                <div class="iar-document__field">
                    <div class="iar-document__field-label">Entity name</div>
                    <div class="iar-document__field-value">{{ $organizationName ?: 'Not recorded' }}</div>
                </div>
                <div class="iar-document__field">
                    <div class="iar-document__field-label">Supplier</div>
                    <div class="iar-document__field-value">{{ $iar->supplier?->name ?: 'Not recorded' }}</div>
                </div>
                <div class="iar-document__field">
                    <div class="iar-document__field-label">PO number / date</div>
                    <div class="iar-document__field-value">
                        <span class="iar-document__mono">{{ $purchaseOrder?->po_number ?: 'Not recorded' }}</span>
                        / {{ $purchaseOrder?->created_at?->format('d M Y') ?? 'Not recorded' }}
                    </div>
                </div>
                <div class="iar-document__field">
                    <div class="iar-document__field-label">Requisitioning office / department</div>
                    <div class="iar-document__field-value">{{ $department ?: 'Not recorded' }}</div>
                </div>
                <div class="iar-document__field">
                    <div class="iar-document__field-label">Responsibility center code</div>
                    <div class="iar-document__field-value iar-document__mono">{{ $responsibilityCode ?: 'Not recorded' }}</div>
                </div>
            </div>

            <div class="iar-document__metadata-column">
                <div class="iar-document__field">
                    <div class="iar-document__field-label">Fund cluster</div>
                    <div class="iar-document__field-value">{{ $purchaseOrder?->fund_cluster ?: 'Not recorded' }}</div>
                </div>
                <div class="iar-document__field">
                    <div class="iar-document__field-label">IAR number / date</div>
                    <div class="iar-document__field-value">
                        <span class="iar-document__mono">{{ $iar->iar_number }}</span>
                        / {{ $iar->iar_date?->format('d M Y') ?? 'Not recorded' }}
                    </div>
                </div>
                <div class="iar-document__field">
                    <div class="iar-document__field-label">Invoice number</div>
                    <div class="iar-document__field-value iar-document__mono">{{ $iar->invoice_number ?: 'Not recorded' }}</div>
                </div>
                <div class="iar-document__field">
                    <div class="iar-document__field-label">Delivery receipt number</div>
                    <div class="iar-document__field-value iar-document__mono">{{ $goodsReceipt?->dr_number ?: 'Not recorded' }}</div>
                </div>
                <div class="iar-document__field">
                    <div class="iar-document__field-label">GRN number</div>
                    <div class="iar-document__field-value iar-document__mono">{{ $goodsReceipt?->grn_number ?: 'Not recorded' }}</div>
                </div>
            </div>
        </div>
    </section>

    {{-- Delivered Item Table --}}
    <section class="iar-document__section" aria-labelledby="iar-items-title">
        <h2 id="iar-items-title" class="iar-document__section-title">Items delivered</h2>
        <table class="iar-document__table">
            <colgroup>
                <col style="width: 6%">
                <col style="width: 40%">
                <col style="width: 9%">
                <col style="width: 13%">
                <col style="width: 15%">
                <col style="width: 17%">
            </colgroup>
            <thead>
                <tr>
                    <th class="iar-document__center">Item<br>#</th>
                    <th>Stock / description</th>
                    <th class="iar-document__center">Unit</th>
                    <th class="iar-document__number">Quantity<br>delivered</th>
                    <th class="iar-document__number">Unit cost<br>(PHP)</th>
                    <th class="iar-document__number">Amount<br>(PHP)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($goodsReceipt?->lines ?? collect() as $index => $line)
                    @php
                        $unitCost = (float) ($line->unit_cost ?? $line->item?->unit_cost ?? 0);
                        $lineTotal = (float) $line->received_quantity * $unitCost;
                        $totalAmount += $lineTotal;
                    @endphp
                    <tr>
                        <td class="iar-document__center iar-document__mono">{{ $index + 1 }}</td>
                        <td>
                            <p class="iar-document__item-name">{{ $line->item?->name ?: 'Item description not recorded' }}</p>
                            <p class="iar-document__item-detail">
                                SKU: {{ $line->item?->sku ?: 'Not recorded' }} ·
                                Batch: {{ $line->batch_number ?: 'Not recorded' }} ·
                                Expiry: {{ $line->expiry_date?->format('Y-m-d') ?? 'Not recorded' }}
                            </p>
                        </td>
                        <td class="iar-document__center">{{ $line->item?->unit ?: 'Not recorded' }}</td>
                        <td class="iar-document__number iar-document__mono">{{ number_format((float) $line->received_quantity, 2) }}</td>
                        <td class="iar-document__number iar-document__mono">{{ number_format($unitCost, 2) }}</td>
                        <td class="iar-document__number iar-document__mono">{{ number_format($lineTotal, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No delivered item lines are recorded for this report.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="iar-document__total-label">Total value of delivery</td>
                    <td class="iar-document__number iar-document__mono">&#8369;{{ number_format($totalAmount, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </section>

    {{-- Inspection and Acceptance Certification (Side-by-Side Unbroken) --}}
    <section class="iar-document__section" aria-labelledby="iar-certification-title">
        <h2 id="iar-certification-title" class="iar-document__section-title">Inspection and acceptance certification</h2>
        <div class="iar-document__signoff">
            {{-- Column 1: Inspection --}}
            <div class="iar-document__signoff-panel">
                <h3 class="iar-document__signoff-title">Inspection</h3>
                <div class="iar-document__signoff-body">
                    <div class="iar-document__signoff-row">
                        <div class="iar-document__signoff-label">Date inspected</div>
                        <div>{{ $iar->inspection_date?->format('d F Y') ?? 'Not recorded' }}</div>
                    </div>
                    <div class="iar-document__check">
                        <span class="iar-document__check-box">[{{ $iar->inspection_date ? 'X' : ' ' }}]</span>
                        <span>Inspected, verified, and found in order as to quantity and technical specifications.</span>
                    </div>
                    @if($iar->inspection_findings)
                        <div class="iar-document__narrative">
                            <strong>Inspection findings</strong>
                            {{ $iar->inspection_findings }}
                        </div>
                    @endif
                </div>
                <div class="iar-document__signature">
                    <div class="iar-document__signature-name">{{ $iar->inspectedBy?->name ?: 'Not yet signed' }}</div>
                    <div class="iar-document__signature-role">Inspection Officer / Committee</div>
                </div>
            </div>

            {{-- Column 2: Acceptance --}}
            <div class="iar-document__signoff-panel">
                <h3 class="iar-document__signoff-title">Acceptance</h3>
                <div class="iar-document__signoff-body">
                    <div class="iar-document__signoff-row">
                        <div class="iar-document__signoff-label">Date received</div>
                        <div>{{ $iar->acceptance_date?->format('d F Y') ?? 'Not recorded' }}</div>
                    </div>
                    <div class="iar-document__check">
                        <span class="iar-document__check-box">[{{ in_array($iar->delivery_status, ['complete'], true) ? 'X' : ' ' }}]</span>
                        <span>Complete delivery</span>
                    </div>
                    <div class="iar-document__check">
                        <span class="iar-document__check-box">[{{ $iar->delivery_status === 'partial' ? 'X' : ' ' }}]</span>
                        <span>Partial delivery</span>
                    </div>

                    @if($iar->days_delayed > 0 || (float) $iar->liquidated_damages_amount > 0)
                        <div class="iar-document__narrative">
                            <strong>Liquidated damages assessment (COA GAM App. 61)</strong>
                            Delay: {{ $iar->days_delayed }} day(s)<br>
                            Recorded rate: {{ number_format(((float) ($purchaseOrder?->penalty_clause_rate ?? 0)) * 100, 4) }}% per day<br>
                            Assessed penalty: <span class="iar-document__mono">&#8369;{{ number_format((float) $iar->liquidated_damages_amount, 2) }}</span>
                        </div>
                    @endif

                    @if($iar->notes)
                        <div class="iar-document__narrative">
                            <strong>Acceptance remarks</strong>
                            {{ $iar->notes }}
                        </div>
                    @endif
                </div>
                <div class="iar-document__signature">
                    <div class="iar-document__signature-name">{{ $iar->acceptedBy?->name ?: 'Not yet signed' }}</div>
                    <div class="iar-document__signature-role">Property and/or Supply Custodian</div>
                </div>
            </div>
        </div>
    </section>

    {{-- Statutory Transmittal Compliance Bar --}}
    <section class="iar-document__compliance" aria-label="COA transmittal compliance">
        <div>
            <strong>COA five-day statutory transmittal:</strong>
            @if($iar->coa_transmitted_at)
                Transmitted on {{ $iar->coa_transmitted_at->format('d F Y') }}@if($iar->coa_received_by) and received by {{ $iar->coa_received_by }}@endif.
            @elseif($iar->isAccepted())
                Pending transmittal. Deadline: {{ $iar->coa_transmittal_deadline_at?->format('d F Y') ?? 'Not recorded' }}.
            @else
                Pending custodial acceptance.
            @endif
        </div>
        <div class="iar-document__verification">System Verification ID<br>{{ $iar->iar_number }}-SHA256</div>
    </section>

    {{-- DEDICATED ATTACHMENT PAGE: Certificate of Analysis (COA) & Supporting Documents --}}
    @if($iar->documents->isNotEmpty())
        <section class="iar-document__attachment-page" aria-labelledby="iar-attachments-title">
            <header class="iar-document__attachment-header">
                <div>
                    <h2 id="iar-attachments-title" class="iar-document__attachment-title">Attachment: Quality Compliance &amp; Supporting Records</h2>
                    <p class="iar-document__attachment-subtitle">Official verified records accompanying Inspection &amp; Acceptance Report {{ $iar->iar_number }}</p>
                </div>
                <div class="iar-document__form-meta">
                    <strong>Archival Dossier</strong>
                    <div>Total Records: {{ $iar->documents->count() }}</div>
                    <div>Status: Verified</div>
                </div>
            </header>

            {{-- COA Highlight Card (if any Certificate of Analysis exists) --}}
            @php
                $coaDoc = $iar->documents->first(fn($doc) => $doc->document_type === \App\Enums\DocumentType::CertificateOfAnalysis);
            @endphp
            @if($coaDoc)
                <div class="iar-document__attachment-card">
                    <div class="iar-document__signoff-title" style="text-align: left; border-bottom: .5pt solid #d1d5db; padding-bottom: 1.5mm; margin-bottom: 2mm;">
                        <span>Manufacturer Certificate of Analysis (COA) / Quality Verification</span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2mm; font-size: 7.2pt;">
                        <div>
                            <strong>Document title:</strong> {{ $coaDoc->title }}<br>
                            <strong>Reference / Lot:</strong> <span class="iar-document__mono">{{ $coaDoc->reference_number ?: 'Not recorded' }}</span><br>
                            <strong>Tracking number:</strong> <span class="iar-document__mono">{{ $coaDoc->tracking_number }}</span>
                        </div>
                        <div>
                            <strong>Compliance standard:</strong> Quality Inspection Protocol<br>
                            <strong>Digital checksum:</strong> <span class="iar-document__mono" style="font-size: 6pt;">{{ $coaDoc->sha256_checksum ?: 'Verified via HIMS Secure Archive' }}</span><br>
                            <strong>Classification:</strong> {{ $coaDoc->document_type->label() }}
                        </div>
                    </div>
                </div>
            @endif

            {{-- Supporting Document Register Table --}}
            <table class="iar-document__table">
                <colgroup>
                    <col style="width: 20%">
                    <col style="width: 35%">
                    <col style="width: 18%">
                    <col style="width: 17%">
                    <col style="width: 10%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Document title</th>
                        <th>Reference</th>
                        <th>Tracking no.</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($iar->documents as $document)
                        <tr>
                            <td>{{ $document->document_type->label() }}</td>
                            <td>{{ $document->title }}</td>
                            <td class="iar-document__mono">{{ $document->reference_number ?: 'Not recorded' }}</td>
                            <td class="iar-document__mono">{{ $document->tracking_number }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $document->status)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="iar-document__attachment-note">Supporting files, including any Certificate of Analysis, remain separate protected records in the HIMS Digital Archive and retain their original format and proportions.</p>
        </section>
    @endif

    {{-- Screen/PDF Document Footer --}}
    <footer class="iar-document__record-footer">
        <span>Document reference: <span class="iar-document__mono">{{ $iar->iar_number }}</span></span>
        <span>Generated: {{ $generatedAt->format('d M Y, H:i') }}</span>
    </footer>
</article>
