<?php

namespace App\Policies;

use App\Models\Score;
use App\Models\TeachingAssignment;
use App\Models\User;

/**
 * This is the rule role-based access control cannot express.
 *
 * "Teacher" is a role; "may enter marks for Grade 4 Blue Mathematics" is a
 * fact about a row. Trying to encode it as a permission gives you names like
 * `scores.enter.grade4blue.maths` and a permissions table nobody can
 * maintain. It belongs here, checked per record, against the
 * teaching_assignments table.
 */
class ScorePolicy
{
    public function view(User $user, Score $score): bool
    {
        return $this->mayTouch($user, $score);
    }

    public function update(User $user, Score $score): bool
    {
        if (! $user->can('scores.enter')) {
            return false;
        }

        return $this->mayTouch($user, $score);
    }

    public function publish(User $user): bool
    {
        // Only the head teacher or admin releases marks to parents.
        return $user->can('scores.publish');
    }

    private function mayTouch(User $user, Score $score): bool
    {
        // Head teacher and admin see the whole school.
        if ($user->can('scores.publish')) {
            return true;
        }

        $assessment = $score->assessment;

        return TeachingAssignment::teaches(
            $user->id,
            $assessment->class_room_id,
            $assessment->subject_id
        );
    }
}
