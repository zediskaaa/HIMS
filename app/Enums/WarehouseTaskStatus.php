<?php

namespace App\Enums;

enum WarehouseTaskStatus: string
{
    case Ready = 'ready';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case PartiallyCompleted = 'partially_completed';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Exception = 'exception';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Ready => [self::Assigned, self::InProgress, self::Blocked, self::Cancelled],
            self::Assigned => [self::InProgress, self::Blocked, self::Cancelled],
            self::InProgress => [self::PartiallyCompleted, self::Completed, self::Blocked, self::Exception, self::Cancelled],
            self::PartiallyCompleted => [self::InProgress, self::Completed, self::Blocked, self::Exception, self::Cancelled],
            self::Blocked, self::Exception => [self::Ready, self::Assigned, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
