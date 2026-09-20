<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'is_current'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function terms()
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }

    public function classRooms()
    {
        return $this->hasMany(ClassRoom::class);
    }

    /** The one year the school is currently running. */
    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }

    /**
     * Only one year may be current. Doing this in one transaction stops the
     * window where two years are current and every query returns doubles.
     */
    public function makeCurrent(): void
    {
        \DB::transaction(function () {
            static::where('is_current', true)->update(['is_current' => false]);
            $this->update(['is_current' => true]);
        });
    }
}
