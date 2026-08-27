<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'first_name',
        'middle_name',
        'email',
        'password',
        'role',
        'status',
        'employee_id',
        'department',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Keep the legacy display name and the structured fields synchronized.
     * This also protects existing registration/profile flows that still write
     * only `name`.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty(['surname', 'first_name', 'middle_name'])) {
                $user->name = self::composeName(
                    $user->first_name,
                    $user->middle_name,
                    $user->surname,
                );

                return;
            }

            if ($user->isDirty('name')) {
                [$firstName, $middleName, $surname] = self::splitName($user->name);

                $user->first_name = $firstName;
                $user->middle_name = $middleName;
                $user->surname = $surname;
            }
        });
    }

    public static function composeName(?string $firstName, ?string $middleName, ?string $surname): string
    {
        return collect([$firstName, $middleName, $surname])
            ->map(fn (?string $part) => preg_replace('/\s+/u', ' ', trim((string) $part)))
            ->filter(fn (?string $part) => filled($part))
            ->implode(' ');
    }

    /**
     * @return array{first_name: ?string, middle_name: ?string, surname: ?string}
     */
    public function nameComponents(): array
    {
        if (filled($this->first_name) || filled($this->middle_name) || filled($this->surname)) {
            return [
                'first_name' => $this->first_name,
                'middle_name' => $this->middle_name,
                'surname' => $this->surname,
            ];
        }

        [$firstName, $middleName, $surname] = self::splitName($this->name);

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'surname' => $surname,
        ];
    }

    /**
     * Split legacy full names without changing their stored display value.
     * A comma-delimited "Surname, Given Names" value is also supported.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    public static function splitName(?string $name): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            return [null, null, null];
        }

        if (str_contains($name, ',')) {
            [$surname, $givenNames] = array_map('trim', explode(',', $name, 2));
            $parts = preg_split('/\s+/u', $givenNames, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $firstName = array_shift($parts);

            return [$firstName ?: null, $parts === [] ? null : implode(' ', $parts), $surname ?: null];
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) === 1) {
            return [$parts[0], null, null];
        }

        $firstName = array_shift($parts);
        $surname = array_pop($parts);

        return [$firstName, $parts === [] ? null : implode(' ', $parts), $surname];
    }

    /**
     * Stock movements this person recorded. The reason accounts are
     * deactivated instead of deleted — this history names them.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function acknowledgedAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class, 'acknowledged_by');
    }

    public function demandPlans(): HasMany
    {
        return $this->hasMany(DemandPlan::class, 'generated_by');
    }

    /**
     * Whether this account's role grants an ability.
     *
     * Inactive accounts hold no permissions at all, so a deactivated user who
     * still has a live session cannot act even before the next request logs
     * them out.
     */
    public function hasPermission(Permission|string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $permission = $permission instanceof Permission
            ? $permission
            : Permission::tryFrom($permission);

        return $permission !== null && $this->role->grants($permission);
    }

    public function hasRole(UserRole|string $role): bool
    {
        $role = $role instanceof UserRole ? $role : UserRole::tryFrom($role);

        return $role !== null && $this->role === $role;
    }

    public function isAdministrator(): bool
    {
        return $this->role->isAdministrator();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return $this->isActive() ? $this->role->permissions() : [];
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: '?';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }

    public function scopeRole(Builder $query, UserRole|string $role): Builder
    {
        return $query->where('role', $role instanceof UserRole ? $role->value : $role);
    }

    public function scopeAdministrators(Builder $query): Builder
    {
        return $query->role(UserRole::Administrator);
    }
}
