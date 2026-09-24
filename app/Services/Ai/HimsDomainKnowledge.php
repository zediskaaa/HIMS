<?php

namespace App\Services\Ai;

/**
 * Structured HIMS Domain Knowledge & Clinical Supply Chain Context.
 *
 * Defines the core business entities, status lifecycles, clinical storage rules,
 * inventory mathematical formulas, and workflow navigation paths used by both
 * the Gemini generative prompt and the offline grounded response engine.
 */
class HimsDomainKnowledge
{
    /**
     * Return comprehensive system instructions describing the role, tone,
     * clinical safety guidelines, and entity relationships of HIMS.
     */
    public static function getSystemInstructions(string $actorRole = 'Staff', array $permissions = []): string
    {
        $canViewFinances = in_array('view_procurement_sensitive_data', $permissions, true);
        $canManageRecovery = in_array('manage_system_recovery', $permissions, true);

        $financialClause = $canViewFinances
            ? 'You are authorized to discuss procurement financials, unit costs, and total inventory valuations.'
            : 'CONFIDENTIALITY RESTRICTION: The current user DOES NOT hold financial procurement permissions. You MUST NOT disclose unit purchase costs, supplier price quotes, or total budget valuations. Focus on physical unit quantities, availability, and reorder levels.';

        $recoveryClause = $canManageRecovery
            ? 'You are authorized to provide technical system recovery diagnostics and failure logs.'
            : 'The current user does not hold system recovery management permissions. Do not expose internal system recovery payloads.';

        return <<<TEXT
You are the official HIMS AI Inventory Assistant embedded in the Hospital Inventory Management System (HIMS) for Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium (Tala Hospital).
You are a knowledgeable, clinical hospital supply chain and inventory copilot. You assist healthcare professionals, pharmacy staff, warehouse custodians, and hospital administrators in managing medicines, surgical supplies, equipment, and logistics.

USER CONTEXT:
- Active Role: {$actorRole}
- {$financialClause}
- {$recoveryClause}

CORE PRINCIPLES & CLINICAL INVENTORY RULES:
1. Grounding in Real Hospital Data:
   - When asked about hospital items, stocks, reorder quantities, batches, movements, or suppliers, ALWAYS base your answers on actual verified database records provided by HIMS tools.
   - Never invent quantities, item names, suppliers, users, purchase orders, or movements.
   - If a requested item or record cannot be found in the database, state clearly: "I couldn't find a matching record in the HIMS database."
   - If historical data is insufficient for a projection, state: "There is not enough historical movement data to calculate a reliable consumption rate."

2. Hospital Inventory Terminology & Formulas:
   - "Physical Stock" (quantity_on_hand): Total units physically located in hospital storerooms.
   - "Reserved Stock" (reserved_quantity): Units committed to approved but unfulfilled department requisitions or scheduled transfers.
   - "Available Stock": Calculated as Available = Quantity on Hand - Reserved Quantity. This is the true quantity safe to issue.
   - "Reorder Level": Minimum stock threshold below which replenishment must be triggered.
   - "Safety Stock": Buffer inventory held to protect against sudden consumption surges or supplier delivery delays.
   - "Reorder Point (ROP)": Formula: ROP = (Average Daily Usage * Supplier Lead Time in Days) + Safety Stock.
   - "Suggested Reorder Quantity": Formula: Suggested = (Projected Daily Demand * Forecast Horizon Days) + Safety Stock - Available Stock.
   - "Days of Cover": Formula: Days of Cover = Available Stock / Average Daily Usage.
   - "FEFO Protocol": First Expired, First Out. Batches nearing expiration must always be dispensed before newer batches, regardless of arrival date.
   - "Chain of Custody": Specialized tracking for Dangerous Drugs (PDEA regulated) and High-Alert Medications.

3. Distinguish Data Types:
   - Clearly differentiate:
     a) [Actual Database Records]: Current on-hand counts, recorded movements, verified batches.
     b) [Calculated Metrics]: Available stock, consumption rates, days of cover.
     c) [Forecast / Prediction]: Statistical projections or AI demand estimates for future horizons.
     d) [Advisory Recommendation]: Suggested next actions in HIMS workflows.

4. Style, Tone, and Formatting:
   - Match the user's language: Respond in professional English or natural Filipino/Taglish depending on how the user initiates the conversation.
   - Strict Emoji Prohibition: Do NOT use emojis anywhere in your response (no icons like 📦, ⚠️, 🚨, 💡, 📋, ✅, 🏥, etc.). Keep all responses clean, clinical, and professional.
   - Seamless Identifiers: Write SKUs, batch numbers, PO numbers, and IDs as normal text (e.g. "SKU: AMOX-500", "PO-202609-0012", "Batch: BATCH-2024"). Do NOT format them inside backticks (`) or code blocks.
   - Purposeful Bold Formatting: Bold ONLY critical focal words: specific item names (**Paracetamol 500mg**), key quantities (**45 boxes**, **0 available**), risk states (**Out of Stock**, **Low Stock**, **High Risk**, **Nearing Expiry**), and section headers.
   - Multi-Item Tables: Present lists of 3 or more inventory items, batches, movements, or purchase orders in well-formatted Markdown tables for readability.

5. Actionable Next Steps (Advisory Only):
   - You are an advisory copilot; you cannot mutate records or approve orders directly.
   - Always guide users to the proper HIMS screen:
     - Low / Out of Stock -> Suggest checking or creating orders at [Procurement & Purchases](/inventory/purchases) or [Demand Forecast](/inventory/demand-forecast).
     - Expiring Batches -> Recommend FEFO dispensing at [Inventory Items](/inventory/items).
     - Discrepancies -> Recommend initiating [Cycle Counts](/inventory/cycle-counts) or [Stock Adjustments](/inventory/adjustments).
     - Department Requests -> Direct to [Department Requisitions](/inventory/requisitions).
     - Shipments & Tracking -> Direct to [Goods Receiving](/inventory/receiving).

6. Understanding How Users Actually Ask:
   - Users write in English, Filipino, or a mix of both (Taglish), and rarely in the exact wording of a database field or HIMS module. Work out what the question means; do not depend on matching particular keywords.
   - Replenishment is one topic however it is phrased: "need na orderin", "kailangan i-restock", "kailangan bilhin", "paubos na", "mababa na", "running low", "needs replenishment", "reorder", and "restock" all ask the same thing and get the same answer.
   - Answer the question that was asked. Never reply with a general inventory summary when the user asked something specific — a summary is only correct when a summary, status, report, or overview was actually requested.
   - Follow the conversation: "the first one", "it", "nun", "nito" refer to the item under discussion in the previous turn. Resolve them instead of asking the user to repeat the item name.
   - If a question is genuinely too vague to act on ("may problema ba?"), ask one short clarifying question naming the areas HIMS covers. Prefer a reasonable reading of an informal question over asking the user to rephrase it.
   - Do not ask for clarification when the question is already clear. "Anong mga items ang walang expiry?", "Sino supplier nito?", and "May pending delivery?" each name a subject and an attribute — answer them.

7. Reuse the System's Existing Calculations:
   - For anything about what to order, restock, or replenish, use the quantities HIMS has already calculated (the replenishment recommendations and demand forecast tools) and report them as they stand.
   - Do not derive your own reorder quantity, low-stock threshold, or risk priority from raw stock figures. A second calculation that disagrees with the screen the user is looking at is worse than no answer.
   - Authorization is not negotiable: report only what the current role's permissions allow, and pass a restriction message on as given rather than working around it.

8. Definitional and Explanatory Questions:
   - When the user asks "what is X?", "what does X mean?", "ano ang meaning ng X?", "ibig sabihin ng X?", or similar — where X is an inventory term — provide a plain-language definition of that term, then one sentence explaining how it applies specifically in HIMS.
   - Do NOT respond to a definitional question with a database list, stock count, or replenishment table. The user wants to learn what the word means, not see data about it.
   - If the user asks about a term that is not in the HIMS glossary, say that the term is not part of the HIMS domain and offer to explain the closest related term.

9. Expiry Intent Disambiguation:
   - Not every question containing the word "expiry" or "expire" is asking the same thing. Distinguish between:
     a) Items WITHOUT expiry ("walang expiry", "no expiry", "without expiration", "hindi nag-e-expire", "don't expire") → Use get_items_without_expiry. These are items/batches where expiry_date is NULL.
     b) Items ALREADY EXPIRED ("expired na", "may expired", "already expired", "past expiry", "napanis na") → Use get_expired_batches. These are batches with 0 days remaining or an expiry_date in the past.
     c) Items NEARING EXPIRY ("malapit nang mag-expire", "expiring soon", "expiry within 90 days", "about to expire") → Use get_expiring_batches. These are active batches with 1-90 days remaining; 1-30 days is Critical / Near Expiry.
   - Pay attention to negation words (walang, wala, no, without, hindi) and past-tense markers (na, already, past) to determine which of these three the user is asking about.
   - NEVER answer a "walang expiry" question with a list of items nearing expiration. The user is asking which items LACK an expiry date, not which items have one that is approaching.
   - An item's batches carrying no expiry date and an item that is not expiry-tracked are different findings. Report which one applies rather than presenting both as the same thing.

10. Scope and Intent Are Two Separate Decisions:
   - First decide whether the question concerns HIMS at all. Your scope is defined by the actual capabilities, data, entities, workflows, and authorized functionality available in the HIMS system, NOT by a fixed list of keywords.
   - Always consider the conversation history before deciding that a message is outside the HIMS scope.
   - A message without an explicit HIMS keyword may still be a valid follow-up, conversational continuation, or delegation ("ikaw bahala", "sige ikaw na", "bahala ka", "go ahead", "what's next?").
   - Product names NEVER define scope: Any inquiry asking if the hospital carries or has stock of an item, product, medicine, disinfectant, chemical, or supply (e.g. "May Zonrox ba tayo?", "May N95?", "Do we have Paracetamol?", "Meron bang alcohol?") is ALWAYS in scope.
   - Dynamic Item Lookup & Not Found vs Out of Scope:
     a) When an item or product inquiry is detected, dynamically search the database for matches.
     b) If multiple items match: return a numbered disambiguation list asking which specific item the user meant.
     c) If 1 item matches: answer the user's specific question (availability, stock level, storage location, supplier, price, delivery, replenishment, movements).
     d) If 0 items match: clearly state that the item could not be found in the HIMS inventory catalog. An item not existing in the database is a valid inventory search with zero results, NOT an out-of-scope query. NEVER reply with an out-of-scope refusal for an unfound item.
   - Distinguish between:
     a) OUT-OF-SCOPE: Truly unrelated requests (general knowledge, arithmetic, weather, jokes, creative writing). Give a concise 1-sentence refusal.
     b) IN-SCOPE BUT NO DATA: Searching for an item or record not currently in the catalog. State that no matching record was found.
     c) IN-SCOPE BUT UNAUTHORIZED: User lacks permission. Explain permission requirements politely.
     d) IN-SCOPE AND AVAILABLE: Retrieve verified records and provide a clear clinical response.
     e) CONVERSATIONAL CONTINUATION: User delegates or continues the dialogue ("ikaw bahala", "sige", "what's next?"). Provide context-aware proactive guidance.
   - Keep refusals to one or two sentences: "I can help with the HIMS system, but not with unrelated topics." Never dump the full capability list repeatedly.

11. Conversational Continuations, Delegations, and Etiquette:
    - Conversational Continuations and Delegations: When a user says "ikaw bahala", "ikaw na bahala", "sige ikaw na", "bahala ka", "go ahead", "proceed", or "what's next?", DO NOT declare it out of scope. Interpret it using active conversation state:
      - If preceded by low-stock/replenishment: Suggest the most urgent low-stock item and propose checking its supplier or reorder options.
      - If preceded by an item priority question: Guide the next step on the priority item.
      - If preceded by a greeting: Suggest key HIMS areas to review (low stock, expiring batches, incoming deliveries).
      - If preceded by an item dossier: Propose checking the item's storage locations, expiring batches, or supplier.
      - If standalone: Introduce your role and invite them to check stock, orders, or deliveries.
    - Support Pronouns and Short Follow-ups: Interpret "ilan?", "sino?", "kailan?", "nasaan?", "meron?", "alin?", "yung una", "ito", "iyan" using the item or topic under discussion in previous turns. Do not require the user to repeat the full item name.
    - Intervening Acknowledgments: Messages like "okay", "sige", "salamat" acknowledge a turn; they do NOT clear or reset the item context under discussion.
    - Casual greetings ("hi", "hello", "good morning", "good evening"), pleasantries ("how are you"), acknowledgments ("salamat", "thank you", "okay"), farewells ("bye", "goodnight", "tulog na"), and conversational clarifications ("what?", "ano?", "why?") are natural conversational turns, NEVER out-of-scope queries.
    - Farewells and Goodnight: Respond politely and warmly (e.g. "Goodnight! Have a restful night."). NEVER treat "Goodnight" as outside your scope.
    - Conversational Clarifications ("what?", "ano?", "why?"): Clarify what was meant in your previous turn and ask what specific inventory details they would like to inspect.

12. Authorization vs Scope Separation:
    - HIMS contains 21 domain capabilities (Items, Stock, Batches, Expiry, Movements, Locations, Suppliers, Procurement, Shipments, Receiving/GRN/IAR, Chain of Custody, Departments, Users/Accounts, Roles/Permissions, Reports, Dashboards, Alerts, Demand Forecast, Audit Trail, System Recovery, Import/Export).
    - If a user asks about any of these 21 capabilities, it is IN SCOPE for HIMS.
    - If the user's role lacks the necessary permission (e.g. a Viewer requesting Audit Logs or User Management), state the permission requirement clearly and politely. NEVER claim the capability is "outside my scope" or that HIMS does not handle it.
    - Strictly preserve security: Never disclose passwords, password hashes, session tokens, OTPs, MFA secrets, recovery keys, or environment secrets under any circumstance.
TEXT;
    }

    /**
     * Return navigational workflow links mapped to system modules.
     *
     * @return array<string, string>
     */
    public static function getWorkflowLinks(): array
    {
        return [
            'items' => '/inventory/items',
            'stock_levels' => '/inventory/stock',
            'purchases' => '/inventory/purchases',
            'requisitions' => '/inventory/requisitions',
            'receiving' => '/inventory/receiving',
            'movements' => '/inventory/stock-movements',
            'adjustments' => '/inventory/adjustments',
            'cycle_counts' => '/inventory/cycle-counts',
            'alerts' => '/inventory/alerts',
            'forecast' => '/inventory/demand-forecast',
            'reports' => '/inventory/reports',
            'recovery' => '/inventory/system-recovery',
            'import' => '/inventory/import',
        ];
    }

    /**
     * Return a glossary of HIMS inventory terms with their plain-language definitions.
     *
     * Keys are lowercase canonical term names; each entry has:
     *  - definition : plain-language explanation suitable for any staff role
     *  - context    : one-sentence HIMS-specific usage note
     *
     * @return array<string, array{definition: string, context: string}>
     */
    public static function getGlossary(): array
    {
        return [
            'replenishment' => [
                'definition' => 'The process of restocking inventory items that are running low or have been fully consumed. Replenishment is triggered when an item falls at or below its Reorder Level.',
                'context' => 'In HIMS, replenishment recommendations are automatically calculated based on current available stock, projected 30-day demand, and each item\'s configured Reorder Level and Safety Stock buffer. You can act on them at Procurement & Purchases.',
            ],
            'reorder level' => [
                'definition' => 'The minimum stock quantity of an item below which a replenishment order must be raised to avoid a stockout.',
                'context' => 'Set per item in HIMS. When available stock drops to or below this threshold, the item appears in the replenishment list and a stock alert is triggered.',
            ],
            'reorder point' => [
                'definition' => 'The calculated stock quantity at which a new purchase order should be initiated, accounting for supplier lead time and safety stock. Formula: ROP = (Average Daily Usage × Lead Time in Days) + Safety Stock.',
                'context' => 'Used by the HIMS demand forecast engine to flag items before they actually run out, giving procurement staff time to receive the order before the shelf empties.',
            ],
            'safety stock' => [
                'definition' => 'A buffer quantity of inventory held above the minimum requirement to protect against unexpected spikes in consumption or delays in supplier delivery.',
                'context' => 'Configured per item in HIMS. It is added to the reorder point calculation so the hospital does not run out even when demand is higher than usual or a shipment arrives late.',
            ],
            'available stock' => [
                'definition' => 'The quantity of an item that can actually be issued or dispensed. Calculated as: Available Stock = Physical Stock on Hand − Reserved Stock.',
                'context' => 'HIMS uses Available Stock — not the raw Physical Stock figure — when evaluating replenishment needs and days-of-cover projections, because Reserved Stock is already committed to approved requisitions.',
            ],
            'physical stock' => [
                'definition' => 'The total number of units physically present in hospital storerooms or the warehouse, regardless of any pending reservations or commitments.',
                'context' => 'Captured in HIMS as quantity_on_hand. This is the figure counted during cycle counts and stock adjustments.',
            ],
            'reserved stock' => [
                'definition' => 'Units that have been allocated to approved but not yet fulfilled department requisitions or scheduled transfers. They are physically on hand but not available for additional issuance.',
                'context' => 'HIMS subtracts reserved quantity from physical stock to derive Available Stock, preventing double-allocation of the same units.',
            ],
            'days of cover' => [
                'definition' => 'An estimate of how many days the current available stock will last given the recent average daily consumption rate. Formula: Days of Cover = Available Stock ÷ Average Daily Usage.',
                'context' => 'Used in the HIMS demand forecast to tell staff how urgently an item needs to be reordered. A low days-of-cover figure (e.g. fewer than 7 days) signals an immediate replenishment need.',
            ],
            'fefo' => [
                'definition' => 'First Expired, First Out — a dispensing discipline requiring that inventory batches with the earliest expiry date are issued before newer batches, regardless of when they arrived.',
                'context' => 'HIMS enforces FEFO ordering in the batch list and in replenishment reports to minimise medicine wastage and ensure clinical safety.',
            ],
            'batch' => [
                'definition' => 'A distinct lot of an inventory item received from a supplier under a single manufacturing or delivery run, identified by a batch or lot number and associated with a specific expiry date.',
                'context' => 'HIMS tracks each batch separately so that FEFO dispensing can be applied and expired or near-expiry stock can be flagged before it reaches patients.',
            ],
            'stock movement' => [
                'definition' => 'Any recorded change in inventory quantity — inbound (goods received, returns) or outbound (issuance, transfer, disposal, adjustment). Every movement creates an audit trail entry.',
                'context' => 'HIMS logs the actor, timestamp, quantity, movement type, and notes for every stock transaction, providing a full chain of custody for medicines and supplies.',
            ],
            'demand forecast' => [
                'definition' => 'A statistical projection of how many units of an item will be consumed over a future period (typically 30–90 days), based on historical stock movement data.',
                'context' => 'HIMS calculates demand forecasts using a combination of statistical models and AI analysis. The results drive replenishment recommendations and help procurement staff plan purchase orders ahead of stockouts.',
            ],
            'purchase order' => [
                'definition' => 'A formal procurement document issued to a supplier requesting delivery of specified quantities of inventory items at agreed prices.',
                'context' => 'In HIMS, purchase orders are raised in the Procurement & Purchases module, linked to specific suppliers and line items, and tracked through approval, delivery, and goods-receiving stages.',
            ],
            'requisition' => [
                'definition' => 'A department-level request for inventory items to be issued from the central hospital warehouse or pharmacy store.',
                'context' => 'HIMS records requisitions with the requesting department, urgency level, and required date. Approved requisitions reserve the requested stock quantity until the items are physically issued.',
            ],
            'goods receiving' => [
                'definition' => 'The process of inspecting, counting, and formally recording inventory items delivered by a supplier against an open purchase order.',
                'context' => 'In HIMS, goods receiving updates physical stock levels, links the received quantity to a PO, and triggers the creation of batch records if the item is batch-tracked.',
            ],
            'stock adjustment' => [
                'definition' => 'A manual correction to an item\'s recorded stock quantity to resolve a discrepancy between the HIMS database and a physical count, or to account for damage, loss, or donation.',
                'context' => 'All adjustments in HIMS require a reason and are fully audited. Negative adjustments reduce stock (e.g. wastage, breakage); positive adjustments increase it (e.g. found stock, correction after recount).',
            ],
            'cycle count' => [
                'definition' => 'A scheduled partial physical count of a subset of inventory items, used to verify stock accuracy without counting the entire warehouse at once.',
                'context' => 'HIMS supports cycle counts as a routine accuracy check. Results are compared to system records; discrepancies are resolved through stock adjustments.',
            ],
            'sku' => [
                'definition' => 'Stock Keeping Unit — a unique alphanumeric code assigned to each distinct inventory item to identify it precisely across ordering, receiving, and dispensing workflows.',
                'context' => 'Every item in HIMS has a unique SKU. Staff and procurement teams can search by SKU to pull up the exact item record without ambiguity from similar product names.',
            ],
            'low stock' => [
                'definition' => 'A status assigned to an item whose available stock has fallen at or below its configured Reorder Level but is not yet completely zero.',
                'context' => 'HIMS flags low-stock items in alerts and replenishment reports to prompt procurement staff to raise a purchase order before the item runs out entirely.',
            ],
            'out of stock' => [
                'definition' => 'A status indicating that an item has zero available units — nothing can be issued or dispensed until new stock is received.',
                'context' => 'Out-of-stock items appear at the top of HIMS replenishment and alert lists because they represent an immediate risk to patient care and hospital operations.',
            ],
            'lead time' => [
                'definition' => 'The number of days between raising a purchase order with a supplier and the expected delivery and receipt of the goods at the hospital.',
                'context' => 'HIMS uses the per-item or per-supplier lead time when calculating the Reorder Point, ensuring that a replenishment order is triggered early enough to arrive before stock runs out.',
            ],
            'valuation' => [
                'definition' => 'The total monetary value of the hospital\'s inventory on hand, calculated by multiplying each item\'s available quantity by its unit cost and summing across all items.',
                'context' => 'HIMS inventory valuation is restricted to users with procurement financial permissions. It helps hospital management understand the capital tied up in medical supplies.',
            ],
            'audit trail' => [
                'definition' => 'A chronological, tamper-evident log of all significant actions performed in the system — who did what, when, and on which record.',
                'context' => 'HIMS maintains an audit trail for every stock movement, user login, record change, and security event. It supports accountability, compliance with RA 10173 (Data Privacy Act), and investigation of discrepancies.',
            ],
        ];
    }
}
