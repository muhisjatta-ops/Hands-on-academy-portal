<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two separate tables on purpose.
     *
     * audit_logs   = "who changed this record, and what was the old value"
     *                (grades, payments, fee structures, role assignments)
     * security_events = "who tried to get in, from where, and did it work"
     *                (logins, failures, lockouts, 2FA, password resets)
     *
     * Conflating them makes both harder to query. The first is about data
     * integrity disputes; the second is about intrusion.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();
            // Denormalised so the log stays readable after a user is deleted.
            $table->string('user_label')->nullable();

            $table->string('event', 20);               // created|updated|deleted
            $table->string('auditable_type');          // App\Models\Payment
            $table->unsignedBigInteger('auditable_id');

            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('reason')->nullable();      // for grade/fee overrides

            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();
            // Keep the attempted identifier even when no user matched —
            // that is exactly the case you want to investigate.
            $table->string('identifier')->nullable();

            $table->string('event', 40);
            // login.success, login.failed, login.locked, logout,
            // two_factor.challenged, two_factor.failed, two_factor.enabled,
            // two_factor.disabled, password.reset_requested,
            // password.reset_completed, session.revoked, role.changed

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('context')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('audit_logs');
    }
};
