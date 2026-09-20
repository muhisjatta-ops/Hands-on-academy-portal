<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    public function __invoke()
    {
        $term = Term::current();
        $year = AcademicYear::current();

        if (! $term) {
            return response()->json([
                'message' => 'No current term is set. Set one under Setup.',
                'ready'   => false,
            ]);
        }

        // One aggregate query each, rather than looping every student.
        $billed = (float) DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.term_id', $term->id)
            ->where('invoices.status', 'ISSUED')
            ->sum('invoice_lines.amount');

        $collected = (float) DB::table('payment_allocations')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where('invoices.term_id', $term->id)
            ->sum('payment_allocations.amount');

        $attendance = DB::table('attendance_records')
            ->where('term_id', $term->id)
            ->whereIn('state', ['P', 'A', 'L'])
            ->selectRaw("count(*) as marked, sum(case when state in ('P','L') then 1 else 0 end) as present")
            ->first();

        $debtors = Invoice::with(['student', 'lines', 'allocations'])
            ->where('term_id', $term->id)
            ->issued()
            ->get()
            ->filter(fn ($i) => $i->balance > 0)
            ->sortByDesc(fn ($i) => $i->balance)
            ->take(8)
            ->map(fn ($i) => [
                'student_id' => $i->student_id,
                'name'       => $i->student->full_name,
                'balance'    => $i->balance,
            ])
            ->values();

        return response()->json([
            'ready'   => true,
            'context' => [
                'academic_year' => $year?->name,
                'term'          => $term->name,
            ],
            'counts'  => [
                'students' => Student::active()->count(),
                'classes'  => ClassRoom::where('academic_year_id', $term->academic_year_id)->count(),
                'teachers' => DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'teacher')->count(),
            ],
            'fees'    => [
                'billed'      => round($billed, 2),
                'collected'   => round($collected, 2),
                'outstanding' => round($billed - $collected, 2),
                'rate'        => $billed > 0 ? round(($collected / $billed) * 100) : 0,
            ],
            'attendance' => [
                'marked' => (int) ($attendance->marked ?? 0),
                'rate'   => ($attendance->marked ?? 0) > 0
                    ? round(($attendance->present / $attendance->marked) * 100, 1)
                    : null,
            ],
            'top_debtors'     => $debtors,
            'recent_payments' => Payment::with('student')
                ->orderByDesc('id')->limit(6)->get()
                ->map(fn ($p) => [
                    'receipt' => $p->receipt_number,
                    'student' => $p->student?->full_name,
                    'amount'  => (float) $p->amount,
                    'paid_on' => $p->paid_on->toDateString(),
                ]),
        ]);
    }
}
