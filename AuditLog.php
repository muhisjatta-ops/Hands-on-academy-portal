<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_label', 'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'reason',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Human-readable diff for the UI: [field => [from, to]]
     */
    public function changes(): array
    {
        $out = [];
        foreach (($this->new_values ?? []) as $field => $new) {
            $out[$field] = [
                'from' => $this->old_values[$field] ?? null,
                'to'   => $new,
            ];
        }
        return $out;
    }
}
