<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    protected $fillable = [
        'grade_level_id', 'academic_year_id', 'stream',
        'class_teacher_id', 'capacity',
    ];

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function teachingAssignments()
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** Students currently in this class, active only. */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class, Enrollment::class,
            'class_room_id', 'id', 'id', 'student_id'
        )->where('students.status', 'ACTIVE');
    }

    public function getNameAttribute(): string
    {
        return trim($this->gradeLevel->name . ' ' . ($this->stream ?? ''));
    }
}
