<?php

namespace App\Enums;

enum AuditAction: string
{
    case CreatedUser = 'created_user';
    case UpdatedUser = 'updated_user';
    case DeletedUser = 'deleted_user';
    case LoggedIn = 'logged_in';
    case LoggedOut = 'logged_out';
    case ChangedPassword = 'changed_password';
    case TemporarilyLockedUser = 'temporarily_locked_user';
    case UnlockedUser = 'unlocked_user';

    public function label(): string
    {
        return match ($this) {
            self::CreatedUser => 'Created User',
            self::UpdatedUser => 'Updated User',
            self::DeletedUser => 'Deleted User',
            self::LoggedIn => 'Logged In',
            self::LoggedOut => 'Logged Out',
            self::ChangedPassword => 'Changed Password',
            self::TemporarilyLockedUser => 'Temporarily Locked User',
            self::UnlockedUser => 'Unlocked User',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $action) => [$action->value => $action->label()])
            ->all();
    }
}
