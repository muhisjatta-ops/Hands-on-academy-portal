<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'class_room_id', 'subject_id', 'term_id', 'name',
        'max_score', 'weight', 'assessed_on', 'is_published', 'created_by',
    ];

    protected $casts = [
        'max_score'    => 'decimal:2',
        'weight'       => 'decimal:2',
        'assessed_on'  => 'date',
        'is_published' => 'boolean',
    ];

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function scores()
    {
        return $this->hasMany(Score::class);
    }
}
