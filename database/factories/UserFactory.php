<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Defaults to the least-privileged role. A test that needs to do something
     * has to say so, which keeps the permission being exercised visible in the
     * test itself rather than inherited silently from the factory.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'password_changed_at' => now(),
            'remember_token' => Str::random(10),
            'role' => UserRole::Viewer,
            'status' => UserStatus::Active,
            'mfa_enabled' => false,
            'employee_id' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'department' => fake()->randomElement(['Pharmacy', 'Central Supply', 'Laboratory', 'Nursing']),
            'phone' => '09'.fake()->numerify('#########'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    public function administrator(): static
    {
        return $this->role(UserRole::Administrator);
    }

    public function superAdministrator(): static
    {
        return $this->role(UserRole::SuperAdministrator);
    }

    public function inventoryManager(): static
    {
        return $this->role(UserRole::InventoryManager);
    }

    public function warehouseStaff(): static
    {
        return $this->role(UserRole::WarehouseStaff);
    }

    public function pharmacyStaff(): static
    {
        return $this->role(UserRole::PharmacyStaff);
    }

    public function viewer(): static
    {
        return $this->role(UserRole::Viewer);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => UserStatus::Inactive]);
    }
}
