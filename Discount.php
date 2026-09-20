<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use Auditable;

    protected $fillable = [
        'student_id', 'fee_category_id', 'reason',
        'percent', 'fixed_amount', 'valid_from', 'valid_to', 'granted_by',
    ];

    protected $casts = [
        'percent'      => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'valid_from'   => 'date',
        'valid_to'     => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function category()
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    public function scopeActiveOn($query, $date)
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });
    }

    /** What this discount takes off a given charge. */
    public function amountOff(float $charge): float
    {
        $off = ($charge * (float) $this->percent / 100) + (float) $this->fixed_amount;

        return round(min($off, $charge), 2);
    }
}
