<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\PasswordStandard;
use App\Services\AuditLogger;
use App\Services\PasswordHistoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hims:create-super-admin
        {--name= : Full name of the Super Administrator}
        {--email= : Email address of the Super Administrator}
        {--phone= : Contact phone number}
        {--password= : Initial password for the account}
        {--department=Administration : Department}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a new Super Administrator account safely with full validation and password history tracking';

    public function handle(PasswordHistoryService $passwords, AuditLogger $audit): int
    {
        $name = (string) ($this->option('name') ?? ($this->input->isInteractive() ? $this->ask('Full Name (e.g. Jayson A. Pinggoy)') : ''));
        $email = (string) ($this->option('email') ?? ($this->input->isInteractive() ? $this->ask('Email Address') : ''));
        $phone = $this->option('phone');
        $password = (string) ($this->option('password') ?? ($this->input->isInteractive() ? $this->secret('Password') : ''));
        $department = (string) ($this->option('department') ?: 'Administration');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'phone' => filled($phone) ? $phone : null,
            'password' => $password,
            'department' => $department,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', new PasswordStandard()],
            'department' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $employeeId = $this->availableEmployeeId();

            $user = $passwords->usePassword(
                $password,
                function (string $passwordHash) use ($name, $email, $phone, $department, $employeeId): User {
                    $user = new User();
                    $user->forceFill([
                        'name' => $name,
                        'email' => $email,
                        'phone' => filled($phone) ? $phone : null,
                        'role' => UserRole::SuperAdministrator,
                        'status' => UserStatus::Active,
                        'is_protected' => false,
                        'employee_id' => $employeeId,
                        'department' => $department,
                        'email_verified_at' => now(),
                        'password' => $passwordHash,
                        'password_changed_at' => now(),
                    ]);
                    $user->save();

                    return $user;
                }
            );

            $audit->log(
                AuditAction::CreatedUser,
                null,
                "Super Administrator account provisioned via CLI: {$user->email}",
                $user,
                $user->name,
                [],
                [
                    'role' => UserRole::SuperAdministrator->value,
                    'status' => UserStatus::Active->value,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                ]
            );

            $this->info('Super Administrator account created successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Employee ID', $user->employee_id],
                    ['Name', $user->name],
                    ['Email', $user->email],
                    ['Phone', $user->phone ?? 'N/A'],
                    ['Role', $user->role->label()],
                    ['Status', $user->status->label()],
                ]
            );

            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Failed to create Super Administrator: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function availableEmployeeId(): string
    {
        $number = 1;

        do {
            $employeeId = 'SA-' . str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (User::query()->where('employee_id', $employeeId)->exists());

        return $employeeId;
    }
}
