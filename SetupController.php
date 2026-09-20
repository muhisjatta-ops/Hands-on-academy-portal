<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\GradeBand;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\Request;

/**
 * Everything the frontend needs to populate its dropdowns, in one request.
 * Four separate lookup endpoints would mean four round trips before the
 * first screen can render.
 */
class SetupController
{
    public function lookups()
    {
        return response()->json([
            'academic_years' => AcademicYear::orderByDesc('start_date')
                ->get(['id', 'name', 'is_current']),
            'terms' => Term::with('academicYear:id,name')->orderBy('academic_year_id')
                ->orderBy('sequence')->get()
                ->map(fn ($t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'label'      => $t->label(),
                    'is_current' => $t->is_current,
                ]),
            'grade_levels' => GradeLevel::orderBy('sequence')->get(['id', 'name', 'sequence']),
            'class_rooms'  => ClassRoom::with('gradeLevel:id,name')
                ->whereHas('academicYear', fn ($q) => $q->where('is_current', true))
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                ->sortBy('name')->values(),
            'subjects'    => Subject::orderBy('name')->get(['id', 'name', 'code']),
            'grade_bands' => GradeBand::orderByDesc('min_percent')
                ->get(['id', 'letter', 'min_percent', 'max_percent', 'remark']),
        ]);
    }

    public function storeAcademicYear(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:20', 'unique:academic_years,name'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'terms'      => ['required', 'array', 'min:1'],
            'terms.*.name'       => ['required', 'string', 'max:30'],
            'terms.*.start_date' => ['required', 'date'],
            'terms.*.end_date'   => ['required', 'date'],
        ]);

        $year = \DB::transaction(function () use ($data) {
            $year = AcademicYear::create([
                'name'       => $data['name'],
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'],
            ]);

            foreach (array_values($data['terms']) as $i => $term) {
                $year->terms()->create([
                    'name'       => $term['name'],
                    'sequence'   => $i + 1,
                    'start_date' => $term['start_date'],
                    'end_date'   => $term['end_date'],
                ]);
            }

            return $year;
        });

        return response()->json(['message' => $year->name . ' created.', 'id' => $year->id], 201);
    }

    public function setCurrentTerm(Request $request)
    {
        $data = $request->validate(['term_id' => ['required', 'exists:terms,id']]);

        $term = Term::with('academicYear')->findOrFail($data['term_id']);
        $term->academicYear->makeCurrent();
        $term->makeCurrent();

        return response()->json(['message' => 'Current term is now ' . $term->label() . '.']);
    }

    public function storeClassRoom(Request $request)
    {
        $data = $request->validate([
            'grade_level_id'   => ['required', 'exists:grade_levels,id'],
            'stream'           => ['nullable', 'string', 'max:20'],
            'class_teacher_id' => ['nullable', 'exists:users,id'],
            'capacity'         => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $year = AcademicYear::current();

        if (! $year) {
            return response()->json(['message' => 'Set the current academic year first.'], 422);
        }

        $classRoom = ClassRoom::create($data + ['academic_year_id' => $year->id]);

        return response()->json(['message' => $classRoom->name . ' created.', 'id' => $classRoom->id], 201);
    }

    public function storeSubject(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:80'],
            'code'            => ['required', 'string', 'max:20', 'unique:subjects,code'],
            'grade_level_ids' => ['array'],
            'grade_level_ids.*' => ['exists:grade_levels,id'],
        ]);

        $subject = Subject::create(['name' => $data['name'], 'code' => $data['code']]);
        $subject->gradeLevels()->sync($data['grade_level_ids'] ?? []);

        return response()->json(['message' => $subject->name . ' added.', 'id' => $subject->id], 201);
    }
}
