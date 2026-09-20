<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use Auditable;

    protected $fillable = [
        'student_id', 'class_room_id', 'academic_year_id',
        'date_enrolled', 'is_repeating', 'outcome',
    ];

    protected $casts = [
        'date_enrolled' => 'date',
        'is_repeating'  => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
