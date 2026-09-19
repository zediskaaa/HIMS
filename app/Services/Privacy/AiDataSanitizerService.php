<?php

namespace App\Services\Privacy;

class AiDataSanitizerService
{
    /**
     * Inspect and redact personal and sensitive data from user queries and attachments
     * before transmitting payload to external AI services.
     *
     * @return array{sanitized_text: string, redaction_count: int, redacted_types: array<int, string>}
     */
    public function sanitize(string $input): array
    {
        $text = $input;
        $redactedTypes = [];
        $totalRedactions = 0;

        // 1. Philippine PhilHealth Identification Number (e.g. 12-345678901-2)
        $philHealthPattern = '/\b\d{2}-\d{9}-\d{1}\b/';
        if (preg_match($philHealthPattern, $text)) {
            $text = preg_replace($philHealthPattern, '[REDACTED PHILHEALTH PIN]', $text, -1, $count);
            $totalRedactions += $count;
            $redactedTypes[] = 'PhilHealth PIN';
        }

        // 2. Philippine TIN (e.g. 123-456-789 or 123-456-789-000)
        $tinPattern = '/\b\d{3}-\d{3}-\d{3}(-\d{3,5})?\b/';
        if (preg_match($tinPattern, $text)) {
            $text = preg_replace($tinPattern, '[REDACTED TIN]', $text, -1, $count);
            $totalRedactions += $count;
            $redactedTypes[] = 'Tax Identification Number (TIN)';
        }

        // 3. Philippine Mobile Numbers (e.g. 09171234567 or +639171234567)
        $phonePattern = '/(?<!\w)(?:\+63|0)9\d{9}\b/';
        if (preg_match($phonePattern, $text)) {
            $text = preg_replace($phonePattern, '[REDACTED PH PHONE]', $text, -1, $count);
            $totalRedactions += $count;
            $redactedTypes[] = 'Phone Number';
        }

        // 4. Email Addresses
        $emailPattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/';
        if (preg_match($emailPattern, $text)) {
            $text = preg_replace($emailPattern, '[REDACTED EMAIL]', $text, -1, $count);
            $totalRedactions += $count;
            $redactedTypes[] = 'Email Address';
        }

        // 5. Passwords, API Keys, Tokens in key-value format
        $secretPattern = '/(?i)(password|passwd|secret|api_key|token|auth_token)\s*[:=]\s*(\S+)/';
        if (preg_match($secretPattern, $text)) {
            $text = preg_replace($secretPattern, '$1: [REDACTED SECRET]', $text, -1, $count);
            $totalRedactions += $count;
            $redactedTypes[] = 'Credentials / Secret';
        }

        // 6. 16-digit Card Numbers
        $cardPattern = '/\b(?:\d{4}[ -]?){3}\d{4}\b/';
        if (preg_match($cardPattern, $text)) {
            $text = preg_replace($cardPattern, '[REDACTED PAYMENT CARD]', $text, -1, $count);
            $totalRedactions += $count;
            $redactedTypes[] = 'Payment Card';
        }

        return [
            'redacted' => $totalRedactions > 0,
            'sanitized_text' => $text,
            'redaction_count' => $totalRedactions,
            'redacted_types' => array_values(array_unique($redactedTypes)),
        ];
    }
}
