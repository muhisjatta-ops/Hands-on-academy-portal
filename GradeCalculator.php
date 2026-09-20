<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\ClassRoom;
use App\Models\GradeBand;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

/**
 * All grade arithmetic lives here, on read. Nothing computed is ever stored.
 *
 * That is deliberate. The school will change its weighting or its grade
 * bands at some point — probably mid-year, probably after marks are in. If
 * totals and letters were columns, that change would mean a migration and a
 * backfill, and any row the backfill missed would be silently wrong forever.
 */
class GradeCalculator
{
    /**
     * One subject, one class, one term: every student's component marks,
     * weighted total, letter grade and position.
     */
    public function subjectSheet(ClassRoom $classRoom, Subject $subject, Term $term): array
    {
        $assessments = Assessment::where('class_room_id', $classRoom->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->orderBy('id')
            ->get();

        $students = $classRoom->students()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $scores = Score::whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy(fn ($s) => $s->assessment_id . ':' . $s->student_id);

        $bands = GradeBand::orderByDesc('min_percent')->get();
        $weightTotal = (float) $assessments->sum('weight');

        $rows = $students->map(function (Student $student) use ($assessments, $scores, $bands, $weightTotal) {
            $components = [];
            $weighted = 0.0;
            $weightSeen = 0.0;

            foreach ($assessments as $assessment) {
                $score = $scores->get($assessment->id . ':' . $student->id);
                $raw = $score?->score === null ? null : (float) $score->score;

                $components[] = [
                    'assessment_id' => $assessment->id,
                    'name'          => $assessment->name,
                    'max_score'     => (float) $assessment->max_score,
                    'weight'        => (float) $assessment->weight,
                    'score'         => $raw,
                ];

                if ($raw !== null && (float) $assessment->max_score > 0) {
                    $percent = ($raw / (float) $assessment->max_score) * 100;
                    $weighted += $percent * ((float) $assessment->weight / 100);
                    $weightSeen += (float) $assessment->weight;
                }
            }

            // Only count what has actually been marked. A student with just
            // the CA entered should show their CA standing, not a total
            // dragged to near-zero by two assessments that haven't happened.
            $total = $weightSeen > 0
                ? round($weighted * ($weightTotal / $weightSeen), 2)
                : null;

            $band = $total === null
                ? null
                : $bands->first(fn ($b) => $total >= (float) $b->min_percent
                    && $total <= (float) $b->max_percent);

            return [
                'student_id'       => $student->id,
                'name'             => $student->full_name,
                'admission_number' => $student->admission_number,
                'components'       => $components,
                'total'            => $total,
                'complete'         => $weightSeen >= $weightTotal && $weightTotal > 0,
                'grade'            => $band?->letter,
                'remark'           => $band?->remark,
            ];
        })->values()->all();

        // Position: ties share a place, and the next place skips — the
        // convention every report card in the world uses.
        $ranked = collect($rows)->whereNotNull('total')->sortByDesc('total')->values();
        $places = [];
        $place = 0;
        $seen = 0;
        $previous = null;

        foreach ($ranked as $row) {
            $seen++;
            if ($row['total'] !== $previous) {
                $place = $seen;
                $previous = $row['total'];
            }
            $places[$row['student_id']] = $place;
        }

        foreach ($rows as &$row) {
            $row['position'] = $places[$row['student_id']] ?? null;
        }

        return [
            'class_room'  => ['id' => $classRoom->id, 'name' => $classRoom->name],
            'subject'     => ['id' => $subject->id, 'name' => $subject->name],
            'term'        => ['id' => $term->id, 'label' => $term->label()],
            'assessments' => $assessments->map(fn ($a) => [
                'id'        => $a->id,
                'name'      => $a->name,
                'max_score' => (float) $a->max_score,
                'weight'    => (float) $a->weight,
            ]),
            'weight_total' => $weightTotal,
            'rows'         => $rows,
        ];
    }

    /**
     * One student's full term result across every subject — the data a
     * report card is printed from.
     */
    public function reportCard(Student $student, Term $term): array
    {
        $enrollment = $student->enrollments()
            ->where('academic_year_id', $term->academic_year_id)
            ->with('classRoom.gradeLevel')
            ->first();

        if (! $enrollment) {
            throw new \RuntimeException(
                $student->full_name . ' was not enrolled in ' . $term->academicYear->name . '.'
            );
        }

        $classRoom = $enrollment->classRoom;
        $subjects = $classRoom->gradeLevel->subjects()->orderBy('name')->get();

        $subjectRows = [];
        $totals = [];

        foreach ($subjects as $subject) {
            $sheet = $this->subjectSheet($classRoom, $subject, $term);
            $mine = collect($sheet['rows'])->firstWhere('student_id', $student->id);

            if (! $mine || $mine['total'] === null) {
                continue;
            }

            $subjectRows[] = [
                'subject'    => $subject->name,
                'total'      => $mine['total'],
                'grade'      => $mine['grade'],
                'remark'     => $mine['remark'],
                'position'   => $mine['position'],
                'class_size' => count($sheet['rows']),
            ];

            $totals[] = $mine['total'];
        }

        $average = count($totals) ? round(array_sum($totals) / count($totals), 2) : null;
        $band = GradeBand::forPercent($average);

        return [
            'student' => [
                'id'               => $student->id,
                'name'             => $student->full_name,
                'admission_number' => $student->admission_number,
                'class'            => $classRoom->name,
            ],
            'term'     => $term->label(),
            'subjects' => $subjectRows,
            'summary'  => [
                'subjects_taken' => count($subjectRows),
                'average'        => $average,
                'grade'          => $band?->letter,
                'remark'         => $band?->remark,
                'attendance'     => \App\Models\AttendanceRecord::rateFor($student->id, $term->id),
            ],
        ];
    }
}
