<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeBand extends Model
{
    protected $fillable = ['letter', 'min_percent', 'max_percent', 'remark', 'sequence'];

    protected $casts = [
        'min_percent' => 'decimal:2',
        'max_percent' => 'decimal:2',
    ];

    /** Letter for a percentage, or null when nothing covers it. */
    public static function forPercent(?float $percent): ?self
    {
        if ($percent === null) {
            return null;
        }

        return static::where('min_percent', '<=', $percent)
            ->where('max_percent', '>=', $percent)
            ->orderBy('min_percent', 'desc')
            ->first();
    }
}
