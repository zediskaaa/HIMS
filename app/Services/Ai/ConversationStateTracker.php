<?php

namespace App\Services\Ai;

use App\Models\InventoryItem;
use Illuminate\Support\Str;

/**
 * Reconstructs and tracks structured conversation state across multi-turn interactions.
 *
 * Resolves active topics, focus items, semantic assistant actions, and
 * conversational context across dialogue turns, ensuring that intervening
 * pleasantries or acknowledgments ("okay", "salamat") do not wipe out domain context.
 */
class ConversationStateTracker
{
    public function __construct(
        private readonly ConversationalEntityTracker $entityTracker,
        private readonly HimsAiToolRegistry $tools,
    ) {}

    /**
     * Extract structured conversation state from current query and history.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function extract(string $userQuery, array $history = []): ConversationState
    {
        $hasHistory = ! empty($history);
        $lastUserMessage = null;
        $lastAssistantMessage = null;

        // Traverse history to extract last user and assistant messages
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $role = $history[$i]['role'] ?? '';
            $content = trim((string) ($history[$i]['content'] ?? ''));

            if ($role === 'user' && $lastUserMessage === null && $content !== '') {
                $lastUserMessage = $content;
            }
            if ($role === 'assistant' && $lastAssistantMessage === null && $content !== '') {
                $lastAssistantMessage = $content;
            }

            if ($lastUserMessage !== null && $lastAssistantMessage !== null) {
                break;
            }
        }

        // Entity resolution for current query and history
        $entityResolution = $this->entityTracker->resolve($userQuery, $history);
        $focusItem = $entityResolution['focus_item'] ?? null;
        if ($focusItem === null && ! empty($history)) {
            $focusItem = $this->entityTracker->findMostRecentItemInHistory($history);
        }

        // Semantic analysis of dialogue history
        $lastAssistantAction = $this->detectLastAssistantAction($lastAssistantMessage, $lastUserMessage);
        $currentTopic = $this->detectCurrentTopic($lastAssistantAction, $lastAssistantMessage, $lastUserMessage, $focusItem);
        $currentModule = $this->resolveModuleFromTopic($currentTopic);

        // Language detection
        $language = $this->detectLanguage($userQuery, $lastUserMessage);

        return new ConversationState(
            hasHistory: $hasHistory,
            lastUserMessage: $lastUserMessage,
            lastAssistantMessage: $lastAssistantMessage,
            currentTopic: $currentTopic,
            currentModule: $currentModule,
            currentEntityType: $focusItem ? 'item' : null,
            currentEntityId: $focusItem?->id,
            currentEntityName: $focusItem?->name,
            focusItem: $focusItem,
            lastIntent: null,
            lastAssistantAction: $lastAssistantAction,
            recentItems: $entityResolution['recent_items']?->all() ?? [],
            activeFilters: [],
            language: $language,
        );
    }

    /**
     * Detect the semantic action represented in the previous assistant turn.
     */
    private function detectLastAssistantAction(?string $assistantMessage, ?string $userMessage): ?string
    {
        if ($assistantMessage === null || $assistantMessage === '') {
            return null;
        }

        $norm = Str::lower($assistantMessage);
        $userNorm = Str::lower($userMessage ?? '');

        // 1. Priority question follow-up context ("Which item should I check first?")
        if (preg_match('/\b(?:which item|alin.*una|unang titingnan|first item|priority item)\b/i', $userNorm)
            || Str::contains($norm, ['first item is', 'check first', 'priority item'])) {
            return 'presented_priority_item';
        }

        // 2. Low-stock / Replenishment list
        if (Str::contains($norm, [
            'items requiring replenishment',
            'below their reorder level',
            'low-stock items',
            'low on stock',
            'out-of-stock items',
            'suggested order',
            'reorder level',
        ]) && Str::contains($norm, ['sku', 'available', 'units', 'stock'])) {
            return 'presented_low_stock';
        }

        // 3. Item dossier or availability
        if (Str::contains($norm, [
            'verified hims inventory record',
            'physical stock on hand',
            'available for dispensing',
            'stock dossier',
            'i found',
            'we have',
        ]) && Str::contains($norm, ['sku', 'units', 'pieces', 'boxes', 'bottles', 'vials'])) {
            return 'presented_item_dossier';
        }

        // 4. Greeting or pleasantry response
        if (preg_match('/^(good\s+(?:morning|afternoon|evening|day)|magandang\s+(?:umaga|hapon|gabi)|hello|hi|kamusta|kumusta)[!.,]/i', trim($assistantMessage))) {
            return 'greeting';
        }

        // 5. Clarification or disambiguation
        if (Str::contains($norm, ['which specific item', 'did you mean', 'do you mean inventory', 'what specific inventory detail'])) {
            return 'clarification';
        }

        return 'general_response';
    }

    /**
     * Determine active domain topic based on action, messages, and focus item.
     */
    private function detectCurrentTopic(?string $action, ?string $assistantMsg, ?string $userMsg, ?InventoryItem $focusItem): ?string
    {
        if ($focusItem !== null) {
            return 'item_details';
        }

        if ($action === 'presented_low_stock' || $action === 'presented_priority_item') {
            return 'replenishment';
        }

        if ($action === 'greeting') {
            return 'greeting';
        }

        $normUser = Str::lower($userMsg ?? '');
        $normAsst = Str::lower($assistantMsg ?? '');

        if (Str::contains($normUser . ' ' . $normAsst, ['expiry', 'expire', 'batch', 'fefo'])) {
            return 'batches_and_expiry';
        }

        if (Str::contains($normUser . ' ' . $normAsst, ['supplier', 'vendor', 'lead time'])) {
            return 'suppliers';
        }

        if (Str::contains($normUser . ' ' . $normAsst, ['purchase order', 'procurement', 'po number'])) {
            return 'procurement';
        }

        if (Str::contains($normUser . ' ' . $normAsst, ['delivery', 'shipment', 'delayed', 'carrier'])) {
            return 'shipments';
        }

        if (Str::contains($normUser . ' ' . $normAsst, ['location', 'storeroom', 'warehouse', 'cabinet'])) {
            return 'locations';
        }

        return null;
    }

    /**
     * Map active topic to corresponding HIMS module.
     */
    private function resolveModuleFromTopic(?string $topic): ?string
    {
        return match ($topic) {
            'item_details', 'replenishment', 'low_stock' => 'inventory',
            'batches_and_expiry' => 'batches',
            'suppliers' => 'suppliers',
            'procurement' => 'purchases',
            'shipments' => 'receiving',
            'locations' => 'storage_locations',
            default => null,
        };
    }

    /**
     * Detect conversation language (Tagalog, English, or Taglish).
     */
    private function detectLanguage(string $query, ?string $lastUserMessage): string
    {
        $combined = Str::lower($query . ' ' . ($lastUserMessage ?? ''));
        $tagalogWords = [
            'ba', 'tayo', 'natin', 'namin', 'po', 'meron', 'mayroon', 'wala', 'walang',
            'paubos', 'kailangan', 'anong', 'ano', 'ilan', 'sino', 'kailan', 'saan',
            'nasaan', 'magkano', 'ikaw', 'bahala', 'sige', 'yung', 'nito', 'iyan', 'ito',
        ];

        $matches = 0;
        foreach ($tagalogWords as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/i', $combined)) {
                $matches++;
            }
        }

        return $matches >= 2 ? 'taglish' : ($matches === 1 ? 'taglish' : 'en');
    }
}
