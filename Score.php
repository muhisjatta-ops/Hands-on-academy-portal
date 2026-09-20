<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Audited: a changed mark is the single most disputed record in a school.
 * The audit row records the old value, the new value, who and when.
 */
class Score extends Model
{
    use Auditable;

    protected $fillable = ['assessment_id', 'student_id', 'score', 'remark', 'entered_by'];

    protected $casts = ['score' => 'decimal:2'];

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function percentage(): ?float
    {
        if ($this->score === null) {
            return null;
        }

        $max = (float) $this->assessment->max_score;

        return $max > 0 ? round(((float) $this->score / $max) * 100, 2) : null;
    }
}
