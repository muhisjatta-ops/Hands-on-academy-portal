<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Student;
use App\Services\PaymentRecorder;
use Illuminate\Http\Request;

class PaymentController
{
    public function index(Request $request)
    {
        $payments = Payment::with(['student', 'receivedBy'])
            ->when($request->query('student_id'), fn ($q, $id) => $q->where('student_id', $id))
            ->when($request->query('from'), fn ($q, $d) => $q->where('paid_on', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('paid_on', '<=', $d))
            ->orderByDesc('paid_on')->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json([
            'data' => collect($payments->items())->map(fn (Payment $p) => [
                'id'         => $p->id,
                'receipt'    => $p->receipt_number,
                'student'    => $p->student?->full_name,
                'student_id' => $p->student_id,
                'amount'     => (float) $p->amount,
                'method'     => $p->method,
                'reference'  => $p->reference,
                'paid_on'    => $p->paid_on->toDateString(),
                'received_by' => $p->receivedBy?->name,
                'reversal'   => $p->isReversal(),
            ]),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
                'total'        => $payments->total(),
            ],
        ]);
    }

    public function store(Request $request, PaymentRecorder $recorder)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'method'     => ['required', 'in:' . implode(',', Payment::METHODS)],
            'reference'  => ['nullable', 'string', 'max:80'],
            'paid_on'    => ['required', 'date', 'before_or_equal:today'],
            'note'       => ['nullable', 'string', 'max:200'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $payment = $recorder->record($student, $data);

        return response()->json([
            'message' => 'Receipt ' . $payment->receipt_number . ' issued for '
                . number_format((float) $payment->amount, 2),
            'receipt' => [
                'id'          => $payment->id,
                'number'      => $payment->receipt_number,
                'student'     => $student->full_name,
                'admission_number' => $student->admission_number,
                'amount'      => (float) $payment->amount,
                'method'      => $payment->method,
                'paid_on'     => $payment->paid_on->toDateString(),
                'allocations' => $payment->allocations->map(fn ($a) => [
                    'invoice' => $a->invoice->number,
                    'amount'  => (float) $a->amount,
                ]),
                'unallocated' => round(
                    (float) $payment->amount - (float) $payment->allocations->sum('amount'), 2
                ),
                'balance_now' => $student->fresh()->outstandingBalance(),
            ],
        ], 201);
    }

    /**
     * Reversal, not deletion. Requires the void permission, which the seeder
     * grants only to the bursar and super-admin.
     */
    public function reverse(Request $request, Payment $payment, PaymentRecorder $recorder)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:200'],
        ]);

        try {
            $reversal = $recorder->reverse($payment, $data['reason']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment reversed on receipt ' . $reversal->receipt_number . '.',
        ]);
    }

    public function statement(Student $student, PaymentRecorder $recorder)
    {
        return response()->json($recorder->statement($student));
    }
}
