<?php

namespace App\Http\Controllers;

use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Term;
use App\Services\InvoiceGenerator;
use Illuminate\Http\Request;

class FeeController
{
    /** The price list for a term, grouped for the settings screen. */
    public function structure(Request $request)
    {
        $termId = $request->query('term_id') ?: Term::current()?->id;

        $structures = FeeStructure::with(['category', 'gradeLevel'])
            ->where('term_id', $termId)
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'category'    => ['id' => $s->fee_category_id, 'name' => $s->category->name],
                'grade_level' => ['id' => $s->grade_level_id, 'name' => $s->gradeLevel->name],
                'amount'      => (float) $s->amount,
            ]);

        return response()->json([
            'term_id'    => $termId,
            'categories' => FeeCategory::orderBy('name')->get(['id', 'name', 'is_optional']),
            'structures' => $structures,
        ]);
    }

    public function storeStructure(Request $request)
    {
        $data = $request->validate([
            'fee_category_id' => ['required', 'exists:fee_categories,id'],
            'grade_level_id'  => ['required', 'exists:grade_levels,id'],
            'term_id'         => ['required', 'exists:terms,id'],
            'amount'          => ['required', 'numeric', 'min:0'],
        ]);

        // updateOrCreate, because the unique index means a duplicate is an
        // amount change, not an error to shout about.
        $structure = FeeStructure::updateOrCreate(
            [
                'fee_category_id' => $data['fee_category_id'],
                'grade_level_id'  => $data['grade_level_id'],
                'term_id'         => $data['term_id'],
            ],
            ['amount' => $data['amount']]
        );

        return response()->json([
            'message' => 'Fee saved.',
            'id'      => $structure->id,
        ]);
    }

    public function destroyStructure(FeeStructure $feeStructure)
    {
        $feeStructure->delete();

        return response()->json([
            'message' => 'Fee removed from the price list. Invoices already issued are unchanged.',
        ]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:80', 'unique:fee_categories,name'],
            'is_recurring' => ['boolean'],
            'is_optional'  => ['boolean'],
        ]);

        $category = FeeCategory::create($data);

        return response()->json(['message' => $category->name . ' added.', 'id' => $category->id], 201);
    }

    /** The arrears ledger: one row per student for a term. */
    public function ledger(Request $request)
    {
        $termId = $request->query('term_id') ?: Term::current()?->id;

        if (! $termId) {
            return response()->json(['message' => 'No current term is set.'], 422);
        }

        $rows = Student::active()
            ->when($request->query('class_room_id'), fn ($q, $id) => $q->inClassRoom($id))
            ->with(['enrollments.classRoom.gradeLevel'])
            ->orderBy('last_name')
            ->get()
            ->map(function (Student $s) use ($termId) {
                $invoice = Invoice::with(['lines', 'allocations'])
                    ->where('student_id', $s->id)
                    ->where('term_id', $termId)
                    ->first();

                $enrollment = $s->currentEnrollment();

                return [
                    'student_id'       => $s->id,
                    'name'             => $s->full_name,
                    'admission_number' => $s->admission_number,
                    'class'            => $enrollment?->classRoom->name,
                    'invoice_id'       => $invoice?->id,
                    'invoice_number'   => $invoice?->number,
                    'billed'           => $invoice ? $invoice->total : 0.0,
                    'paid'             => $invoice ? $invoice->paid : 0.0,
                    'balance'          => $invoice ? $invoice->balance : 0.0,
                    'total_outstanding' => $s->outstandingBalance(),
                ];
            })
            ->sortByDesc('balance')
            ->values();

        return response()->json([
            'term_id' => $termId,
            'rows'    => $rows,
            'totals'  => [
                'billed'      => round($rows->sum('billed'), 2),
                'collected'   => round($rows->sum('paid'), 2),
                'outstanding' => round($rows->where('balance', '>', 0)->sum('balance'), 2),
                'debtors'     => $rows->where('balance', '>', 0)->count(),
            ],
        ]);
    }

    /** Generate invoices for a class or the whole school. */
    public function generateInvoices(Request $request, InvoiceGenerator $generator)
    {
        $data = $request->validate([
            'term_id'       => ['required', 'exists:terms,id'],
            'class_room_id' => ['nullable', 'exists:class_rooms,id'],
        ]);

        $term = Term::findOrFail($data['term_id']);
        $report = $generator->forClassRoom($data['class_room_id'] ?? null, $term);

        return response()->json([
            'message' => $report['created'] . ' invoice(s) created, '
                . $report['existing'] . ' already existed, '
                . count($report['failed']) . ' could not be billed.',
            'report'  => $report,
        ]);
    }

    public function invoice(Invoice $invoice)
    {
        $invoice->load(['lines', 'student', 'term.academicYear', 'allocations.payment']);

        return response()->json([
            'id'       => $invoice->id,
            'number'   => $invoice->number,
            'status'   => $invoice->status,
            'student'  => [
                'id'               => $invoice->student->id,
                'name'             => $invoice->student->full_name,
                'admission_number' => $invoice->student->admission_number,
            ],
            'term'     => $invoice->term->label(),
            'issued'   => $invoice->issue_date->toDateString(),
            'due'      => $invoice->due_date->toDateString(),
            'lines'    => $invoice->lines->map(fn ($l) => [
                'description' => $l->description,
                'amount'      => (float) $l->amount,
            ]),
            'total'    => $invoice->total,
            'paid'     => $invoice->paid,
            'balance'  => $invoice->balance,
        ]);
    }
}
