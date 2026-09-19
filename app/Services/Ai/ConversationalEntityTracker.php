<?php

namespace App\Services\Ai;

use App\Models\InventoryItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Multi-turn conversational entity tracker.
 *
 * Resolves pronouns ("it", "that item", "this medicine") and ordinal references
 * ("the first one", "the second item", "#1") against numbered lists and active focus
 * in the ongoing conversation history.
 */
class ConversationalEntityTracker
{
    public function __construct(
        private readonly HimsAiToolRegistry $tools,
    ) {}

    /**
     * Resolve focus item and references from user query and conversation history.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{
     *     focus_item: ?InventoryItem,
     *     resolved_name: ?string,
     *     is_ordinal: bool,
     *     is_pronoun: bool,
     *     ordinal_index: ?int,
     *     recent_items: Collection<int, InventoryItem>,
     *     candidate_item: ?string
     * }
     */
    public function resolve(string $userQuery, array $history = []): array
    {
        $q = Str::lower(trim($userQuery));
        $candidateItem = $this->extractItemCandidate($userQuery);

        // 1. Direct explicit item search in current query first
        $explicitItem = $this->extractExplicitItem($userQuery);
        if ($explicitItem !== null && ! $this->isPureReferenceQuery($q)) {
            // If the query candidate matches multiple distinct items, do not prematurely lock onto just one.
            // Returning focus_item = null allows the assistant to offer a numbered disambiguation list.
            if ($candidateItem !== null && $this->tools->searchMatchingItems($candidateItem, 2)->count() > 1) {
                return [
                    'focus_item' => null,
                    'resolved_name' => null,
                    'is_ordinal' => false,
                    'is_pronoun' => false,
                    'ordinal_index' => null,
                    'recent_items' => collect(),
                    'candidate_item' => $candidateItem,
                ];
            }

            return [
                'focus_item' => $explicitItem,
                'resolved_name' => $explicitItem->name,
                'is_ordinal' => false,
                'is_pronoun' => false,
                'ordinal_index' => null,
                'recent_items' => collect([$explicitItem]),
                'candidate_item' => $candidateItem ?? $explicitItem->name,
            ];
        }

        // 2. Check for ordinal reference ("the first one", "2nd item", "number 3")
        $ordinalIndex = $this->extractOrdinalIndex($q);
        if ($ordinalIndex !== null && ! empty($history)) {
            $parsedList = $this->parseNumberedItemsFromHistory($history);
            if (isset($parsedList[$ordinalIndex])) {
                $matchedItem = $this->tools->findItem($parsedList[$ordinalIndex]);
                if ($matchedItem !== null) {
                    return [
                        'focus_item' => $matchedItem,
                        'resolved_name' => $matchedItem->name,
                        'is_ordinal' => true,
                        'is_pronoun' => false,
                        'ordinal_index' => $ordinalIndex,
                        'recent_items' => collect([$matchedItem]),
                        'candidate_item' => $candidateItem,
                    ];
                }
            }
        }

        // 3. Check for pronoun reference ("it", "this item", "that medicine", "who supplied it")
        $isPronoun = $this->isPronounReference($q);
        if ($isPronoun && ! empty($history)) {
            // Find most recent item discussed in previous turns
            $recentItem = $this->findMostRecentItemInHistory($history);
            if ($recentItem !== null) {
                return [
                    'focus_item' => $recentItem,
                    'resolved_name' => $recentItem->name,
                    'is_ordinal' => false,
                    'is_pronoun' => true,
                    'ordinal_index' => null,
                    'recent_items' => collect([$recentItem]),
                    'candidate_item' => $candidateItem,
                ];
            }
        }

        // 3b. Subjectless follow-up about the item under discussion ("Sino
        // supplier?", "How much stock is left?"). These name no item, so
        // without this they fell through and the caller answered with the
        // global supplier directory instead of the item just talked about.
        if ($explicitItem === null && ! empty($history) && $this->isSubjectlessItemFollowUp($q)) {
            $recentItem = $this->findMostRecentItemInHistory($history);
            if ($recentItem !== null) {
                return [
                    'focus_item' => $recentItem,
                    'resolved_name' => $recentItem->name,
                    'is_ordinal' => false,
                    'is_pronoun' => true,
                    'ordinal_index' => null,
                    'recent_items' => collect([$recentItem]),
                    'candidate_item' => $candidateItem,
                ];
            }
        }

        // 4. Fallback: if explicit item was found even if subtle
        if ($explicitItem !== null) {
            // If the query candidate matches multiple distinct items, do not prematurely lock onto just one.
            // Returning focus_item = null allows the assistant to offer a numbered disambiguation list.
            if ($candidateItem !== null && $this->tools->searchMatchingItems($candidateItem, 2)->count() > 1) {
                return [
                    'focus_item' => null,
                    'resolved_name' => null,
                    'is_ordinal' => false,
                    'is_pronoun' => false,
                    'ordinal_index' => null,
                    'recent_items' => collect(),
                    'candidate_item' => $candidateItem,
                ];
            }

            return [
                'focus_item' => $explicitItem,
                'resolved_name' => $explicitItem->name,
                'is_ordinal' => false,
                'is_pronoun' => false,
                'ordinal_index' => null,
                'recent_items' => collect([$explicitItem]),
                'candidate_item' => $candidateItem ?? $explicitItem->name,
            ];
        }

        return [
            'focus_item' => null,
            'resolved_name' => null,
            'is_ordinal' => false,
            'is_pronoun' => false,
            'ordinal_index' => null,
            'recent_items' => collect(),
            'candidate_item' => $candidateItem,
        ];
    }

    /**
     * Check if query is purely referencing an item rather than introducing a new one.
     */
    private function isPureReferenceQuery(string $q): bool
    {
        return Str::contains($q, [
            'the first', 'first one', '1st one', '1st item',
            'the second', 'second one', '2nd one', '2nd item',
            'the third', 'third one', '3rd one', '3rd item',
            'who supplied it', 'who supplies it', 'who is the supplier',
            'is it enough', 'how many are available', 'what about it',
        ]);
    }

    /**
     * Determine if query refers to an existing subject via pronoun.
     *
     * Includes the Filipino anaphors ("nun", "nito", "iyon"), because a follow-up
     * like "may pending order na ba nun?" refers back to the same item without
     * naming it. "yung" is deliberately absent: it is an article, not a pronoun,
     * and treating it as a reference would pull global questions such as "ano
     * yung mga paubos na?" back to whatever item happened to be discussed last.
     */
    private function isPronounReference(string $q): bool
    {
        return (bool) preg_match('/\b(it|this|that|its|the item|the medicine|this item|that item|this drug|that drug|the product|who supplied it|who supplies it|is it enough|how many of it|nun|niyon|niyan|nito|iyon|iyan|ito|yun|yon)\b/i', $q);
    }

    /**
     * Determine whether a query asks about an attribute of the item currently
     * under discussion without naming it.
     *
     * Deliberately narrow. A question that introduces its own subject ("anong
     * mga items ang walang expiry?") must not be pulled back to whatever item
     * happened to be mentioned last, so only attribute questions that are
     * meaningless without a subject qualify.
     */
    private function isSubjectlessItemFollowUp(string $q): bool
    {
        $trimmed = trim($q);

        // 1. Quantity & availability follow-ups: "ilan?", "ilan pa?", "how many?", "meron?", "meron pa?", "may stock pa ba?"
        if (preg_match('/^(?:and\s+)?(?:ilan(?:\s+pa|\s+na\s+lang|\s+ang\s+available|\s+ang\s+stock)?|how\s+many(?:\s+are\s+left|\s+left|\s+available)?|how\s+much\s+stock(?:\s+is\s+left)?|meron(?:\s+pa)?(?:\s+ba)?|mayroon(?:\s+pa)?(?:\s+ba)?|may\s+stock\s+pa(?:\s+ba)?|wala\s+na(?:\s+ba)?|available\s+pa(?:\s+ba)?)\b[\s?!.]*$/iu', $trimmed)
            || in_array(strtolower($trimmed), ['ilan', 'ilan?', 'ilan pa', 'ilan pa?', 'ilan na lang', 'how many', 'how many?', 'how many left', 'how many left?', 'meron', 'meron?', 'meron pa', 'meron pa?', 'mayroon?', 'available?'], true)) {
            return true;
        }

        // 2. Supplier follow-ups: "sino?", "sino supplier?", "who supplies it?", "kanino galing?"
        if (preg_match('/^(?:and\s+)?(?:sino|who|kanino|sa\s+kanino)\b(?:.*\b(?:supplier|vendor|nag-supply|nagsupply|nagbigay|galing|supplies|supply|provide|provides)\b)?[\s?!.]*$/iu', $trimmed)
            || in_array(strtolower($trimmed), ['sino', 'sino?', 'who', 'who?', 'kanino', 'kanino?', 'sino supplier', 'sino supplier?'], true)) {
            return true;
        }

        // 3. Location follow-ups: "nasaan?", "saan?", "nasaan nakatago?", "saan naka-store?", "where is it?"
        if (preg_match('/^(?:and\s+)?(?:nasaan|saan|where)\b(?:.*\b(?:naka-store|nakatago|nakalagay|makikita|ang\s+location|stored|located|kept)\b)?[\s?!.]*$/iu', $trimmed)
            || in_array(strtolower($trimmed), ['nasaan', 'nasaan?', 'saan', 'saan?', 'where', 'where?', 'where is it', 'where is it?'], true)) {
            return true;
        }

        // 4. Delivery & Expiry follow-ups: "kailan?", "kailan darating?", "when?"
        if (preg_match('/^(?:and\s+)?(?:kailan|when)\b(?:.*\b(?:delivery|darating|parating|arrival|expiry|mag-e-expire|mageexpire)\b)?[\s?!.]*$/iu', $trimmed)
            || in_array(strtolower($trimmed), ['kailan', 'kailan?', 'when', 'when?'], true)) {
            return true;
        }

        // 5. Cost & Valuation follow-ups: "magkano?", "magkano ito?", "how much?"
        if (preg_match('/^(?:and\s+)?(?:magkano|how\s+much|price|unit\s+cost)\b(?:.*\b(?:ito|nito|iyan|yan|ba|cost|price)\b)?[\s?!.]*$/iu', $trimmed)
            || in_array(strtolower($trimmed), ['magkano', 'magkano?', 'how much', 'how much?'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Extract ordinal 0-based index from query.
     */
    private function extractOrdinalIndex(string $q): ?int
    {
        if (preg_match('/\b(first|1st|number\s*1|#1|the\s*first\s*one)\b/i', $q)) {
            return 0;
        }
        if (preg_match('/\b(second|2nd|number\s*2|#2|the\s*second\s*one)\b/i', $q)) {
            return 1;
        }
        if (preg_match('/\b(third|3rd|number\s*3|#3|the\s*third\s*one)\b/i', $q)) {
            return 2;
        }
        if (preg_match('/\b(fourth|4th|number\s*4|#4|the\s*fourth\s*one)\b/i', $q)) {
            return 3;
        }
        if (preg_match('/\b(fifth|5th|number\s*5|#5|the\s*fifth\s*one)\b/i', $q)) {
            return 4;
        }

        return null;
    }

    /**
     * Parse numbered lists from previous assistant messages.
     * E.g. "1. **Latex Examination Gloves** (SKU: GLOV-LTX-001)" -> [0 => "Latex Examination Gloves", ...]
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, string>
     */
    private function parseNumberedItemsFromHistory(array $history): array
    {
        $reversed = array_reverse($history);
        foreach ($reversed as $turn) {
            if (($turn['role'] ?? '') !== 'assistant') {
                continue;
            }

            $content = (string) ($turn['content'] ?? '');
            if (trim($content) === '') {
                continue;
            }

            $items = [];
            // 1. Match markdown numbered lists: e.g. "1. **Item Name**" or "1. Item Name (SKU: ...)"
            if (preg_match_all('/(?:^|\n)\s*(\d+)\.\s+\*?\*?([^\*\n\(\—\-]+?)\*?\*?(?:\s*\(SKU|\s*—|\s*\n|$)/m', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $index = ((int) $match[1]) - 1;
                    $name = trim($match[2]);
                    if ($index >= 0 && $name !== '') {
                        $items[$index] = $name;
                    }
                }
            }

            // 2. Match markdown bullet lists: e.g. "- **Item Name** (SKU: ...)"
            if (empty($items) && preg_match_all('/(?:^|\n)\s*[\-\*]\s+\*?\*?([^\*\n\(\—\-]+?)\*?\*?(?:\s*\(SKU|\s*—|\s*\n|$)/m', $content, $matches, PREG_SET_ORDER)) {
                $exclusions = ['category', 'physical stock', 'reserved stock', 'reorder level', 'safety stock', 'supplier lead time', 'primary supplier', 'unit cost', 'current status'];
                $idx = 0;
                foreach ($matches as $match) {
                    $name = trim($match[1]);
                    if ($name !== '' && ! in_array(strtolower($name), $exclusions, true)) {
                        $items[$idx++] = $name;
                    }
                }
            }

            if (! empty($items)) {
                ksort($items);

                return $items;
            }
        }

        return [];
    }

    /**
     * Find the most recent InventoryItem discussed in earlier turns.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function findMostRecentItemInHistory(array $history): ?InventoryItem
    {
        $reversed = array_reverse($history);
        foreach ($reversed as $turn) {
            $content = (string) ($turn['content'] ?? '');
            $item = $this->extractExplicitItem($content);
            if ($item !== null) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Extract an explicit item name or SKU from text.
     */
    public function extractExplicitItem(string $text): ?InventoryItem
    {
        // 1. Try candidate extraction first
        $candidate = $this->extractItemCandidate($text);
        if ($candidate !== null) {
            $item = $this->tools->findItem($candidate);
            if ($item) {
                return $item;
            }
        }

        // 2. Try SKU matching (e.g. SKU: ABC-123 or ABC-123)
        if (preg_match('/\b([A-Z0-9]{2,6}-[A-Z0-9-]{2,10})\b/i', $text, $skuMatch)) {
            $rawSku = $skuMatch[1];
            $isNotSku = preg_match('/^(?:mag|nag|pag|naka|ipa|i|pa|non|pre|post|low|out|high|fast|slow|near|shelf|on|re|first|second|third|day|multi)-/i', $rawSku)
                || preg_match('/^(?:low-stock|out-of-stock|high-risk|near-expiry|fast-moving|slow-moving|shelf-life|re-order|follow-up)$/i', $rawSku)
                || (! preg_match('/[0-9]/', $rawSku) && $rawSku !== strtoupper($rawSku));

            if (! $isNotSku) {
                $item = InventoryItem::with(['category', 'supplier', 'batches', 'stockLevels.storageLocation'])->where('sku', $rawSku)->first();
                if ($item) {
                    return $item;
                }
            }
        }

        // 3. Try bolded item names: **Item Name**
        if (preg_match_all('/\*\*([^\*\n]+?)\*\*/', $text, $boldMatches)) {
            foreach ($boldMatches[1] as $boldText) {
                $item = $this->tools->findItem(trim($boldText));
                if ($item) {
                    return $item;
                }
            }
        }

        // 4. Match known item words
        $words = collect(explode(' ', $text))
            ->map(fn ($w) => trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $w)))
            ->filter(fn ($w) => strlen($w) >= 4 && ! in_array(Str::lower($w), [
                'what', 'which', 'where', 'whose', 'about', 'items', 'stock', 'please', 'check', 'show', 'tell', 'need', 'many', 'much', 'forecast', 'demand', 'month', 'first', 'second', 'supplier', 'supplies', 'order', 'orders', 'purchase',
                // Filipino interrogatives, articles and negation.
                'anong', 'ano', 'alin', 'sino', 'saan', 'kanino', 'kailan', 'ilan', 'magkano', 'meron', 'merong',
                'yung', 'iyong', 'itong', 'nito', 'natin', 'namin', 'niyan', 'yun', 'iyon',
                'walang', 'wala', 'hindi', 'huwag', 'gusto', 'pakita', 'lahat', 'bang', 'naman', 'kaya', 'para', 'mga',
                'tayo', 'dapat', 'pwede', 'puwede', 'tingnan', 'tignan', 'hanapin', 'meron', 'mayroon',
            ], true))
            ->values();

        foreach ($words as $word) {
            $item = InventoryItem::with(['category', 'supplier', 'batches', 'stockLevels.storageLocation'])
                ->where('name', 'LIKE', "%{$word}%")
                ->first();
            if ($item) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Extract a candidate item subject from natural-language queries.
     * Works with English, Filipino, and Taglish phrasings without requiring
     * the item to be previously known.
     */
    public function extractItemCandidate(string $text): ?string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

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

        // 2. Try bolded item names: **Item Name**
        if (preg_match('/\*\*([^\*\n]+?)\*\*/', $trimmed, $boldMatch)) {
            return trim($boldMatch[1]);
        }

        // 3. Natural-language extraction patterns (specific attribute queries first)
        $patterns = [
            // "May delivery ba ng Zonrox?", "When is the arrival of Zonrox?"
            '/\b(?:may delivery ba ng|may delivery ba sa|may delivery ba|kailan darating ang|delivery of|shipment of)\s+(.+?)(?:\?|\.|$)/iu',
            // "May Zonrox bang paubos na?", "May Zonrox bang critical?"
            '/\bmay\s+(.+?)\s+bang\s+(?:paubos|low stock|ubos|kailangan|critical|reorder)\b/iu',
            // "Paubos na ba ang Zonrox?", "Need ba i-order ang Zonrox?"
            '/\b(?:paubos na ba ang|need ba i-order ang|kailangan ba orderin ang)\s+(.+?)(?:\?|\.|$)/iu',
            // "Sino supplier ng Zonrox?", "Who supplies paracetamol?"
            '/\b(?:sino|who)\s+(?:ang\s+)?(?:supplier|supplies|nagsu-supply)\s+(?:ng|nito|for|of)\s+(.+?)(?:\?|\.|$)/iu',
            // "Nasaan yung Zonrox?", "Saan naka-store ang alcohol?", "Where is paracetamol stored?"
            '/\b(?:nasaan|saan\s+(?:naka-store|nakatago|may stock|ang))\s+(?:yung|ang)?\s*(.+?)(?:\?|\.|$)/iu',
            '/\b(?:where is|where are)\s+(.+?)\s+(?:stored|located|kept)?(?:\?|\.|$)/iu',
            // "Magkano Zonrox?", "How much is paracetamol?", "Price of Zonrox"
            '/\b(?:magkano|how much is|price of|unit cost of)\s+(?:ang|yung)?\s*(.+?)(?:\?|\.|$)/iu',
            // "tell me about...", "how many ... do we have"
            '/\b(?:tell me about|information on|details on|status of|status on|update on)\s+(.+?)(?:\?|\.|$)/iu',
            '/\bhow many\s+(.+?)\s+(?:do we have|are left|in stock|are in warehouse|are in storage|in storage|available|on hand)\b/iu',
            '/\b(?:why is|why are)\s+(.+?)\s+(?:at high risk|considered high risk|considered low stock|high risk|low stock|low in stock|critical|at risk|failing|delayed|short)\b/iu',
            '/\b(?:what is|what\'s|check|show|get)\s+(?:the\s+)?(?:predicted\s+demand|stock|quantity|level|status|lead time|details|record|info|history)\s+(?:for|of|on)\s+(.+?)(?:\?|\.|$)/iu',
            '/\b(?:review|inspect|check)\s+(.+?)(?:\?|\.|$)/iu',
            // "Do we have surgical masks?", "Is there any alcohol available?", "Is paracetamol in stock?"
            '/\b(?:do we have|is there|is there any|do we carry|check if we have)\s+(?:any\s+|stock of\s+)?(.+?)(?:\s+in stock|\s+available)?(?:\?|\.|$)/iu',
            // "May Zonrox ba tayo?", "May stock pa ba ng paracetamol?", "Meron bang alcohol?", "Mayroon bang surgical gloves?"
            '/\b(?:mayroon|meron|may)\s+(?:bang\s+|ba\s+)?(?:available\s+na\s+|stock\s+(?:pa\s+)?(?:ba\s+)?(?:ng\s+|nito\s+)?)?(.+?)(?:\s+ba(?:\s+tayo|\s+pa|\s+natin)?|\s+tayo)?(?:\?|\.|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches)) {
                $candidate = $this->cleanCandidateString($matches[1]);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Clean candidate item string, stripping leading/trailing stop words and punctuation.
     */
    public function cleanCandidateString(string $raw): ?string
    {
        $candidate = trim(rtrim(trim($raw), '?!.,;:'));

        // Strip leading articles, prepositions and demonstratives
        $candidate = preg_replace('/^(?:ang\s+|yung\s+|iyong\s+|itong\s+|ng\s+|sa\s+|mga\s+|the\s+|a\s+|an\s+|this\s+|that\s+)+/iu', '', $candidate) ?? $candidate;

        // Strip trailing Filipino particles (do twice to handle paired particles e.g. "ba tayo", "pa ba")
        $candidate = preg_replace('/\s+(?:ba|tayo|natin|namin|po|pa|kaya|naman|bang|din|rin|pala|nga)+$/iu', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s+(?:ba|tayo|natin|namin|po|pa|kaya|naman|bang|din|rin|pala|nga)+$/iu', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s+(?:right now|today|at the moment|currently|ngayon|sa ngayon)+$/iu', '', $candidate) ?? $candidate;

        // Strip surrounding quotes
        $candidate = trim($candidate, " \t\n\r\0\x0B'\"");

        // Filter out action clauses and non-item descriptions
        if (preg_match('/\b(?:na need|na kailangan|kailangan|orderin|bilhin|iorder|irestock|paubos|ubos na|magkano|darating|parating|problema|issue|high[- ]risk|low[- ]stock|out[- ]of[- ]stock|expir(?:y|ed|ing)|malapit na)\b/iu', $candidate)) {
            return null;
        }

        // Filter out generic descriptors like "item na", "mga item na", "gamot na", "item ba na"
        if (preg_match('/^(?:item|items|gamot|supply|supplies)\s+(?:ba\s+|na\s+|bang\s+|kung\s+)/iu', $candidate)) {
            return null;
        }

        // Filter out standalone status or risk categories
        if (preg_match('/^(?:low[- ]stock|out[- ]of[- ]stock|high[- ]risk)(?:\s+items?)?$/iu', $candidate)) {
            return null;
        }

        $lower = strtolower($candidate);

        $genericExclusions = [
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

        if (in_array($lower, $genericExclusions, true)) {
            return null;
        }

        if (mb_strlen($candidate) < 2 || mb_strlen($candidate) > 50) {
            return null;
        }

        return $candidate;
    }
}
