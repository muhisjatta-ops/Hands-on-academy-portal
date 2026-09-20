<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    public const STATES = ['P' => 'Present', 'A' => 'Absent', 'L' => 'Late', 'E' => 'Excused'];

    protected $fillable = [
        'student_id', 'class_room_id', 'term_id',
        'attended_on', 'state', 'reason', 'recorded_by',
    ];

    protected $casts = ['attended_on' => 'date'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class);
    }

    /**
     * Present and Late both count as attending; Excused is neither present
     * nor held against the student, so it leaves the denominator.
     */
    public static function rateFor(int $studentId, int $termId): ?float
    {
        $rows = static::where('student_id', $studentId)
            ->where('term_id', $termId)
            ->whereIn('state', ['P', 'A', 'L'])
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $attended = $rows->whereIn('state', ['P', 'L'])->count();

        return round(($attended / $rows->count()) * 100, 1);
    }
}
