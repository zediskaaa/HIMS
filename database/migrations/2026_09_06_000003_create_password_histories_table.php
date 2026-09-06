<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('password_hash');
            $table->char('password_fingerprint', 64)->nullable()->unique();
            $table->timestamp('used_at')->index();
        });

        // Existing plaintext passwords cannot and must not be recovered. Their
        // current hashes are enough for Hash::check() to block future reuse.
        DB::table('users')
            ->select(['id', 'password', 'password_changed_at', 'created_at'])
            ->orderBy('id')
            ->chunkById(250, function ($users): void {
                DB::table('password_histories')->insert(
                    $users->map(fn ($user): array => [
                        'user_id' => $user->id,
                        'password_hash' => $user->password,
                        'password_fingerprint' => null,
                        'used_at' => $user->password_changed_at ?? $user->created_at ?? now(),
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
