<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Keep the legacy `name` column as the canonical display value so
            // existing authentication, audit trails, and integrations continue
            // to work while user management gains structured name fields.
            $table->string('surname')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    [$firstName, $middleName, $surname] = $this->splitLegacyName($user->name);

                    DB::table('users')->where('id', $user->id)->update([
                        'first_name' => $firstName,
                        'middle_name' => $middleName,
                        'surname' => $surname,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['surname', 'first_name', 'middle_name']);
        });
    }

    /**
     * Best-effort split for existing rows. The original `name` is deliberately
     * left untouched, so ambiguous compound names never lose information.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function splitLegacyName(?string $name): array
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
};
