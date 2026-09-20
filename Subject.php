<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['name', 'code', 'is_examinable'];

    protected $casts = ['is_examinable' => 'boolean'];

    public function gradeLevels()
    {
        return $this->belongsToMany(GradeLevel::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }
}
