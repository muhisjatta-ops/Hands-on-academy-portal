<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController
{
    public function index(Request $request)
    {
        $term = Term::current();

        $students = Student::query()
            ->search($request->query('q'))
            ->when($request->query('class_room_id'),
                fn ($q, $id) => $q->inClassRoom($id))
            ->when($request->query('status'),
                fn ($q, $s) => $q->where('status', $s),
                fn ($q) => $q->active())
            ->with(['enrollments.classRoom.gradeLevel'])
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(min((int) $request->query('per_page', 25), 100))
            ->withQueryString();

        // Balances are computed per student. Fine at school scale; if the
        // roll grows past a few thousand, replace this with one aggregate
        // query rather than letting the loop grow.
        return response()->json([
            'data' => collect($students->items())->map(function (Student $s) use ($term) {
                $enrollment = $s->currentEnrollment();

                return [
                    'id'               => $s->id,
                    'admission_number' => $s->admission_number,
                    'full_name'        => $s->full_name,
                    'first_name'       => $s->first_name,
                    'last_name'        => $s->last_name,
                    'gender'           => $s->gender,
                    'date_of_birth'    => $s->date_of_birth?->toDateString(),
                    'status'           => $s->status,
                    'class_room'       => $enrollment ? [
                        'id'   => $enrollment->class_room_id,
                        'name' => $enrollment->classRoom->name,
                    ] : null,
                    'balance'          => $s->outstandingBalance(),
                ];
            }),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page'    => $students->lastPage(),
                'total'        => $students->total(),
                'per_page'     => $students->perPage(),
            ],
        ]);
    }

    public function show(Student $student)
    {
        $student->load(['guardians', 'enrollments.classRoom.gradeLevel', 'enrollments.academicYear']);

        return response()->json([
            'id'               => $student->id,
            'admission_number' => $student->admission_number,
            'full_name'        => $student->full_name,
            'first_name'       => $student->first_name,
            'middle_name'      => $student->middle_name,
            'last_name'        => $student->last_name,
            'date_of_birth'    => $student->date_of_birth?->toDateString(),
            'gender'           => $student->gender,
            'address'          => $student->address,
            'admission_date'   => $student->admission_date?->toDateString(),
            'status'           => $student->status,
            'notes'            => $student->notes,
            'balance'          => $student->outstandingBalance(),
            'guardians'        => $student->guardians->map(fn ($g) => [
                'id'           => $g->id,
                'full_name'    => $g->full_name,
                'relationship' => $g->relationship,
                'phone'        => $g->phone,
                'email'        => $g->email,
                'is_primary'   => (bool) $g->pivot->is_primary,
                'is_fee_payer' => (bool) $g->pivot->is_fee_payer,
            ]),
            // Class history, oldest first — this is why enrollments exist.
            'history' => $student->enrollments->sortBy('date_enrolled')->values()->map(fn ($e) => [
                'academic_year' => $e->academicYear->name,
                'class'         => $e->classRoom->name,
                'outcome'       => $e->outcome,
                'is_repeating'  => $e->is_repeating,
            ]),
        ]);
    }

    /** Admission: student + guardians + enrollment, all or nothing. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name'    => ['required', 'string', 'max:60'],
            'middle_name'   => ['nullable', 'string', 'max:60'],
            'last_name'     => ['required', 'string', 'max:60'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender'        => ['required', 'in:M,F'],
            'address'       => ['nullable', 'string'],
            'notes'         => ['nullable', 'string'],
            'class_room_id' => ['required', 'exists:class_rooms,id'],

            'guardians'                  => ['array'],
            'guardians.*.full_name'      => ['required', 'string', 'max:120'],
            'guardians.*.relationship'   => ['required', 'string', 'max:40'],
            'guardians.*.phone'          => ['required', 'string', 'max:30'],
            'guardians.*.email'          => ['nullable', 'email'],
            'guardians.*.occupation'     => ['nullable', 'string', 'max:80'],
            'guardians.*.is_primary'     => ['boolean'],
            'guardians.*.is_fee_payer'   => ['boolean'],
        ]);

        $year = AcademicYear::current();

        if (! $year) {
            return response()->json([
                'message' => 'Set the current academic year before admitting students.',
            ], 422);
        }

        $student = DB::transaction(function () use ($data, $year) {
            $student = Student::create([
                'admission_number' => Student::nextAdmissionNumber(),
                'first_name'       => $data['first_name'],
                'middle_name'      => $data['middle_name'] ?? null,
                'last_name'        => $data['last_name'],
                'date_of_birth'    => $data['date_of_birth'],
                'gender'           => $data['gender'],
                'address'          => $data['address'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'admission_date'   => now()->toDateString(),
                'status'           => 'ACTIVE',
            ]);

            foreach ($data['guardians'] ?? [] as $g) {
                // Match an existing guardian on phone so siblings share one
                // record instead of creating four copies of the same father.
                $guardian = \App\Models\Guardian::firstOrCreate(
                    ['phone' => $g['phone']],
                    [
                        'full_name'    => $g['full_name'],
                        'relationship' => $g['relationship'],
                        'email'        => $g['email'] ?? null,
                        'occupation'   => $g['occupation'] ?? null,
                    ]
                );

                $student->guardians()->attach($guardian->id, [
                    'is_primary'   => $g['is_primary'] ?? false,
                    'is_fee_payer' => $g['is_fee_payer'] ?? false,
                ]);
            }

            $student->enrollments()->create([
                'class_room_id'    => $data['class_room_id'],
                'academic_year_id' => $year->id,
                'date_enrolled'    => now()->toDateString(),
            ]);

            return $student;
        });

        return response()->json([
            'message' => $student->full_name . ' admitted as ' . $student->admission_number,
            'id'      => $student->id,
        ], 201);
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'first_name'    => ['sometimes', 'string', 'max:60'],
            'middle_name'   => ['nullable', 'string', 'max:60'],
            'last_name'     => ['sometimes', 'string', 'max:60'],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'gender'        => ['sometimes', 'in:M,F'],
            'address'       => ['nullable', 'string'],
            'notes'         => ['nullable', 'string'],
            'status'        => ['sometimes', 'in:' . implode(',', Student::STATUSES)],
        ]);

        $student->update($data);

        return response()->json(['message' => 'Changes saved.']);
    }

    /** Move a student to another class, keeping the year's enrollment row. */
    public function transfer(Request $request, Student $student)
    {
        $data = $request->validate([
            'class_room_id' => ['required', 'exists:class_rooms,id'],
        ]);

        $enrollment = $student->currentEnrollment();

        if (! $enrollment) {
            return response()->json([
                'message' => 'This student has no enrollment for the current year.',
            ], 422);
        }

        $enrollment->update(['class_room_id' => $data['class_room_id']]);

        return response()->json(['message' => 'Student transferred.']);
    }

    public function destroy(Student $student)
    {
        // Soft delete. Fee and mark history must survive.
        $student->delete();

        return response()->json(['message' => 'Student record archived.']);
    }
}
