<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * Works out what a naturally phrased HIMS question is actually asking for.
 *
 * The assistant is asked things like "May item ba na need na talagang orderin?"
 * and "Ano yung mga paubos na?" — Filipino, English, or the two mixed, with no
 * shared vocabulary and no fixed question shape. Recognising those by exact
 * keyword ("reorder", "low stock", "restock") only ever catches the phrasing
 * whoever wrote the list happened to have in mind; everything else fell past
 * every branch and landed on the daily-status summary, which is how a valid
 * replenishment question ended up answered with a stock count.
 *
 * So intent is resolved instead of keywords matched. Each intent owns a small
 * lexicon of cues in four tiers:
 *
 *  - `patterns` — regular expressions, the strongest signal. These reframe a
 *                 topic word rather than mention it, so they outweigh one:
 *                 "past their expiry" is an already-expired question even
 *                 though it contains "expiry".
 *  - `phrases`  — multi-word phrases ("running low").
 *  - `terms`    — distinctive single words ("reorder", "paubos", "replenish").
 *  - `weak`     — words that only hint at the intent on their own ("order",
 *                 "status"), useful when nothing stronger is present.
 *
 * Single words match on their stem, so affixes and Filipino verbal forms still
 * land: the `order` stem catches order, orders, orderin, orderan, i-order and
 * ordering. The highest-scoring intent wins, ties break by the order intents
 * are declared in (most specific first), and a question that matches nothing is
 * answered as a clarification or as out of scope — never with an unrelated
 * summary of the day.
 */
class ConversationalIntentResolver
{
    public const CAPABILITIES = 'capabilities';

    public const DEFINITION = 'definition';

    public const REPLENISHMENT = 'replenishment';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const NO_EXPIRY = 'no_expiry';

    public const EXPIRED = 'expired';

    public const EXPIRY = 'expiry';

    public const SUPPLIER = 'supplier';

    public const MOVEMENTS = 'movements';

    public const PROCUREMENT = 'procurement';

    public const REQUISITIONS = 'requisitions';

    public const SHIPMENTS = 'shipments';

    public const VALUATION = 'valuation';

    public const FORECAST = 'forecast';

    public const AUDIT = 'audit';

    public const RECOVERY = 'recovery';

    public const ATTENTION = 'attention';

    public const SUMMARY = 'summary';

    public const LOCATION = 'location';

    public const ITEM_LOOKUP = 'item_lookup';

    public const GREETING = 'greeting';

    public const ACKNOWLEDGMENT = 'acknowledgment';

    public const FAREWELL = 'farewell';

    public const HOW_ARE_YOU = 'how_are_you';

    public const CLARIFY = 'clarify';

    public const CONVERSATIONAL_CLARIFY = 'conversational_clarify';

    public const CONVERSATIONAL_CONTINUE = 'conversational_continue';

    public const OUT_OF_SCOPE = 'out_of_scope';

    private const PHRASE_WEIGHT = 3;

    private const TERM_WEIGHT = 2;

    private const WEAK_WEIGHT = 1;

    /**
     * Weight of a `patterns` cue.
     *
     * Higher than a phrase because these cues reframe a topic word rather than
     * merely mentioning it. "Are there items past their expiry date?" contains
     * "expiry date", which on its own scores for nearing-expiry; the "past
     * their ... expiry" framing is what makes it an already-expired question,
     * so it has to outweigh the plain mention of the word.
     */
    private const PATTERN_WEIGHT = 6;

    /**
     * Explicit "explain this term" cues. These stand on their own: a question
     * carrying one is definitional whatever else it mentions.
     *
     * @var array<int, string>
     */
    private const DEFINITION_CUES = [
        'meaning', 'kahulugan', 'ibig sabihin', 'define', 'definition',
        'paliwanag', 'what does it mean', 'what do you mean by', 'what is meant by',
        'paano gumagana', 'paano ito gumagana', 'ano ang ibig sabihin',
    ];

    /**
     * Question openers a bare definitional question can start with. They are
     * only meaningful together with a glossary term — on their own they are
     * just how a Filipino or English question begins.
     *
     * @var array<int, string>
     */
    private const DEFINITION_OPENERS = [
        'ano ang', 'ano yung', 'ano ba ang', 'anong', 'ano',
        'what is', 'what are', "what's", 'whats', 'what',
        'define', 'meaning of', 'meaning ng', 'kahulugan ng', 'kahulugan ng mga',
        'ibig sabihin ng', 'ibig sabihin',
    ];

    /**
     * Words that carry no subject in a definitional question, so they are
     * dropped before checking whether what remains is a glossary term.
     *
     * @var array<int, string>
     */
    private const DEFINITION_STOPWORDS = [
        'ang', 'yung', 'iyong', 'ng', 'nito', 'ito', 'iyan', 'iyon', 'sa', 'ba', 'po',
        'mga', 'the', 'a', 'an', 'is', 'are', 'of', 'for', 'about', 'and', 'at', 'para',
        'mean', 'means', 'meaning', 'ibig', 'sabihin', 'kahulugan', 'paliwanag',
        'define', 'definition', 'explain', 'ito', 'yan',
    ];

    /**
     * Stated expiry horizons, tested in order (most specific phrasing first).
     * Calendar-relative windows resolve at call time, so "this month" means the
     * remainder of the current month rather than a fixed 30 days.
     *
     * @var array<string, int|string>
     */
    private const EXPIRY_WINDOWS = [
        '/\bthis month\b|\bngayong buwan\b|\bngayon buwan\b|\bbuwan na ito\b/' => 'end_of_month',
        '/\bnext month\b|\bsusunod na buwan\b/' => 60,
        '/\bthis week\b|\bngayong linggo\b/' => 7,
        '/\bwithin\s+30\s*days\b|\b30\s*days\b/' => 30,
        '/\bwithin\s+60\s*days\b|\b60\s*days\b/' => 60,
        '/\bwithin\s+90\s*days\b|\b90\s*days\b/' => 90,
        '/\bwithin\s+(?:a|one)\s+year\b|\bwithin\s+12\s*months\b/' => 365,
    ];

    /**
     * Cues per intent, declared most specific first — that order is the
     * tie-breaker when two intents score the same.
     *
     * Tiers: `phrases` (multi-word cues), `patterns` (regex cues, weighted
     * above a phrase — see PATTERN_WEIGHT), `terms` (distinctive words,
     * matched on stem) and `weak` (words that only hint at the intent on
     * their own).
     *
     * @var array<string, array{phrases?: array<int, string>, patterns?: array<int, string>, terms?: array<int, string>, weak?: array<int, string>}>
     */
    private const LEXICON = [
        // "Ano yung mga pwede kong itanong?" asks what this assistant can
        // answer — a question about the assistant itself. It names no HIMS
        // record, so it matched nothing and was answered with the out-of-scope
        // refusal, which tells a user asking how to use the assistant that it
        // cannot help them. The cues are deliberately about asking and
        // answering rather than about the word "pwede" alone, so "ano ang
        // pwede kong i-order?" stays a replenishment question.
        self::CAPABILITIES => [
            'phrases' => [
                'what can you do', 'what can you help', 'how can you help',
                'what are your capabilities', 'what can you do for me',
                'ano ang pwede mong gawin', 'ano ang puwede mong gawin',
                'anong pwede mong gawin', 'anong puwede mong gawin',
                'ano ang kaya mong gawin', 'anong kaya mong gawin',
                'mga pwede kong itanong', 'mga puwede kong itanong',
                'pwede kong itanong', 'puwede kong itanong',
                'ano ang sakop mo', 'anong sakop mo', 'ano ang coverage mo',
                'paano ka makakatulong', 'ano ang maitutulong mo',
                'anong pwede mong sagutin', 'ano ang pwede mong sagutin',
                'pwede mong sagutin', 'puwede mong sagutin', 'kaya mong sagutin',
            ],
            'patterns' => [
                '/\b(?:what|which)\b[^.]{0,40}\b(?:can|could|may)\s+(?:i|we|you)\b[^.]{0,40}\b(?:ask|answer|help)\b/',
                '/\b(?:ano|anong)\b[^.]{0,40}\b(?:pwede|puwede|kaya|maari|maaari)\b[^.]{0,40}\b(?:itanong|tanong|tanung|sagutin|sagot|masagot|tulong|malaman)\b/',
            ],
            // Generic words like 'tanong', 'itanong', 'tulong' are intentionally
            // excluded from terms because they appear in too many non-capability
            // queries. The phrase-level and regex matches above are specific
            // enough to catch real capability questions.
            'terms' => ['capabilities', 'capability'],
        ],

        // Definitional questions must win before any inventory keyword in the
        // query scores a data-retrieval intent. "Ano ang meaning ng replenishment?"
        // contains the word "replenishment", which would otherwise score 2 for
        // the REPLENISHMENT intent and trigger a stock list instead of an
        // explanation. Declaring DEFINITION first and giving its phrases a
        // weight-3 match ensures the correct intent always wins.
        //
        // The lexicon deliberately holds only real definitional cues. Generic
        // question openers ("ano", "ano ang", "ano yung", "what is a", "what
        // are", "what is") used to be listed here as phrases, which made every
        // question that merely opened with them score 3 for DEFINITION — and
        // because DEFINITION is declared first, it then won every tie. That is
        // how "ano yung mga hindi nag-e-expire?" (items without expiry) and
        // "what are the low stock items?" (replenishment) were answered as
        // requests for a definition. A bare "ano ang fefo?" is still a
        // definitional question; looksDefinitional() recognises it from the
        // glossary term it names rather than from the opener.
        self::DEFINITION => [
            'phrases' => [
                'ano ang meaning', 'ano ba meaning', 'what is the meaning', 'what does it mean',
                'what does that mean', 'what do you mean by', 'what is meant by',
                'meaning of', 'meaning ng', 'ibig sabihin', 'ano ang ibig sabihin',
                'define', 'definition of',
                'what do you call', 'paano mo tinutukoy',
                'anong ibig sabihin', 'anong kahulugan',
                'kahulugan ng', 'kahulugan nito', 'itong termino', 'this term',
                'what does fefo mean', 'what does sku mean', 'what does rop mean',
                'what is fefo', 'what is sku', 'what is safety stock', 'what is lead time',
                'what is reorder level', 'what is replenishment', 'what is a batch',
                'what is days of cover', 'what is stock movement', 'what is a requisition',
                'what is a purchase order', 'what is goods receiving', 'what is a cycle count',
                'what is stock adjustment', 'what is demand forecast', 'what is valuation',
                'what is available stock', 'what is physical stock', 'what is reserved stock',
                'what is out of stock', 'what is low stock', 'what is an audit trail',
                'paano gumagana', 'paano ito gumagana',
            ],
            'terms' => ['meaning', 'kahulugan', 'ibig', 'define', 'definition', 'explain', 'paliwanag', 'mean', 'means'],
        ],

        // ask what needs buying. "orderin", "i-restock", "bilhin" and "kulang"
        // are the Tagalog forms of the same question.
        self::REPLENISHMENT => [
            'phrases' => [
                'need to order', 'needs to order', 'need to reorder', 'needs reorder',
                'need to restock', 'needs restocking', 'needs replenishment', 'need replenishment',
                'should we order', 'should we reorder', 'should we restock', 'should we buy',
                'to order', 'to reorder', 'to restock', 'for reorder', 'order more', 'buy more',
                'kailangan orderin', 'kailangan i order', 'kailangan iorder', 'kailangan mag order',
                'kailangan bilhin', 'kailangan i restock', 'kailangan irestock', 'kailangan i reorder',
                'kailangan nating', 'dapat bilhin', 'dapat i order', 'dapat orderin', 'dapat i restock',
                'kailangan bang', 'may kailangan', 'mga kailangan',
                'running low', 'running out', 'low in stock', 'low on stock', 'low stock',
                'below reorder', 'under reorder', 'at reorder', 'critical stock', 'critical na',
                'almost out', 'nearly out', 'few left', 'konti na lang', 'kaunti na lang',
                'onti na lang', 'mababa na', 'paubos na', 'papalapit na',
                'inventory issue', 'inventory issues', 'stock issue', 'stock issues',
            ],
            'terms' => [
                'reorder', 'reorderin', 'restock', 'replenish', 'replenishment',
                'orderin', 'orderan', 'iorder', 'umorder', 'magorder',
                'bilhin', 'bibilhin', 'bilhan', 'kulang', 'kulangan', 'dagdagan',
                'paubos', 'mababa', 'ubos',
            ],
            'weak' => ['order', 'orders', 'ordering', 'bili', 'critical', 'restocking', 'stock'],
        ],

        // Nothing left on the shelf at all — a sharper question than "low",
        // and answered from the zero-quantity rows specifically.
        self::OUT_OF_STOCK => [
            'phrases' => [
                'out of stock', 'out of stocks', 'zero stock', 'no stock', 'without stock',
                'wala nang stock', 'walang stock', 'wala na stock', 'ubos na', 'naubos na',
                'sold out', 'run out', 'ran out',
            ],
            'terms' => ['depleted', 'unavailable', 'naubos', 'ubos', 'zero'],
        ],

        // "walang expiry" / "no expiry" / "without expiry" — items or batches
        // that have no expiry date at all. Must outscore the generic EXPIRY
        // intent so that the negation in "walang expiry" is not ignored.
        self::NO_EXPIRY => [
            'phrases' => [
                'walang expiry', 'walang expiration', 'walang expiration date', 'walang expiry date',
                'wala nang expiry', 'wala silang expiry', 'no expiry', 'no expiration',
                'no expiration date', 'no expiry date', 'without expiry', 'without expiration',
                'without expiration date', 'without an expiry', 'without an expiration',
                // Apostrophes are stripped during normalization, so the
                // normalized text of "don't expire" is "don t expire".
                'don t expire', 'do not expire', 'doesn t expire', 'does not expire',
                'dont expire', 'doesnt expire', 'won t expire',
                'hindi nag expire', 'hindi nag e expire', 'hindi nagexpire', 'hindi nagpapanis',
                'hindi mag expire', 'hindi ma expire', 'hindi ito nag expire', 'hindi napapanis',
                'walang batch expiry', 'non expiring', 'not expiring', 'never expire', 'never expires',
                'no shelf life', 'without shelf life', 'walang shelf life',
                'items na walang expiry', 'items na walang expiration',
                'mga walang expiry', 'mga walang expiration',
            ],
            'terms' => ['walang', 'wala'],
        ],

        // "expired na" / "already expired" / "past expiry" — stock whose
        // expiry_date is in the past. Different from nearing expiry.
        self::EXPIRED => [
            'phrases' => [
                'expired na', 'expired na ba', 'may expired', 'may expired ba', 'may expired stock',
                'already expired', 'past expiry', 'past expiration', 'lapsed', 'beyond expiry',
                'nag expire na', 'naexpire na', 'lumampas na ang expiry', 'expired stock',
                'expired items', 'expired batches', 'expired inventory', 'expired medicine',
                'expired batch', 'napanis na', 'panis na',
            ],
            // "past expiry" was listed above, but a possessive or an article
            // between the two words ("past their expiry date") slipped past it
            // and the question was answered as nearing-expiry instead.
            'patterns' => [
                '/\b(?:past|beyond|after)\s+(?:the\s+|their\s+|its\s+|our\s+|my\s+)?expir/',
                '/\b(?:lumampas|lagpas|nalagpasan|lipas|tapos)\b[^.]{0,24}\bexpir/',
            ],
            'terms' => ['expired'],
        ],

        // Nearing expiry — items/batches whose expiry_date is approaching.
        // This is the "expiring soon" / "malapit nang mag-expire" intent.
        self::EXPIRY => [
            'phrases' => [
                'nearing expiry', 'near expiry', 'expiring soon', 'about to expire', 'going to expire',
                'shelf life', 'expiry date', 'expiration date', 'malapit nang ma expire',
                'malapit na ma expire', 'malapit nang mapanis', 'ma expire', 'maexpire', 'mapapanis',
                'expire within', 'expiring within', 'expire this month', 'expiring this month',
                'mag e expire', 'mag expire', 'malapit na mag expire',
            ],
            'terms' => ['expiry', 'expire', 'expiring', 'expiration', 'fefo', 'lot'],
            'weak' => ['batch', 'batches'],
        ],

        self::SUPPLIER => [
            'phrases' => [
                'who supplies', 'who provides', 'who is the supplier', 'sino ang supplier',
                'sino supplier', 'sino ang nag supply', 'sino nag supply', 'tagapagbigay',
                // "kanino galing tong item?" asks the same question without the
                // English word "supplier" anywhere in it.
                'kanino galing', 'sino galing', 'galing kanino', 'saan galing',
                'sino ang nagbigay', 'sino nagbigay', 'kanino nanggaling',
            ],
            'terms' => ['supplier', 'suppliers', 'vendor', 'vendors', 'distributor', 'pinagkukunan', 'nagbigay'],
        ],

        self::MOVEMENTS => [
            'phrases' => [
                'what happened', 'anong nangyari', 'ano ang nangyari', 'chain of custody',
                'stock movement', 'stock movements', 'movement history', 'stock history',
                'stock adjustment', 'stock adjustments', 'nagalaw', 'gumalaw',
            ],
            'terms' => [
                'movement', 'movements', 'nangyari', 'consumption', 'consumed', 'issued',
                'transferred', 'transfer', 'adjustment', 'adjustments', 'history', 'anomaly',
                'unusual', 'kakaiba',
            ],
        ],

        self::PROCUREMENT => [
            'phrases' => [
                'purchase order', 'purchase orders', 'pending order', 'pending orders',
                'pending po', 'open order', 'open orders', 'incoming stock', 'incoming delivery',
                'naka order', 'may order na', 'may pending', 'ordered na',
            ],
            'terms' => ['procurement', 'purchases', 'purchase', 'ordered', 'incoming'],
        ],

        self::REQUISITIONS => [
            'phrases' => [
                'supply request', 'material request', 'department request', 'department requisition',
                'request from', 'mga hiling', 'kahilingan',
                // Naming a requisition is more specific than the generic "may
                // pending" in the procurement cues, so these have to outscore it.
                'pending requisition', 'pending requisitions', 'may requisition',
                'requisition ba', 'requisition status', 'mga requisition',
            ],
            'terms' => ['requisition', 'requisitions', 'hiling'],
        ],

        self::SHIPMENTS => [
            'phrases' => [
                'delayed shipment', 'delayed delivery', 'delayed shipments', 'pending delivery',
                'pending shipments', 'in transit', 'kailan darating', 'darating na', 'parating na',
                'hindi pa dumating', 'delivery status',
                'may delayed', 'may delayed ba', 'ano yung delayed', 'anong delayed',
                'delayed ba', 'late ba', 'may late',
                'nasaan ang delivery', 'nasaan yung delivery', 'where is the delivery', 'where is the shipment',
            ],
            'patterns' => [
                '/\b(?:nasaan|saan|where\s+is|where\s+are)\b[^.]{0,35}\b(?:delivery|deliveries|shipment|shipments|kargamento)\b/u',
            ],
            'terms' => ['delivery', 'deliveries', 'shipment', 'shipments', 'delayed', 'carrier', 'tracking', 'parating', 'darating'],
        ],

        self::LOCATION => [
            'phrases' => [
                'where is', 'where are', 'where can i find',
                'nasaan ang', 'nasaan yung', 'saan naka store',
                'saan nakatago', 'saan nakalagay', 'saan may stock',
                'which location', 'what location', 'storage location', 'storage locations',
                'stock location', 'saan makikita', 'mga storage location', 'mga location',
                'saan ang mga', 'saan ang', 'list of storage locations',
            ],
            'patterns' => [
                '/\b(?:nasaan|saan)\b[^.]{0,35}\b(?:naka|store|nakatago|nakalagay|stock|makikita|hanapin)\b/u',
                '/\bwhere\s+(?:is|are|can i find)\b[^.]{0,35}\b(?:stored|located|kept|found)\b/u',
            ],
            'terms' => ['location', 'locations', 'nasaan', 'nakalagay', 'nakatago'],
        ],

        self::ITEM_LOOKUP => [
            'phrases' => [
                'check availability', 'item availability', 'check item', 'check stock of',
                'is it in stock', 'available stock of', 'do we carry',
                'tingnan ang stock', 'tignan ang stock',
            ],
            'terms' => ['availability'],
        ],

        self::VALUATION => [
            'phrases' => [
                'total value', 'inventory value', 'inventory valuation', 'stock value',
                'how much is our inventory', 'kabuuang halaga', 'magkano ang inventory',
                'magkano ang stock', 'halaga ng inventory',
            ],
            'terms' => ['valuation', 'magkano', 'halaga', 'worth', 'cost'],
        ],

        self::FORECAST => [
            'phrases' => [
                'demand forecast', 'predicted demand', 'forecasted demand', 'projected demand',
                'demand trend', 'consumption trend', 'tantiya', 'hula',
            ],
            'terms' => ['forecast', 'forecasting', 'predicted', 'prediction', 'demand', 'trend', 'projection', 'projected'],
        ],

        self::AUDIT => [
            'phrases' => [
                'audit trail', 'audit log', 'audit logs', 'who changed', 'who issued',
                'who modified', 'activity log', 'sino nagbago', 'sino ang nag',
            ],
            'terms' => ['audit', 'logs', 'logged'],
        ],

        self::RECOVERY => [
            'phrases' => [
                'system recovery', 'failed job', 'failed jobs', 'system error', 'system issue',
                'system problem', 'may problema sa system', 'system down',
            ],
            'terms' => ['recovery', 'diagnostics'],
        ],

        // "Anything I should know?" — answered with what needs action rather
        // than a recital of totals. Only used when the question also names
        // something in HIMS, so a bare "May problema ba?" still clarifies.
        self::ATTENTION => [
            'phrases' => [
                'anything i should know', 'something wrong', 'anything wrong',
                'may problema', 'may sira', 'anong problema', 'what is wrong', 'whats wrong',
                'dapat bantayan', 'dapat abangan', 'watch out', 'look out for',
            ],
            'terms' => ['issue', 'issues', 'problem', 'problems', 'problema', 'alert', 'alerts', 'notification', 'notifications', 'anomalies'],
        ],

        // Dashboard/overview language only. This is deliberately not a
        // fallback: it answers when the user asked for a summary, and nothing
        // else reaches it.
        self::SUMMARY => [
            'phrases' => [
                'daily status', 'daily summary', 'inventory status', 'inventory summary',
                'stock status', 'stock summary', 'overall status', 'inventory overview',
                'stock overview', 'status report', 'summary report', 'inventory report',
                'status today', 'today status', 'kamusta ang inventory', 'kumusta ang inventory',
                'kamusta inventory', 'kumusta inventory', 'kamusta natin', 'buod ng imbentaryo',
                'how many items', 'total items', 'in storage',
            ],
            'terms' => ['summary', 'summarize', 'summarise', 'overview', 'dashboard', 'buod', 'imbentaryo', 'kamusta', 'kumusta'],
            'weak' => ['status'],
        ],
    ];

    /**
     * Nouns that mark a question as being about HIMS at all. Used only to tell
     * a vague but on-topic question (clarify) from an unrelated one (out of
     * scope), so it stays deliberately broad.
     *
     * @var array<int, string>
     */
    private const HIMS_ENTITIES = [
        'inventory', 'stock', 'stocks', 'item', 'items', 'medicine', 'medicines', 'gamot',
        'supply', 'supplies', 'supplier', 'suppliers', 'order', 'orders', 'procurement',
        'purchase', 'delivery', 'deliveries', 'shipment', 'shipments', 'expiry', 'batch',
        'batches', 'requisition', 'requisitions', 'warehouse', 'storage', 'imbentaryo',
        'receiving', 'equipment', 'consumable', 'consumables', 'sku', 'reorder', 'restock',
        'valuation', 'forecast', 'demand', 'alert', 'alerts', 'audit', 'dispensing', 'pharmacy',
        'location', 'locations', 'storeroom', 'aisle', 'shelf', 'bin', 'brand', 'generic',
        'barcode', 'gtin', 'unit', 'units', 'movement', 'movements', 'transfer', 'transfers',
        'adjustment', 'adjustments', 'cycle', 'count', 'counts', 'hospital', 'clinic', 'ward',
        'department', 'departments', 'vendor', 'vendors', 'po', 'grn', 'receipt', 'inspection',
        'recovery', 'diagnostic', 'diagnostics', 'custody', 'regulated', 'pdea', 'surgical',
        'gloves', 'mask', 'masks', 'alcohol', 'gauze', 'cotton', 'syringe', 'syringes',
        'needle', 'needles', 'bandage', 'bandages', 'dextrose', 'catheter', 'ppe',
        // Allow definitional questions about HIMS terms to be treated as in-scope
        'replenishment', 'fefo', 'safety', 'lead', 'meaning', 'kahulugan', 'ibig',
        // Allow expiry-related phrasing to be treated as in-scope
        'expiry', 'expire', 'expired', 'expiration', 'expiring', 'walang',
        // Filipino/Taglish vocabulary for the same inventory concepts.
        'paubos', 'ubos', 'maubos', 'naubos', 'kulang', 'kulangan', 'gamot', 'medisina',
        'bantayan', 'abangan', 'nangyari', 'orderin', 'orderan', 'bilhin', 'magkano', 'halaga',
        'delayed', 'parating', 'darating', 'presyo', 'nasaan', 'nakatago', 'nakalagay',
    ];

    /**
     * Resolve the question's intent.
     *
     * Scope and intent are two separate decisions. `in_hims_scope` answers "is
     * this about HIMS at all?"; `intent` answers "what exactly is being asked?".
     * A question can be in scope and still not match any specific intent (it
     * becomes a clarification), and a specific intent never needs the user to
     * phrase the question in HIMS vocabulary.
     *
     * @return array{intent: string, confidence: float, score: int, matched: array<int, string>, in_hims_scope: bool, days_ahead: ?int, candidate_item: ?string}
     */
    public function resolve(string $message, ?string $candidateItem = null, ?ConversationState $state = null): array
    {
        $normalized = $this->normalize($message);

        // 1. Explicitly out-of-scope check (math, weather, jokes, creative writing, general non-hospital trivia)
        if ($this->isExplicitlyOutOfScope($normalized)) {
            return [
                'intent' => self::OUT_OF_SCOPE,
                'confidence' => 0.0,
                'score' => 0,
                'matched' => [],
                'in_hims_scope' => false,
                'days_ahead' => null,
                'candidate_item' => null,
                'greeting_prefix' => null,
            ];
        }

        // 2. Conversational continuation / delegation ("ikaw bahala", "sige ikaw na", "bahala ka", "go ahead", "what's next?", "proceed")
        if ($this->isConversationalContinue($message)) {
            return [
                'intent' => self::CONVERSATIONAL_CONTINUE,
                'confidence' => 1.0,
                'score' => self::PATTERN_WEIGHT,
                'matched' => [$normalized],
                'in_hims_scope' => true,
                'days_ahead' => null,
                'candidate_item' => null,
                'greeting_prefix' => null,
            ];
        }

        // 2b. Pure conversational checks (acknowledgments, farewells, how are you, and pure greetings)
        if ($this->isAcknowledgment($message)) {
            // If in active replenishment/action dialogue and user says "sige" / "sure" / "okay", treat as continuation
            if ($state !== null && ($state->isLowStockContext() || $state->lastAssistantAction === 'presented_priority_item')
                && in_array(strtolower(trim($message)), ['sige', 'sige po', 'sure', 'go', 'okay go'], true)) {
                return [
                    'intent' => self::CONVERSATIONAL_CONTINUE,
                    'confidence' => 1.0,
                    'score' => self::PATTERN_WEIGHT,
                    'matched' => [$normalized],
                    'in_hims_scope' => true,
                    'days_ahead' => null,
                    'candidate_item' => null,
                    'greeting_prefix' => null,
                ];
            }

            return [
                'intent' => self::ACKNOWLEDGMENT,
                'confidence' => 1.0,
                'score' => self::PATTERN_WEIGHT,
                'matched' => [$normalized],
                'in_hims_scope' => true,
                'days_ahead' => null,
                'candidate_item' => null,
                'greeting_prefix' => null,
            ];
        }

        if ($this->isFarewell($message)) {
            return [
                'intent' => self::FAREWELL,
                'confidence' => 1.0,
                'score' => self::PATTERN_WEIGHT,
                'matched' => [$normalized],
                'in_hims_scope' => true,
                'days_ahead' => null,
                'candidate_item' => null,
                'greeting_prefix' => null,
            ];
        }

        if ($this->isHowAreYou($message)) {
            return [
                'intent' => self::HOW_ARE_YOU,
                'confidence' => 1.0,
                'score' => self::PATTERN_WEIGHT,
                'matched' => [$normalized],
                'in_hims_scope' => true,
                'days_ahead' => null,
                'candidate_item' => null,
                'greeting_prefix' => null,
            ];
        }

        // Conversational clarification / follow-ups ("what?", "ano?", "why?", "huh?", "pardon?", "come again?")
        if ($this->isConversationalClarification($message)) {
            return [
                'intent' => self::CONVERSATIONAL_CLARIFY,
                'confidence' => 1.0,
                'score' => self::PATTERN_WEIGHT,
                'matched' => [$normalized],
                'in_hims_scope' => true,
                'days_ahead' => null,
                'candidate_item' => null,
                'greeting_prefix' => null,
            ];
        }

        $greetingPrefixInfo = $this->extractGreetingPrefix($message);
        $greetingPrefix = null;
        $evalMessage = $message;

        if ($greetingPrefixInfo !== null) {
            $greetingPrefix = $greetingPrefixInfo['greeting'];
            $remainder = $greetingPrefixInfo['remainder'];

            if ($remainder === '') {
                return [
                    'intent' => self::GREETING,
                    'confidence' => 1.0,
                    'score' => self::PATTERN_WEIGHT,
                    'matched' => [$greetingPrefix],
                    'in_hims_scope' => true,
                    'days_ahead' => null,
                    'candidate_item' => null,
                    'greeting_prefix' => $greetingPrefix,
                ];
            }

            // Combined greeting + inquiry: evaluate the remainder message for domain intent
            $evalMessage = $remainder;
            $normalized = $this->normalize($evalMessage);
        }

        [$scores, $matched] = $this->score($normalized);

        // 3. Identify candidate item if not explicitly supplied (or inherit from active conversation state)
        $candidateItem ??= ($state?->focusItem?->name ?? $this->extractCandidateItem($message));

        $isSubjectless = $this->isSubjectlessFollowup($message);
        $hasOngoingDialogue = $state !== null && $state->hasHistory;

        // Scope detection: in HIMS scope if a candidate item was identified,
        // or any HIMS entity/concept is mentioned, or an inventory inquiry frame is present,
        // or any registered HIMS capability matches in HimsCapabilityRegistry,
        // or it is a subjectless follow-up in an ongoing dialogue
        $inHimsScope = $candidateItem !== null
            || $this->mentionsHimsEntity($normalized)
            || $this->hasInventoryInquiryFrame($normalized)
            || HimsCapabilityRegistry::isHimsScope($message, $candidateItem)
            || ($isSubjectless && $hasOngoingDialogue);

        // DEFINITION is decided by whether the question actually names a term to
        // define — not by which opener it happens to start with.
        $definitional = $this->looksDefinitional($normalized);
        if (! $definitional) {
            unset($scores[self::DEFINITION], $matched[self::DEFINITION]);
        }

        $winner = $definitional ? self::DEFINITION : $this->pickWinner($scores);

        // If no specific domain intent matched, check if HimsCapabilityRegistry identifies a registered domain
        if ($winner === null) {
            $matchingCap = HimsCapabilityRegistry::findMatchingCapability($message);
            if ($matchingCap !== null) {
                $inHimsScope = true;
                $winner = match ($matchingCap['id']) {
                    HimsCapabilityRegistry::STORAGE_LOCATIONS => self::LOCATION,
                    HimsCapabilityRegistry::SUPPLIERS => self::SUPPLIER,
                    HimsCapabilityRegistry::SHIPMENTS_DELIVERIES => self::SHIPMENTS,
                    HimsCapabilityRegistry::PROCUREMENT => self::PROCUREMENT,
                    HimsCapabilityRegistry::STOCK_MOVEMENTS => self::MOVEMENTS,
                    HimsCapabilityRegistry::DEMAND_FORECASTING => self::FORECAST,
                    HimsCapabilityRegistry::AUDIT_TRAIL => self::AUDIT,
                    HimsCapabilityRegistry::SYSTEM_RECOVERY => self::RECOVERY,
                    HimsCapabilityRegistry::DASHBOARDS_AND_KPIS => self::SUMMARY,
                    default => null,
                };
            }
        }

        // Check if subjectless follow-up in ongoing conversation resolves intent from active topic
        if ($winner === null && $hasOngoingDialogue) {
            if ($state->isLowStockContext() && preg_match('/\b(?:alin|which|una|first|mabilis|need)\b/i', $normalized)) {
                $winner = self::REPLENISHMENT;
                $inHimsScope = true;
            } elseif (preg_match('/^(?:talaga(?:\s+ba)?|sure\s+ka(?:\s+ba)?|really)[\s?!.,:;-]*$/i', trim($message))) {
                $winner = self::CONVERSATIONAL_CLARIFY;
                $inHimsScope = true;
            }
        }

        // If no specific domain intent matched, but an item candidate was detected,
        // it resolves directly to ITEM_LOOKUP
        if ($candidateItem !== null && ($winner === null || $winner === self::CLARIFY || $winner === self::OUT_OF_SCOPE)) {
            $intent = self::ITEM_LOOKUP;
            $inHimsScope = true;
            $score = self::PHRASE_WEIGHT;
        } else {
            $intent = $winner;
            $score = $intent !== null ? ($scores[$intent] ?? 0) : 0;
        }

        // If a specific intent was recognised, the query is definitively in scope
        if ($intent !== null && $intent !== self::CLARIFY && $intent !== self::OUT_OF_SCOPE) {
            $inHimsScope = true;
        }

        // A question about "issues" only means HIMS inventory issues when it
        // actually names something in HIMS; on its own it is too vague to
        // answer, so it becomes a clarification instead.
        if ($intent === self::ATTENTION && ! $this->mentionsHimsEntity($normalized) && $candidateItem === null) {
            $intent = self::CLARIFY;
        }

        // Nothing recognised: on-topic questions get a clarification, the rest
        // are told plainly that they are outside what this assistant covers.
        if ($intent === null) {
            $intent = $inHimsScope ? self::CLARIFY : self::OUT_OF_SCOPE;
        }

        return [
            'intent' => $intent,
            'confidence' => $score > 0 ? min(1.0, $score / self::PHRASE_WEIGHT) : 0.0,
            'score' => $score,
            'matched' => $matched[$intent] ?? [],
            'in_hims_scope' => $inHimsScope,
            'days_ahead' => $intent === self::EXPIRY ? $this->expiryWindowDays($normalized) : null,
            'candidate_item' => $candidateItem,
            'greeting_prefix' => $greetingPrefix,
        ];
    }

    /**
     * Extract an opening greeting prefix from a message (e.g. "Good evening, may low stock ba tayo?").
     *
     * @return array{greeting: string, remainder: string}|null
     */
    public function extractGreetingPrefix(string $message): ?array
    {
        $pattern = '/^(?<greeting>good\s+(?:morning|afternoon|evening|night|day)|magandang\s+(?:umaga|tanghali|hapon|gabi|araw)|kumusta(?:\s+po)?|kamusta(?:\s+po)?|musta(?:\s+po)?|hello\s+there|hi\s+there|greetings|hello|hey|hi)\b(?<punct>[\s,!.:;-]*)/iu';

        if (preg_match($pattern, trim($message), $matches)) {
            $greeting = trim($matches['greeting']);
            $punct = $matches['punct'] ?? '';
            $remainder = trim(substr(trim($message), strlen($matches[0])));

            // If greeting is "kamusta" / "kumusta" / "musta" and is followed directly by
            // "ang", "yung", "iyong", "ating", "aming", "natin", "mga", "stock", "inventory" without a comma,
            // it is a status/summary inquiry ("Kamusta ang inventory?"), not a greeting prefix.
            if (preg_match('/^(?:kamusta|kumusta|musta)(?:\s+po)?$/i', $greeting) && ! str_contains($punct, ',')) {
                if (preg_match('/^(?:ang|yung|iyong|ating|aming|natin|mga|stock|inventory)\b/i', $remainder)) {
                    return null;
                }
            }

            return [
                'greeting' => $greeting,
                'remainder' => $remainder,
            ];
        }

        return null;
    }

    /**
     * Check if query is a conversational continuation, delegation, or agreement to proceed.
     * Handles "ikaw bahala", "sige ikaw na", "bahala ka", "go ahead", "what's next?", "proceed".
     */
    public function isConversationalContinue(string $message): bool
    {
        $clean = Str::lower(trim(rtrim(trim($message), '?!.,;:')));

        // 1. Direct delegation patterns (English / Filipino / Taglish)
        if (preg_match('/^(?:(?:ikaw|ikaw\s+na|kayo|kayo\s+na)\s+bahala(?:\s+na)?(?:\s+dyan|\s+diyan)?|bahala\s+ka(?:\s+na)?(?:\s+dyan|\s+diyan)?|sige(?:\s+po)?\s+(?:ikaw\s+na|bahala\s+ka)|up\s+to\s+you|your\s+call|you\s+decide|whatever\s+you\s+think(?:\s+is\s+best)?)\b[\s?!.,:;-]*$/iu', $clean)) {
            return true;
        }

        // 2. Direct continuation / proceed / progression patterns
        if (preg_match('/^(?:what\'?s\s+next|whats\s+next|ano(?:\s+ang)?\s+susunod|anong\s+susunod|ano\s+next|then\s+what|what\s+now|what\s+next|tuloy\s+mo|proceed|go\s+ahead|sige\s+go|go\s+lang|sige\s+lang)\b[\s?!.,:;-]*$/iu', $clean)) {
            return true;
        }

        return false;
    }

    /**
     * Check if query is an implied or subjectless follow-up in an ongoing dialogue.
     */
    public function isSubjectlessFollowup(string $message): bool
    {
        $clean = Str::lower(trim(rtrim(trim($message), '?!.,;:')));

        // Quantity: "ilan", "ilan pa", "how many", "how much", "meron", "meron pa", "may stock pa", "wala", "wala na"
        if (preg_match('/^(?:and\s+)?(?:ilan(?:\s+pa|\s+na\s+lang|\s+ang\s+available|\s+ang\s+stock)?|how\s+many(?:\s+are\s+left|\s+left|\s+available)?|how\s+much(?:\s+stock|\s+is\s+left)?|meron(?:\s+pa|\s+ba|\s+pa\s+ba)?|mayroon(?:\s+pa|\s+ba)?|may\s+stock\s+pa(?:\s+ba)?|wala\s+na(?:\s+ba)?|available\s+pa(?:\s+ba)?)$/iu', $clean)) {
            return true;
        }

        // Supplier: "sino", "who", "kanino", "sino supplier", "who supplies it"
        if (preg_match('/^(?:and\s+)?(?:sino(?:\s+supplier|\s+nagbigay|\s+ang\s+supplier)?|who(?:\s+supplies(?:\s+it)?|\s+is\s+the\s+supplier)?|kanino(?:\s+galing)?)$/iu', $clean)) {
            return true;
        }

        // Location: "nasaan", "saan", "where", "where is it", "nasaan ito", "saan nakatago"
        if (preg_match('/^(?:and\s+)?(?:nasaan(?:\s+ito|\s+iyan|\s+iyon|\s+nakatago|\s+nakalagay)?|saan(?:\s+ito|\s+naka-store|\s+nakatago|\s+makikita)?|where(?:\s+is\s+it|\s+is\s+this)?)$/iu', $clean)) {
            return true;
        }

        // Expiry / Date: "kailan", "when", "kailan expiry", "kailan delivery"
        if (preg_match('/^(?:and\s+)?(?:kailan(?:\s+expiry|\s+delivery|\s+darating)?|when(?:\s+expires|\s+delivery|\s+is\s+delivery)?)$/iu', $clean)) {
            return true;
        }

        // Selection / Reference: "alin", "which one", "yung una", "yung isa", "the first one", "ito", "iyan"
        if (preg_match('/^(?:and\s+)?(?:alin(?:\s+dito|\s+dyan|\s+doon)?|which\s+one|yung\s+(?:una|isa|pangalawa)|the\s+(?:first|second|1st|2nd)\s+one|ito|iyan|iyon)$/iu', $clean)) {
            return true;
        }

        // Confirmation: "talaga", "sure ka", "really"
        if (preg_match('/^(?:talaga(?:\s+ba)?|sure\s+ka(?:\s+ba)?|really)$/iu', $clean)) {
            return true;
        }

        return false;
    }

    /**
     * Check if query is a pure conversational acknowledgment or expression of gratitude.
     */
    public function isAcknowledgment(string $message): bool
    {
        return (bool) preg_match(
            '/^(?:thanks(?:\s+a\s+lot|\s+so\s+much|\s+po)?|thank\s+you(?:\s+so\s+much|\s+very\s+much|\s+po)?|salamat(?:\s+po|\s+nang\s+marami)?|maraming\s+salamat(?:\s+po)?|ok(?:\s+po|\s+thanks)?|okay(?:\s+po|\s+thanks)?|got\s+it|copy(?:\s+that)?|noted(?:\s+po)?|alright|all\s+right|sure(?:\s+thing)?|sige(?:\s+po)?|kuha\s+ko|ayun)[\s,!.:;-]*$/iu',
            trim($message)
        );
    }

    /**
     * Check if query is a pure farewell or closing.
     */
    public function isFarewell(string $message): bool
    {
        return (bool) preg_match(
            '/^(?:bye(?:\s+bye)?|goodbye|good\s+bye|goodnight(?:\s+po)?|good\s+night(?:\s+po)?|night(?:\s+night)?|see\s+you(?:\s+later)?|see\s+ya|talk\s+to\s+you\s+later|ingat(?:\s+po)?|paalam|tulog\s+na(?:\s+ako|\s+po)?|matulog\s+na(?:\s+ako|\s+po)?)[\s,!.:;-]*$/iu',
            trim($message)
        );
    }

    /**
     * Check if query is a short conversational clarification or follow-up
     * ("what?", "ano?", "why?", "huh?", "pardon?", "come again?").
     */
    public function isConversationalClarification(string $message): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(?:what(?:\s+do\s+you\s+mean|\s+does\s+that\s+mean|\s+is\s+that)?|why(?:\s+is\s+that|\s+so)?|huh|ha|ano(?:\s+daw|\s+po|\s+ibig\s+sabihin(?:\s+nito)?)?|bakit(?:\s+po|\s+naman)?|pardon(?:\s+me)?|come\s+again|di\s+ko\s+gets|di\s+ko\s+maintindihan|hindi\s+ko\s+maintindihan|can\s+you\s+repeat\s+that|paki-?ulit(?:\s+po)?)[\s?!.,:;-]*$/iu',
            $trimmed
        );
    }

    /**
     * Check if query asks about the assistant's status or well-being ("kamusta?", "how are you?").
     */
    public function isHowAreYou(string $message): bool
    {
        return (bool) preg_match(
            '/^(?:how\s+are\s+you(?:\s+doing)?|how\s+do\s+you\s+do|how\'s\s+it\s+going|kamusta(?:\s+ka|\s+po)?|kumusta(?:\s+ka|\s+po)?|musta(?:\s+ka)?|how\s+are\s+things)[\s?!.,:;-]*$/iu',
            trim($message)
        );
    }

    /**
     * Check if a query is explicitly out of scope (math, weather, jokes, creative writing, non-hospital trivia).
     */
    public function isExplicitlyOutOfScope(string $normalized): bool
    {
        // Math / arithmetic (e.g. "1+1", "1 + 1", "what is 5 * 10")
        if (preg_match('/^(?:what\s+is\s+|calculate\s+|solve\s+)?\d+\s*[\+\-\*\/x%^]\s*\d+/i', $normalized) === 1
            || preg_match('/\b(?:1\s*\+\s*1|2\s*\+\s*2)\b/', $normalized) === 1) {
            return true;
        }

        // Weather
        if (preg_match('/\b(?:weather|panahon|ulan|rain|temperature|climate)\b/i', $normalized) === 1
            && ! str_contains($normalized, 'demand')) {
            return true;
        }

        // Jokes / creative writing / poems
        if (preg_match('/\b(?:joke|jokes|magbiro|biro|magpatawa|poem|tula|kanta|song|sing|write a poem|tell me a joke|write a story|tell a story)\b/i', $normalized) === 1) {
            return true;
        }

        // General trivia / non-hospital off-topic knowledge
        if (preg_match('/\b(?:president of|capital of|sino ang presidente|who is the president|recipe for|how to bake|how to cook|python programming|javascript code|write code)\b/i', $normalized) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Does the query exhibit an inventory inquiry frame (e.g. asking about presence, stock, availability, or supplier)?
     */
    public function hasInventoryInquiryFrame(string $normalized): bool
    {
        $patterns = [
            '/\b(?:may|meron|mayroon)\b[^.]{0,35}\b(?:ba|tayo|natin|available|stock|pa)\b/u',
            '/\b(?:do we have|is there any|do we carry|check if we have)\b/u',
            '/\b(?:sino|who)\b[^.]{0,25}\b(?:supplier|supplies)\b/u',
            '/\b(?:nasaan|saan)\b[^.]{0,25}\b(?:naka-store|nakatago|stock|location|makikita)\b/u',
            '/\b(?:kailan|when)\b[^.]{0,25}\b(?:delivery|darating|arrival|shipment)\b/u',
            '/\b(?:magkano|how much)\b/u',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a candidate item subject from natural-language queries.
     */
    private function extractCandidateItem(string $message): ?string
    {
        if (function_exists('app')) {
            try {
                return app(ConversationalEntityTracker::class)->extractItemCandidate($message);
            } catch (\Throwable) {
                // Container not booted in isolated unit tests
            }
        }

        // Standalone fallback matching common query patterns
        $trimmed = trim($message);

        // 1. Try SKU matching first (e.g. SKU: ABC-123 or ABC-123, but not Tagalog/English hyphenated verbs or adjectives like mag-expire, low-stock)
        if (preg_match('/\b([A-Z0-9]{2,6}-[A-Z0-9-]{2,10})\b/i', $trimmed, $skuMatch)) {
            $rawSku = $skuMatch[1];
            $isNotSku = preg_match('/^(?:mag|nag|pag|naka|ipa|i|pa|non|pre|post|low|out|high|fast|slow|near|shelf|on|re|first|second|third|day|multi)-/i', $rawSku)
                || preg_match('/^(?:low-stock|out-of-stock|high-risk|near-expiry|fast-moving|slow-moving|shelf-life|re-order|follow-up)$/i', $rawSku)
                || (! preg_match('/[0-9]/', $rawSku) && $rawSku !== strtoupper($rawSku));

            if (! $isNotSku) {
                return $rawSku;
            }
        }

        $patterns = [
            '/\b(?:may delivery ba ng|may delivery ba sa|may delivery ba|kailan darating ang|delivery of|shipment of)\s+(.+?)(?:\?|\.|$)/iu',
            '/\bmay\s+(.+?)\s+bang\s+(?:paubos|low stock|ubos|kailangan|critical|reorder)\b/iu',
            '/\b(?:paubos na ba ang|need ba i-order ang|kailangan ba orderin ang)\s+(.+?)(?:\?|\.|$)/iu',
            '/\b(?:sino|who)\s+(?:ang\s+)?(?:supplier|supplies|nagsu-supply)\s+(?:ng|nito|for|of)\s+(.+?)(?:\?|\.|$)/iu',
            '/\b(?:nasaan|saan\s+(?:naka-store|nakatago|may stock|ang))\s+(?:yung|ang)?\s*(.+?)(?:\?|\.|$)/iu',
            '/\b(?:where is|where are)\s+(.+?)\s+(?:stored|located|kept)?(?:\?|\.|$)/iu',
            '/\b(?:magkano|how much is|price of|unit cost of)\s+(?:ang|yung)?\s*(.+?)(?:\?|\.|$)/iu',
            '/\b(?:tell me about|information on|details on|status of|status on|update on)\s+(.+?)(?:\?|\.|$)/iu',
            '/\bhow many\s+(.+?)\s+(?:do we have|are left|in stock|are in warehouse|are in storage|in storage|available|on hand)\b/iu',
            '/\b(?:why is|why are)\s+(.+?)\s+(?:at high risk|considered high risk|considered low stock|high risk|low stock|low in stock|critical|at risk|failing|delayed|short)\b/iu',
            '/\b(?:what is|what\'s|check|show|get)\s+(?:the\s+)?(?:predicted\s+demand|stock|quantity|level|status|lead time|details|record|info|history)\s+(?:for|of|on)\s+(.+?)(?:\?|\.|$)/iu',
            '/\b(?:review|inspect|check)\s+(.+?)(?:\?|\.|$)/iu',
            '/\b(?:do we have|is there|is there any|do we carry|check if we have)\s+(?:any\s+|stock of\s+)?(.+?)(?:\s+in stock|\s+available)?(?:\?|\.|$)/iu',
            '/\b(?:mayroon|meron|may)\s+(?:bang\s+|ba\s+)?(?:available\s+na\s+|stock\s+(?:pa\s+)?(?:ba\s+)?(?:ng\s+|nito\s+)?)?(.+?)(?:\s+ba(?:\s+tayo|\s+pa|\s+natin)?|\s+tayo)?(?:\?|\.|$)/iu',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $trimmed, $m)) {
                $cand = trim(rtrim(trim($m[1]), '?!.,;:'));
                $cand = preg_replace('/^(?:ang\s+|yung\s+|iyong\s+|itong\s+|ng\s+|sa\s+|mga\s+|the\s+|a\s+|an\s+|this\s+|that\s+)+/iu', '', $cand) ?? $cand;
                $cand = preg_replace('/\s+(?:ba|tayo|natin|namin|po|pa|kaya|naman|bang|din|rin|pala|nga)+$/iu', '', $cand) ?? $cand;
                $cand = preg_replace('/\s+(?:ba|tayo|natin|namin|po|pa|kaya|naman|bang|din|rin|pala|nga)+$/iu', '', $cand) ?? $cand;
                $cand = preg_replace('/\s+(?:right now|today|at the moment|currently|ngayon|sa ngayon)+$/iu', '', $cand) ?? $cand;
                $cand = trim($cand, " \t\n\r\0\x0B'\"");

                if (preg_match('/\b(?:na need|na kailangan|kailangan|orderin|bilhin|iorder|irestock|paubos|ubos na|magkano|darating|parating|problema|issue|high[- ]risk|low[- ]stock|out[- ]of[- ]stock|expir(?:y|ed|ing)|malapit na)\b/iu', $cand)) {
                    continue;
                }

                if (preg_match('/^(?:item|items|gamot|supply|supplies)\s+(?:ba\s+|na\s+|bang\s+|kung\s+)/iu', $cand)) {
                    continue;
                }

                if (preg_match('/^(?:low[- ]stock|out[- ]of[- ]stock|high[- ]risk)(?:\s+items?)?$/iu', $cand)) {
                    continue;
                }

                if (mb_strlen($cand) >= 2 && mb_strlen($cand) <= 50) {
                    $lower = strtolower($cand);
                    $exclusions = [
                        'this item', 'that item', 'the item', 'these items', 'those items', 'an item', 'item', 'items',
                        'this', 'that', 'our inventory', 'the inventory', 'inventory', 'stock', 'stocks', 'it', 'everything', 'anything',
                        'first one', 'the first one', 'the second one', 'second one',
                        'any high risk item', 'high risk item', 'high-risk item', 'high risk items', 'high-risk items', 'high risk', 'high-risk',
                        'low stock', 'low-stock', 'low stock item', 'low-stock item', 'low stock items', 'low-stock items',
                        'out of stock', 'out-of-stock', 'out of stock item', 'out-of-stock item', 'out of stock items', 'out-of-stock items',
                        'nito', 'natin', 'namin', 'niyan', 'noon', 'lahat', 'problema', 'issue', 'issues', 'summary', 'report',
                        'gamot', 'medisina', 'supplies', 'supply', 'delivery', 'deliveries', 'shipment', 'shipments', 'order', 'orders',
                        'wala', 'walang', 'hindi', 'meron', 'merong', 'mayroon', 'available', 'kailangan', 'darating', 'parating',
                        'paubos', 'ubos', 'kulang', 'mababa', 'critical', 'reorder', 'restock', 'bawal', 'pwede', 'puwede',
                        'storage location', 'storage locations', 'location', 'locations', 'bodega', 'warehouse', 'storeroom',
                        'mga storage location', 'mga location', 'mga bodega', 'mga warehouse', 'mga storeroom',
                        'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'good night',
                        'magandang umaga', 'magandang hapon', 'magandang gabi', 'magandang araw',
                        'kamusta', 'kumusta', 'musta', 'thanks', 'thank you', 'salamat', 'ok', 'okay', 'bye', 'goodbye',
                    ];
                    if (! in_array($lower, $exclusions, true)) {
                        return $cand;
                    }
                }
            }
        }

        return null;
    }

    /**
     * The expiry horizon the question actually asked about.
     *
     * "Anong items ang mag-e-expire this month?" and "expiring within 90 days"
     * are the same intent over different windows; answering both with the
     * 90-day default reports the wrong batches for one of them. Returns null
     * when no window is stated, so callers can apply their own default.
     */
    public function expiryWindowDays(string $message): ?int
    {
        $normalized = $this->normalize($message);

        foreach (self::EXPIRY_WINDOWS as $pattern => $days) {
            if (preg_match($pattern, $normalized) === 1) {
                return $days === 'end_of_month'
                    ? max(1, (int) now()->startOfDay()->diffInDays(now()->endOfMonth()->startOfDay()))
                    : $days;
            }
        }

        // "expire within 45 days" — any stated number wins over the defaults.
        if (preg_match('/\b(?:within|in|sa loob ng)\s+(\d{1,3})\s*(?:days|araw)\b/', $normalized, $m)) {
            return max(1, min(730, (int) $m[1]));
        }

        if (preg_match('/\b(\d{1,3})\s*(?:days|araw)\b/', $normalized, $m)) {
            return max(1, min(730, (int) $m[1]));
        }

        return null;
    }

    /**
     * Is this question asking what a term means?
     *
     * True when it carries an explicit definitional cue ("meaning of",
     * "ibig sabihin", "define"), or when it is a bare "what is X?" / "ano ang
     * X?" whose only content words are a term in the HIMS glossary. The second
     * form is what keeps "ano ang fefo?" working now that the generic openers
     * are no longer lexicon phrases — while "what is the reorder level for N95
     * mask?" stays a data question, because "N95" and "mask" are not part of
     * any glossary term.
     */
    private function looksDefinitional(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        foreach (self::DEFINITION_CUES as $cue) {
            if ($this->containsPhrase($normalized, $cue)) {
                return true;
            }
        }

        return $this->bareDefinitionSubject($normalized);
    }

    /**
     * A "what is X?" question whose remaining words are exactly a glossary term.
     */
    private function bareDefinitionSubject(string $normalized): bool
    {
        $remainder = null;
        foreach (self::DEFINITION_OPENERS as $opener) {
            if (str_starts_with($normalized, $opener.' ')) {
                $remainder = substr($normalized, strlen($opener) + 1);
                break;
            }
        }

        if ($remainder === null || trim($remainder) === '') {
            return false;
        }

        $words = array_values(array_filter(
            explode(' ', $remainder),
            fn (string $w) => $w !== '' && ! in_array($w, self::DEFINITION_STOPWORDS, true)
        ));

        if ($words === []) {
            return false;
        }

        // Every remaining word has to be consumed by a single glossary term.
        foreach (array_keys(HimsDomainKnowledge::getGlossary()) as $term) {
            $termWords = explode(' ', $term);
            if ($words === $termWords) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lowercase, strip punctuation (so "i-restock" and "i restock" agree), and
     * collapse whitespace for phrase and token matching.
     */
    private function normalize(string $message): string
    {
        $lowered = Str::lower(trim($message));
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lowered) ?? $lowered;

        return trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);
    }

    /**
     * Score every intent against the question, returning the totals and which
     * cues fired for each.
     *
     * @return array{0: array<string, int>, 1: array<string, array<int, string>>}
     */
    private function score(string $normalized): array
    {
        $tokens = $normalized === '' ? [] : explode(' ', $normalized);
        $scores = [];
        $matched = [];

        foreach (self::LEXICON as $intent => $tiers) {
            $score = 0;
            $hits = [];

            foreach ($tiers['phrases'] ?? [] as $phrase) {
                if ($this->containsPhrase($normalized, $phrase)) {
                    $score += self::PHRASE_WEIGHT;
                    $hits[] = $phrase;
                }
            }

            // Patterns carry more weight than a phrase. They exist because a
            // fixed phrase list only matches the exact wording someone wrote
            // down: "past expiry" was listed, but "past their expiry date" was
            // not, and that question was then answered as a nearing-expiry
            // question instead of an already-expired one.
            foreach ($tiers['patterns'] ?? [] as $pattern) {
                if (preg_match($pattern, $normalized) === 1) {
                    $score += self::PATTERN_WEIGHT;
                    $hits[] = $pattern;
                }
            }

            foreach ($tiers['terms'] ?? [] as $term) {
                if ($this->containsStem($tokens, $term)) {
                    $score += self::TERM_WEIGHT;
                    $hits[] = $term;
                }
            }

            foreach ($tiers['weak'] ?? [] as $term) {
                if ($this->containsStem($tokens, $term)) {
                    $score += self::WEAK_WEIGHT;
                    $hits[] = $term;
                }
            }

            if ($score > 0) {
                $scores[$intent] = $score;
                $matched[$intent] = $hits;
            }
        }

        return [$scores, $matched];
    }

    /**
     * Match a multi-word cue on whole words, so "low stock" does not fire
     * inside "low stockpile".
     */
    private function containsPhrase(string $normalized, string $phrase): bool
    {
        return $normalized !== ''
            && preg_match('/\b' . preg_quote($phrase, '/') . '\b/u', $normalized) === 1;
    }

    /**
     * Match a cue against a token by stem, so inflections and Filipino verbal
     * forms of the same word all land ("order" catches orderin / i-order /
     * ordering / ordered / reorder).
     *
     * @param  array<int, string>  $tokens
     */
    private function containsStem(array $tokens, string $stem): bool
    {
        if ($stem === '') {
            return false;
        }

        foreach ($tokens as $token) {
            if (str_starts_with($token, $stem) || str_contains($token, $stem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Highest scoring intent; ties break by declaration order in the lexicon,
     * which puts the more specific reading first.
     *
     * @param  array<string, int>  $scores
     */
    private function pickWinner(array $scores): ?string
    {
        $best = null;
        $bestScore = 0;

        foreach (array_keys(self::LEXICON) as $intent) {
            $score = $scores[$intent] ?? 0;
            if ($score > $bestScore) {
                $best = $intent;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Does the question name anything that exists in HIMS?
     */
    public function mentionsHimsEntity(string $message): bool
    {
        $normalized = $this->normalize($message);

        foreach (self::HIMS_ENTITIES as $entity) {
            if ($this->containsStem(explode(' ', $normalized), $entity)) {
                return true;
            }
        }

        return false;
    }
}
