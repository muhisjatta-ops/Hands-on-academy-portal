<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns the price list into actual charges.
 *
 * The single rule that matters: an invoice line COPIES the description and
 * amount from the fee structure at the moment of billing. It does not point
 * at the price list and read it later. Raising tuition in March must not
 * silently rewrite what a parent was told in January — and if it can, you
 * will eventually be unable to explain a receipt to an angry guardian.
 */
class InvoiceGenerator
{
    /**
     * Bill one student for one term. Idempotent: calling it twice returns
     * the existing invoice rather than double-charging, because the unique
     * index on (student_id, term_id) makes a second invoice impossible
     * anyway — better to return it cleanly than to catch a database error.
     */
    public function forStudent(Student $student, Term $term): Invoice
    {
        return DB::transaction(function () use ($student, $term) {
            $existing = Invoice::where('student_id', $student->id)
                ->where('term_id', $term->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $enrollment = $student->currentEnrollment();

            if (! $enrollment) {
                throw new \RuntimeException(
                    $student->full_name . ' is not enrolled in a class for the current year.'
                );
            }

            $gradeLevelId = $enrollment->classRoom->grade_level_id;

            $structures = FeeStructure::with('category')
                ->where('grade_level_id', $gradeLevelId)
                ->where('term_id', $term->id)
                ->get();

            if ($structures->isEmpty()) {
                throw new \RuntimeException(
                    'No fees have been set for ' . $enrollment->classRoom->gradeLevel->name
                    . ' in ' . $term->label() . '.'
                );
            }

            $invoice = Invoice::create([
                'number'     => Invoice::nextNumber(),
                'student_id' => $student->id,
                'term_id'    => $term->id,
                'issue_date' => $term->start_date,
                'due_date'   => $term->start_date->copy()->addDays(30),
                'status'     => 'ISSUED',
                'created_by' => Auth::id(),
            ]);

            $discounts = Discount::where('student_id', $student->id)
                ->activeOn($term->start_date)
                ->get();

            foreach ($structures as $structure) {
                $charge = (float) $structure->amount;

                $invoice->lines()->create([
                    'description'      => $structure->category->name,
                    'amount'           => $charge,
                    'fee_structure_id' => $structure->id,
                ]);

                // Discounts are their own negative lines, not a reduced
                // charge — so a receipt shows the full fee and the relief
                // separately, which is what parents and auditors both want.
                foreach ($discounts as $discount) {
                    $applies = is_null($discount->fee_category_id)
                        || $discount->fee_category_id === $structure->fee_category_id;

                    if (! $applies) {
                        continue;
                    }

                    $off = $discount->amountOff($charge);

                    if ($off > 0) {
                        $invoice->lines()->create([
                            'description' => $discount->reason . ' — ' . $structure->category->name,
                            'amount'      => -$off,
                        ]);
                    }
                }
            }

            return $invoice->load('lines');
        });
    }

    /**
     * Bill a whole class or the whole school. Returns a per-student report
     * rather than throwing on the first problem, because one unenrolled
     * student should not abort billing for the other 1,200.
     */
    public function forClassRoom(?int $classRoomId, Term $term): array
    {
        $query = Student::active();

        if ($classRoomId) {
            $query->inClassRoom($classRoomId);
        }

        $report = ['created' => 0, 'existing' => 0, 'failed' => []];

        foreach ($query->cursor() as $student) {
            try {
                $before = Invoice::where('student_id', $student->id)
                    ->where('term_id', $term->id)
                    ->exists();

                $this->forStudent($student, $term);

                $before ? $report['existing']++ : $report['created']++;
            } catch (\Throwable $e) {
                $report['failed'][] = [
                    'student' => $student->full_name,
                    'reason'  => $e->getMessage(),
                ];
            }
        }

        return $report;
    }
}
