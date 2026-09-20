<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $fillable = [
        'academic_year_id', 'name', 'sequence',
        'start_date', 'end_date', 'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public static function current(): ?self
    {
        return static::with('academicYear')->where('is_current', true)->first();
    }

    public function makeCurrent(): void
    {
        \DB::transaction(function () {
            static::where('is_current', true)->update(['is_current' => false]);
            $this->update(['is_current' => true]);
        });
    }

    public function label(): string
    {
        return $this->academicYear->name . ' ' . $this->name;
    }
}
