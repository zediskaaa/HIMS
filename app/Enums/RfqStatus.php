<?php

namespace App\Enums;

enum RfqStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case BiddingClosed = 'bidding_closed';
    case UnderEvaluation = 'under_evaluation';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published (Canvassing Open)',
            self::BiddingClosed => 'Bidding Closed (Sealed)',
            self::UnderEvaluation => 'Under Comparative Evaluation',
            self::Awarded => 'Awarded',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Cancelled],
            self::Published => [self::BiddingClosed, self::Cancelled],
            self::BiddingClosed => [self::UnderEvaluation, self::Cancelled],
            self::UnderEvaluation => [self::Awarded, self::Published, self::Cancelled],
            self::Awarded, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOpenForBidding(): bool
    {
        return $this === self::Published;
    }
}
