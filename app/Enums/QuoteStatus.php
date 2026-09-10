<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Invited = 'invited';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Accepted => 'Accepted / Awarded',
            self::Declined => 'Declined / Rejected',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::Draft, self::Submitted, self::Declined],
            self::Draft => [self::Submitted, self::Declined],
            self::Submitted => [self::UnderReview, self::Declined],
            self::UnderReview => [self::Accepted, self::Declined],
            self::Accepted, self::Declined => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
