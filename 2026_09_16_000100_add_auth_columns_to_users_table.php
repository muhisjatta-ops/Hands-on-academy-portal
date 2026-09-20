<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Staff/student identifier used for login alongside email.
            $table->string('username', 60)->unique()->nullable()->after('id');
            $table->string('phone', 30)->nullable();

            // --- Two-factor (TOTP) ---
            // Both columns are encrypted casts on the model, never plain text.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // --- Account state ---
            $table->boolean('is_active')->default(true);
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Lockout after repeated failures. Laravel's RateLimiter handles
            // throttling; this is the persistent, admin-visible lock.
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'phone',
                'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'is_active', 'password_changed_at', 'must_change_password',
                'last_login_at', 'last_login_ip',
                'failed_login_attempts', 'locked_until',
                'deleted_at',
            ]);
        });
    }
};
