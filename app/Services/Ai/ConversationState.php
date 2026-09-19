<?php

namespace App\Services\Ai;

use App\Models\InventoryItem;

/**
 * Encapsulates structured multi-turn conversation state.
 *
 * Holds the semantic context carried across conversational turns, including
 * the active topic, focus entity/item, previous assistant action, and
 * recent conversation signals.
 */
class ConversationState
{
    /**
     * @param  array<int, InventoryItem|array<string, mixed>>  $recentItems
     * @param  array<string, mixed>  $activeFilters
     */
    public function __construct(
        public readonly bool $hasHistory = false,
        public readonly ?string $lastUserMessage = null,
        public readonly ?string $lastAssistantMessage = null,
        public readonly ?string $currentTopic = null,
        public readonly ?string $currentModule = null,
        public readonly ?string $currentEntityType = null,
        public readonly ?int $currentEntityId = null,
        public readonly ?string $currentEntityName = null,
        public readonly ?InventoryItem $focusItem = null,
        public readonly ?string $lastIntent = null,
        public readonly ?string $lastAssistantAction = null,
        public readonly array $recentItems = [],
        public readonly array $activeFilters = [],
        public readonly string $language = 'en',
    ) {}

    /**
     * Determine if there is an active item under discussion.
     */
    public function hasFocusItem(): bool
    {
        return $this->focusItem !== null;
    }

    /**
     * Determine if the last assistant action presented a replenishment or low-stock list.
     */
    public function isLowStockContext(): bool
    {
        return $this->lastAssistantAction === 'presented_low_stock'
            || $this->currentTopic === 'replenishment'
            || $this->currentTopic === 'low_stock';
    }

    /**
     * Determine if the last assistant action was a greeting or pleasantry.
     */
    public function isGreetingContext(): bool
    {
        return $this->lastAssistantAction === 'greeting'
            || $this->currentTopic === 'greeting';
    }

    /**
     * Determine if the previous turn presented an item dossier or specific item information.
     */
    public function isItemDossierContext(): bool
    {
        return $this->lastAssistantAction === 'presented_item_dossier'
            || ($this->focusItem !== null && $this->currentTopic === 'item_details');
    }
}
