<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The bridge that makes row-level teacher permissions possible: a teacher
 * may touch marks for exactly the (class, subject) pairs listed here.
 */
class TeachingAssignment extends Model
{
    protected $fillable = ['user_id', 'class_room_id', 'subject_id'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public static function teaches(int $userId, int $classRoomId, int $subjectId): bool
    {
        return static::where('user_id', $userId)
            ->where('class_room_id', $classRoomId)
            ->where('subject_id', $subjectId)
            ->exists();
    }
}
