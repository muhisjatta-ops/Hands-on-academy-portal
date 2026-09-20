<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use Auditable;

    protected $fillable = [
        'number', 'student_id', 'term_id',
        'issue_date', 'due_date', 'status', 'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date'   => 'date',
    ];

    protected $appends = ['total', 'paid', 'balance'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    // ---- derived money. Never columns. ----

    public function getTotalAttribute(): float
    {
        return round((float) $this->lines->sum('amount'), 2);
    }

    public function getPaidAttribute(): float
    {
        return round((float) $this->allocations->sum('amount'), 2);
    }

    public function getBalanceAttribute(): float
    {
        return round($this->total - $this->paid, 2);
    }

    public function isSettled(): bool
    {
        return $this->balance <= 0;
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'ISSUED');
    }

    public static function nextNumber(): string
    {
        $year = date('Y');
        $stem = 'INV-' . $year . '-';

        $last = static::where('number', 'like', $stem . '%')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('number');

        $next = $last ? ((int) substr($last, strlen($stem))) + 1 : 1;

        return $stem . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
