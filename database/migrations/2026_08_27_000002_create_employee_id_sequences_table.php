<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_id_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_value');
        });

        $largestExistingNumber = 0;

        DB::table('users')
            ->whereNotNull('employee_id')
            ->orderBy('id')
            ->pluck('employee_id')
            ->each(function (string $employeeId) use (&$largestExistingNumber): void {
                if (preg_match('/^EMP-(\d+)$/', $employeeId, $matches) === 1) {
                    $largestExistingNumber = max($largestExistingNumber, (int) $matches[1]);
                }
            });

        DB::table('employee_id_sequences')->insert([
            'id' => 1,
            'next_value' => $largestExistingNumber + 1,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_id_sequences');
    }
};
