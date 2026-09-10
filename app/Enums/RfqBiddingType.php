<?php

namespace App\Enums;

enum RfqBiddingType: string
{
    case Sealed = 'sealed';
    case Open = 'open';

    public function label(): string
    {
        return match ($this) {
            self::Sealed => 'Sealed Bid (Masked until close)',
            self::Open => 'Open Canvass / Quotation',
        };
    }

    public function isSealed(): bool
    {
        return $this === self::Sealed;
    }
}
