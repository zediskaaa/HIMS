<?php

namespace App\Services;

use Illuminate\Support\Str;

class AiChatTitleGenerator
{
    /**
     * Generate a concise, human-friendly conversation title without calling external AI APIs.
     */
    public static function generate(string $message, ?string $attachmentName = null): string
    {
        $clean = trim($message);
        $lower = Str::lower($clean);

        // 1. Check attachment analysis patterns
        if ($attachmentName !== null && $attachmentName !== '') {
            $baseName = pathinfo($attachmentName, PATHINFO_FILENAME);
            $cleanBase = preg_replace('/[_\-]+/', ' ', $baseName);
            $cleanBase = trim(preg_replace('/\s+/', ' ', (string) $cleanBase));

            if ($clean === '' || preg_match('/\b(analyze|check|review|inspect|look at|file|attachment|report|spreadsheet)\b/i', $clean)) {
                if (preg_match('/inventory/i', $cleanBase) || preg_match('/inventory/i', $clean)) {
                    return 'Inventory File Analysis';
                }
                if (preg_match('/forecast|demand/i', $cleanBase)) {
                    return 'Forecast File Analysis';
                }
                if (preg_match('/stock|movement/i', $cleanBase)) {
                    return 'Stock File Analysis';
                }
                if (strlen($cleanBase) >= 3 && strlen($cleanBase) <= 25) {
                    return Str::headline($cleanBase) . ' Analysis';
                }

                return 'Inventory File Analysis';
            }
        }

        if ($clean === '') {
            return 'New Chat';
        }

        // 2. Low stock / stock levels / out of stock
        if (preg_match('/\b(low\s+in\s+stock|low\s+stock|low-stock|running\s+out|critical\s+stock)\b/i', $lower)) {
            return 'Low Stock Items';
        }
        if (preg_match('/\b(out\s+of\s+stock|zero\s+stock|depleted|unavailable)\b/i', $lower)) {
            return 'Out of Stock Items';
        }

        // 3. Demand forecast
        if (preg_match('/\b(demand\s+forecast|demand\s+plan|forecast|predicted\s+demand|projections?)\b/i', $lower)) {
            return 'Demand Forecast';
        }

        // 4. Reorder / replenishment
        if (preg_match('/\b(what\s+should\s+we\s+reorder|reorder|restock|replenish|replenishment)\b/i', $lower)) {
            return 'Reorder Recommendations';
        }

        // 5. Expiration / expiring batches
        if (preg_match('/\b(expir(y|ing|ed|e)|shelf\s+life)\b/i', $lower)) {
            return 'Expiring Inventory';
        }

        // 6. Stock movements / transfers
        if (preg_match('/\b(stock\s+movements?|movements?|transfers?|stock\s+in|stock\s+out)\b/i', $lower)) {
            return 'Stock Movements';
        }

        // 7. Inventory status / summary
        if (preg_match('/\b(summarize\s+inventory|inventory\s+status|inventory\s+summary|overview|status\s+summary)\b/i', $lower)) {
            return 'Inventory Summary';
        }

        // 8. Risk analysis
        if (preg_match('/\b(high\s+risk|risk\s+level|risk\s+analysis|inventory\s+risk)\b/i', $lower)) {
            return 'Inventory Risk Review';
        }

        // 9. Specific items (e.g. "Paracetamol", "Gloves", "Scalpels")
        if (preg_match('/\b(?:for|of|on|about|check|status\s+of)\s+([A-Za-z0-9\s]{3,30}?)(?:\?|\.|$)/i', $clean, $matches)) {
            $candidate = trim($matches[1]);
            $candidateLower = strtolower($candidate);
            $exclusions = ['this', 'that', 'it', 'them', 'the items', 'the item', 'all items', 'inventory', 'stock'];
            if (! in_array($candidateLower, $exclusions, true) && strlen($candidate) >= 3) {
                return Str::headline($candidate) . ' Stock';
            }
        }

        // 10. Concise headline from the message if reasonable
        $words = preg_split('/\s+/', $clean);
        if (is_array($words) && count($words) <= 5 && strlen($clean) <= 30) {
            $stripped = trim((string) preg_replace('/[?!.,;:]+$/', '', $clean));
            $headline = Str::headline($stripped);
            if (strlen($headline) >= 3) {
                return $headline;
            }
        }

        return 'New Chat';
    }
}
