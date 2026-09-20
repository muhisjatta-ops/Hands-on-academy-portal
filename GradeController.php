<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\ClassRoom;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\GradeCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GradeController
{
    /** The mark sheet for one class, subject and term. */
    public function sheet(Request $request, GradeCalculator $calculator)
    {
        $data = $request->validate([
            'class_room_id' => ['required', 'exists:class_rooms,id'],
            'subject_id'    => ['required', 'exists:subjects,id'],
            'term_id'       => ['nullable', 'exists:terms,id'],
        ]);

        $classRoom = ClassRoom::with('gradeLevel')->findOrFail($data['class_room_id']);
        $subject = Subject::findOrFail($data['subject_id']);
        $term = $data['term_id'] ? Term::findOrFail($data['term_id']) : Term::current();

        if (! $term) {
            return response()->json(['message' => 'No current term is set.'], 422);
        }

        // Row-level check: teachers see only what they teach.
        $this->authorizeSheet($classRoom, $subject);

        return response()->json($calculator->subjectSheet($classRoom, $subject, $term));
    }

    /**
     * Create the assessment components for a class/subject/term. Weights
     * must total 100 — refusing anything else here is much kinder than
     * discovering a 90% total on a printed report card.
     */
    public function storeAssessments(Request $request)
    {
        $data = $request->validate([
            'class_room_id'         => ['required', 'exists:class_rooms,id'],
            'subject_id'            => ['required', 'exists:subjects,id'],
            'term_id'               => ['required', 'exists:terms,id'],
            'components'            => ['required', 'array', 'min:1'],
            'components.*.name'     => ['required', 'string', 'max:80'],
            'components.*.weight'   => ['required', 'numeric', 'min:0', 'max:100'],
            'components.*.max_score' => ['required', 'numeric', 'min:1'],
        ]);

        $total = collect($data['components'])->sum('weight');

        if (abs($total - 100) > 0.01) {
            return response()->json([
                'message' => 'Component weights must add up to 100%. They currently total ' . $total . '%.',
            ], 422);
        }

        $classRoom = ClassRoom::findOrFail($data['class_room_id']);
        $subject = Subject::findOrFail($data['subject_id']);
        $this->authorizeSheet($classRoom, $subject);

        DB::transaction(function () use ($data) {
            foreach ($data['components'] as $component) {
                Assessment::updateOrCreate(
                    [
                        'class_room_id' => $data['class_room_id'],
                        'subject_id'    => $data['subject_id'],
                        'term_id'       => $data['term_id'],
                        'name'          => $component['name'],
                    ],
                    [
                        'weight'     => $component['weight'],
                        'max_score'  => $component['max_score'],
                        'created_by' => Auth::id(),
                    ]
                );
            }
        });

        return response()->json(['message' => 'Assessment components saved.']);
    }

    /**
     * Bulk mark entry: the whole class in one request.
     *
     * One POST per row would mean 40 requests for one class, 40 chances to
     * fail halfway, and a teacher who cannot tell what saved. One
     * transaction either takes the whole sheet or none of it.
     */
    public function storeScores(Request $request)
    {
        $data = $request->validate([
            'assessment_id'      => ['required', 'exists:assessments,id'],
            'scores'             => ['required', 'array'],
            'scores.*.student_id' => ['required', 'exists:students,id'],
            'scores.*.score'     => ['nullable', 'numeric', 'min:0'],
            'scores.*.remark'    => ['nullable', 'string', 'max:200'],
        ]);

        $assessment = Assessment::with(['classRoom', 'subject'])->findOrFail($data['assessment_id']);
        $this->authorizeSheet($assessment->classRoom, $assessment->subject);

        $max = (float) $assessment->max_score;

        foreach ($data['scores'] as $row) {
            if ($row['score'] !== null && (float) $row['score'] > $max) {
                return response()->json([
                    'message' => 'A mark of ' . $row['score'] . ' exceeds the maximum of ' . $max . '.',
                ], 422);
            }
        }

        DB::transaction(function () use ($data, $assessment) {
            foreach ($data['scores'] as $row) {
                Score::updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'student_id'    => $row['student_id'],
                    ],
                    [
                        'score'      => $row['score'],
                        'remark'     => $row['remark'] ?? null,
                        'entered_by' => Auth::id(),
                    ]
                );
            }
        });

        return response()->json([
            'message' => count($data['scores']) . ' mark(s) saved.',
        ]);
    }

    public function reportCard(Request $request, Student $student, GradeCalculator $calculator)
    {
        $term = $request->query('term_id')
            ? Term::findOrFail($request->query('term_id'))
            : Term::current();

        if (! $term) {
            return response()->json(['message' => 'No current term is set.'], 422);
        }

        try {
            return response()->json($calculator->reportCard($student, $term));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * A teacher may only touch a class/subject pair they are assigned.
     * Anyone with scores.publish (head teacher, admin) sees everything.
     */
    private function authorizeSheet(ClassRoom $classRoom, Subject $subject): void
    {
        $user = Auth::user();

        if ($user->can('scores.publish')) {
            return;
        }

        $teaches = \App\Models\TeachingAssignment::teaches($user->id, $classRoom->id, $subject->id);

        abort_unless($teaches, 403, 'You are not assigned to teach ' . $subject->name . ' in ' . $classRoom->name . '.');
    }
}
