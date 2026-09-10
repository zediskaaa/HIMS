# Supplier / Vendor Management: Research and Design Basis

Last reviewed: 2026-09-10

## Scope and organization assumption

The repository is a Laravel 12 hospital inventory system branded in parts as DJNRMHS, but its legal pages still contain `[Hospital / Healthcare Facility Name]` placeholders and say statutory public-procurement rules apply only where applicable. That is not enough evidence to classify this deployment as a government procuring entity. The supplier master therefore implements a generally applicable hospital workflow and records organization-specific evidence as documents. It does not make PhilGEPS or government-bidding documents universally mandatory.

## Researched process

The implemented lifecycle is:

1. Procurement staff creates a supplier record as an accreditation draft.
2. Business identity, minimal contacts, product scope, and applicable documents are collected.
3. Uploaded documents remain pending until a reviewer verifies or rejects them. Upload is never treated as verification.
4. Procurement submits the supplier for accreditation review.
5. An authorized approver records an approval or rejection. Approval requires explicit confirmation that requirements applicable to that supplier and its products were reviewed; any document marked required for accreditation must be current and verified.
6. Only an operationally active supplier with approved, unexpired accreditation and no expired/rejected blocking document is eligible for new procurement.
7. Products, supplier catalog information, product-level lead time, time-bounded prices, contracts, and commercial terms are maintained without overwriting the inventory item master.
8. Accreditation and document expiry are derived from their own validity dates. There is no invented universal validity period.
9. An approver may suspend procurement use for a recorded reason and later reactivate a still-compliant supplier. Inactivation preserves all history.
10. Performance is derived only from real purchase/receiving records. Where the current transaction model lacks expected dates, inspection, accepted quantity, or rejected quantity, the UI reports that the metric is unavailable rather than manufacturing a rating.
11. A submitted profile must identify its business structure, provide an address, and provide at least one active email or phone channel. This is a HIMS review-readiness control, not a claim that those three fields alone satisfy legal eligibility.
12. A material legal/compliance identity change after approval returns the supplier to draft; a pending review must first be rejected/returned before those fields can change. This prevents a prior decision from silently covering a different identity or regulatory scope.

This is a controlled onboarding and qualification process, not a claim that every private or public hospital uses identical committee names or approval titles.

## Sources consulted

### Current Philippine public procurement

- [Republic Act No. 12009, New Government Procurement Act](https://lawphil.net/statutes/repacts/ra2024/ra_12009_2024.html).
- [GPPB announcement of the approved RA 12009 IRR](https://www.gppb.gov.ph/exciting-announcement-issuance-and-publication-of-the-irr-for-the-new-government-procurement-act/): the GPPB approved the IRR on 4 February 2025; it was published on 10 February 2025.
- [GPPB Public Advisory No. 09-2026](https://www.gppb.gov.ph/public-advisory-no-09-2026/): the March 2026 “1st Edition” was withdrawn; users must rely on the GPPB-approved 2025 IRR, effective 25 February 2025.
- [GPPB Non-Policy Matter Opinion No. 001-2026](https://www.gppb.gov.ph/wp-content/uploads/2026/02/NPM-No.-001-2026_Redacted.pdf): under the approved IRR, a valid and updated PhilGEPS Platinum certificate establishes legal eligibility at bid opening, while technical and financial capability evidence remains procurement-specific.
- [GPPB Circular No. 06-2026](https://www.gppb.gov.ph/wp-content/uploads/2026/06/Annex-A-Circular-No.-06-2026.pdf): current documentary requirements vary by procurement mode and project type.

### Philippine health-product regulation

- [Republic Act No. 9711, FDA Act of 2009](https://lawphil.net/statutes/repacts/ra2009/ra_9711_2009.html): covered health-product establishments may not manufacture, import, distribute, transfer, or retail regulated products without the applicable FDA authorization; products that require registration may not be supplied unregistered.
- [FDA Administrative Order No. 2024-0015](https://www.fda.gov.ph/administrative-order-no-2024-0015-prescribing-the-rules-requirements-and-procedures-in-the-application-for-license-to-operate-of-covered-health-product-establishments-with-the-food-and-drug-adm/): current LTO rules for covered establishments; it expressly repeals AO 2020-0017.
- [FDA Advisory No. 2023-2238](https://www.fda.gov.ph/fda-advisory-no-2023-2238-utilization-of-the-food-and-drug-administration-fda-verification-portal/): the public FDA Verification Portal can be used to validate licensed establishments and registered/notified health products.
- [FDA Verification Portal](https://verification.fda.gov.ph/): official source for establishment LTO and product authorization checks.

### Business identity and privacy

- [DTI Business Name Registration FAQ](https://bnrs.dti.gov.ph/faq): DTI business-name registration applies when a sole proprietor trades under a name other than the proprietor's true name; it is not itself a permit to operate.
- [SEC Company Registration System](https://appointment.sec.gov.ph/online-services/sec-company-registration-system/): SEC is the registry for corporations and partnerships, including foreign corporations doing business in the Philippines.
- [BIR New Business Registration](https://web-services.bir.gov.ph/newbizreg/): BIR registration requirements vary between sole proprietors and non-individual entities.
- [Data Privacy Act of 2012](https://privacy.gov.ph/data-privacy-act/) and its [Implementing Rules and Regulations](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/): processing must follow transparency, legitimate purpose, proportionality/data minimization, security, and retention limits.
- [NPC guidance on third parties](https://privacy.gov.ph/third-parties/): organizations must exercise due diligence and maintain safeguards when third parties process personal data.

### Healthcare procurement practice

- [WHO procurement-agency guidance](https://extranet.who.int/prequal/medicines/procurement-agencies): procurement organizations should operate a quality-assurance system, qualify products and manufacturers/suppliers, confirm ability to supply and registration status, and use agreements defining responsibilities.
- [WHO Model Quality Assurance System for Procurement Agencies](https://iris.who.int/bitstream/handle/10665/69721/WHO_PSM_PAR_2007.3_eng.pdf?isAllowed=y&sequence=1): recommends continuous monitoring of product/supplier compliance, delivery schedules, lead time, complaints, costs, and contract adherence, with periodic requalification.

## Classification of requirements

### Legally or regulatorily required when applicable

- RA 12009 and its approved IRR apply to Philippine government procuring entities, not automatically to a private hospital. PhilGEPS and bid eligibility evidence are therefore conditional on the deploying organization and procurement method.
- FDA establishment and product authorizations apply when the supplier activity and health product fall within FDA regulation. They are not blanket requirements for every vendor (for example, a general office-supply or non-regulated service provider).
- The correct business-registration source depends on legal form: DTI business-name registration for qualifying sole proprietors using a business name, SEC registration for corporations/partnerships, and other registries where applicable. A DTI certificate alone is not a permit to operate.
- Supplier-contact personal data is subject to the Data Privacy Act. Only operationally necessary contact details are collected; uploaded evidence is kept on the private filesystem and served only through an authorized controller.

### Industry-standard procurement practice

- Separate supplier creation, evidence collection, verification, approval, procurement eligibility, monitoring, requalification, suspension, and retirement.
- Qualify both the supplier and the product/source where patient safety depends on the health product.
- Maintain time-bounded prices, lead times, contracts, and evidence rather than overwriting history.
- Monitor delivery and quality performance from transactions and inspections, and disclose when the source data is insufficient.

### HIMS controls implemented

- Existing roles are retained. `ManageSuppliers` maintains records; a new compliance-review permission verifies evidence; a separate approval permission decides accreditation and operational suspension/reactivation. The server also prevents an uploader from reviewing the same file and prevents a supplier creator, submitter, document uploader, or document verifier from recording that accreditation decision.
- The procurement request, supplier quote, and purchase-order APIs require `ManageProcurement`, matching the server-rendered workflow. They do not expose destructive delete routes, and database foreign keys prevent supplier deletion from erasing or detaching procurement attribution.
- No document type is globally hard-coded as mandatory. A reviewer identifies which uploaded evidence is required for a particular accreditation and whether its lapse blocks procurement. The approving user must attest that applicable requirements were reviewed.
- Reviewed document versions are immutable. Renewal creates a new pending version, retains the old evidence, and carries forward required/blocking controls. Current duplicate type/reference combinations are rejected.
- Procurement eligibility is computed from operational status, accreditation decision/expiry, and blocking-document state. Merely creating a row or uploading a file never makes a supplier eligible.
- A scheduled daily check creates persistent in-app alerts 30 days before recorded accreditation, document, and active-contract expiry dates, promotes expired blocking evidence to critical severity, and resolves alerts when the underlying date/status no longer qualifies. It does not email external parties because the repository has no approved procurement-notification recipient policy.
- Supplier deletion is not exposed. Inactivation preserves procurement, pricing, contract, document, and accreditation history.
- Audit events are recorded at successful business boundaries with allowlisted, non-sensitive values; file contents and unnecessary personal details are not logged.
- Supplier identity duplicates are blocked using a normalized tax identifier when supplied, otherwise a normalized legal-name/address fingerprint. Database uniqueness is the final race-safe guard.
- Price history is time-bounded. Overlapping periods for the same product, currency, quantity tier, and contract context are rejected; inactive products and inactive, expired, or not-yet-started contracts cannot receive a current price.

### Optional or organization-specific

- PhilGEPS Platinum, tax clearance, bid security, and other RA 12009 evidence are configured/recorded only if this deployment is a government procuring entity and the procurement method requires them.
- FDA LTO and product authorization records are collected only for covered suppliers/products.
- ISO or other quality certificates, bank information, weighted evaluation scorecards, and supplier portals are not made mandatory by this module.

## Data design decisions

- `suppliers` remains the master identity and gains accreditation/operational fields instead of conflating row existence with approval.
- Contacts are separate to support procurement, sales, and finance contacts without repeating supplier identity.
- Documents preserve verification, validity, issuer, private storage reference, and reviewer attribution.
- Document renewals explicitly supersede a current version. Historical files remain accessible, while only the current version participates in eligibility and expiry alerts; inherited required/blocking controls prevent renewal from bypassing compliance review.
- Accreditation decisions are append-only cycle records so renewal and rejection history survive later status changes.
- Supplier products link to the existing inventory master and hold catalog, manufacturer/brand, pack, MOQ, and product-level lead time.
- Prices are child history records with effective dates and optional contract reference; they never update `inventory_items.unit_cost`.
- Contracts are procurement-reference records, not generated legal instruments.

## Future integration

- Purchase Requests and RFQs should query the procurement-eligible supplier scope and supplier-product capability.
- Quotations can later promote accepted quote lines into effective supplier-price records.
- Purchase Orders should retain the supplier and supplier-product/price/contract references used at award time.
- Receiving and inspection should add expected delivery, delivered/accepted/rejected quantities, damage/quality outcomes, and reason codes. Those facts can then calculate on-time delivery, fill rate, rejection rate, and lead-time variance transparently.
- Inventory keeps its existing item master and movement ledger. Supplier product/catalog data remains a relationship, not a duplicate item master.
- Finance can later reference contracts and payment terms; this module stores no bank credentials or payment-processing data.

## Known limitation

The current HIMS purchase-order/receiving schema records ordered quantity and a receipt timestamp, but not promised delivery date, accepted/rejected quantity, inspection result, or return reason linked to the PO. Consequently, a defensible performance score cannot yet be calculated. The profile reports real order/receipt counts and explicitly marks unsupported metrics unavailable.

The pre-existing `inventory_items.supplier_id` column is retained for backward compatibility and historical item display. New supplier capability, catalog, pack, lead-time, and pricing data is authoritative in `supplier_products` and `supplier_prices`; new assignments through the legacy item form are limited to procurement-eligible suppliers. Removing or backfilling the legacy column requires a separately reviewed data migration.
