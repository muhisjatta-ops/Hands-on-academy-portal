<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    /**
     * A parent sees only their own children. Getting this wrong exposes the
     * whole school's records to every guardian account, which is the worst
     * bug this system could have.
     */
    public function view(User $user, Student $student): bool
    {
        if (! $user->can('students.view')) {
            return false;
        }

        if ($user->hasRole('parent')) {
            return $student->guardians()
                ->where('guardians.user_id', $user->id)
                ->exists();
        }

        if ($user->hasRole('student')) {
            return $student->user_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.update');
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can('students.delete');
    }
}
