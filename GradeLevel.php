<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeLevel extends Model
{
    protected $fillable = ['name', 'sequence'];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class);
    }

    public function classRooms()
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }

    /** The next rung up, for end-of-year promotion. */
    public function nextLevel(): ?self
    {
        return static::where('sequence', '>', $this->sequence)
            ->orderBy('sequence')
            ->first();
    }
}
