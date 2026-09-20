<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Add `use Auditable;` to any model whose history you must be able to defend:
 * Payment, Invoice, Score, FeeStructure, Enrollment, User.
 *
 * Do NOT add it to high-churn models like AttendanceRecord unless you need
 * to — 1,200 students x 190 school days is a lot of rows.
 *
 * Optionally declare on the model:
 *   protected array $auditExclude = ['updated_at', 'remember_token'];
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', [], $model->auditableAttributes()));

        static::updated(function ($model) {
            $new = $model->auditableChanges();

            if (empty($new)) {
                return;   // nothing meaningful changed; don't log noise
            }

            $old = array_intersect_key($model->getOriginal(), $new);
            $model->writeAudit('updated', $old, $new);
        });

        static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditableAttributes(), []));
    }

    protected function auditExcluded(): array
    {
        return array_merge(
            ['created_at', 'updated_at', 'remember_token', 'password',
             'two_factor_secret', 'two_factor_recovery_codes'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );
    }

    protected function auditableAttributes(): array
    {
        return array_diff_key($this->getAttributes(), array_flip($this->auditExcluded()));
    }

    protected function auditableChanges(): array
    {
        return array_diff_key($this->getChanges(), array_flip($this->auditExcluded()));
    }

    public function writeAudit(string $event, array $old, array $new, ?string $reason = null): void
    {
        $user = Auth::user();

        AuditLog::create([
            'user_id'        => $user?->id,
            'user_label'     => $user?->name ?? 'system',
            'event'          => $event,
            'auditable_type' => static::class,
            'auditable_id'   => $this->getKey(),
            'old_values'     => $old ?: null,
            'new_values'     => $new ?: null,
            'ip_address'     => Request::ip(),
            'user_agent'     => substr((string) Request::userAgent(), 0, 500),
            'reason'         => $reason ?? request()->input('audit_reason'),
        ]);
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }
}
