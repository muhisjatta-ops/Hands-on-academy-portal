<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController
{
    /** The register for one class on one day, with every student listed. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'class_room_id' => ['required', 'exists:class_rooms,id'],
            'date'          => ['nullable', 'date'],
        ]);

        $classRoom = ClassRoom::with('gradeLevel')->findOrFail($data['class_room_id']);
        $date = $data['date'] ?? now()->toDateString();
        $term = Term::current();

        $this->authorizeClass($classRoom);

        $students = $classRoom->students()
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $existing = AttendanceRecord::where('class_room_id', $classRoom->id)
            ->whereDate('attended_on', $date)
            ->get()
            ->keyBy('student_id');

        return response()->json([
            'class_room' => ['id' => $classRoom->id, 'name' => $classRoom->name],
            'date'       => $date,
            'term'       => $term ? ['id' => $term->id, 'label' => $term->label()] : null,
            'states'     => AttendanceRecord::STATES,
            'rows'       => $students->map(function ($s) use ($existing, $term) {
                $record = $existing->get($s->id);

                return [
                    'student_id'       => $s->id,
                    'name'             => $s->full_name,
                    'admission_number' => $s->admission_number,
                    'state'            => $record?->state,
                    'reason'           => $record?->reason,
                    'term_rate'        => $term
                        ? AttendanceRecord::rateFor($s->id, $term->id)
                        : null,
                ];
            }),
        ]);
    }

    /** Save the whole register in one transaction. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'class_room_id'      => ['required', 'exists:class_rooms,id'],
            'date'               => ['required', 'date', 'before_or_equal:today'],
            'rows'               => ['required', 'array', 'min:1'],
            'rows.*.student_id'  => ['required', 'exists:students,id'],
            'rows.*.state'       => ['required', 'in:P,A,L,E'],
            'rows.*.reason'      => ['nullable', 'string', 'max:200'],
        ]);

        $classRoom = ClassRoom::findOrFail($data['class_room_id']);
        $this->authorizeClass($classRoom);

        $term = Term::current();

        if (! $term) {
            return response()->json(['message' => 'No current term is set.'], 422);
        }

        // A date outside the term is almost always a typo in the date box.
        if ($data['date'] < $term->start_date->toDateString()
            || $data['date'] > $term->end_date->toDateString()) {
            return response()->json([
                'message' => 'That date falls outside ' . $term->label() . '.',
            ], 422);
        }

        DB::transaction(function () use ($data, $classRoom, $term) {
            foreach ($data['rows'] as $row) {
                AttendanceRecord::updateOrCreate(
                    [
                        'student_id'  => $row['student_id'],
                        'attended_on' => $data['date'],
                    ],
                    [
                        'class_room_id' => $classRoom->id,
                        'term_id'       => $term->id,
                        'state'         => $row['state'],
                        'reason'        => $row['reason'] ?? null,
                        'recorded_by'   => Auth::id(),
                    ]
                );
            }
        });

        return response()->json([
            'message' => count($data['rows']) . ' student(s) marked.',
        ]);
    }

    /** Absentee report for a term: who is missing school most. */
    public function absentees(Request $request)
    {
        $termId = $request->query('term_id') ?: Term::current()?->id;

        $rows = DB::table('attendance_records')
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->where('attendance_records.term_id', $termId)
            ->whereIn('attendance_records.state', ['P', 'A', 'L'])
            ->groupBy('students.id', 'students.first_name', 'students.last_name', 'students.admission_number')
            ->select([
                'students.id',
                'students.admission_number',
                DB::raw("students.first_name || ' ' || students.last_name as name"),
                DB::raw('count(*) as marked'),
                DB::raw("sum(case when attendance_records.state = 'A' then 1 else 0 end) as absent"),
            ])
            ->havingRaw("sum(case when attendance_records.state = 'A' then 1 else 0 end) > 0")
            ->orderByRaw('absent desc')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'student_id'       => $r->id,
                'admission_number' => $r->admission_number,
                'name'             => $r->name,
                'days_marked'      => (int) $r->marked,
                'days_absent'      => (int) $r->absent,
                'rate'             => round((($r->marked - $r->absent) / $r->marked) * 100, 1),
            ]);

        return response()->json(['term_id' => $termId, 'rows' => $rows]);
    }

    private function authorizeClass(ClassRoom $classRoom): void
    {
        $user = Auth::user();

        if ($user->can('students.view') && $user->hasAnyRole(['admin', 'super-admin', 'registrar'])) {
            return;
        }

        $mine = $classRoom->class_teacher_id === $user->id
            || \App\Models\TeachingAssignment::where('user_id', $user->id)
                ->where('class_room_id', $classRoom->id)
                ->exists();

        abort_unless($mine, 403, 'You do not teach ' . $classRoom->name . '.');
    }
}
