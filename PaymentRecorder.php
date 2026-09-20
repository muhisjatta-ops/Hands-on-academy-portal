<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentRecorder
{
    /**
     * Record money in and spread it across the student's unpaid invoices,
     * oldest first.
     *
     * Oldest-first matters: a parent paying 5,000 towards a 12,000 arrears
     * plus a 15,000 current term expects the old debt cleared first. Doing
     * it the other way round leaves an ageing balance that never clears and
     * makes the arrears report lie.
     *
     * Anything left over after every invoice is settled stays unallocated —
     * a credit on the student's account, visible because the payment total
     * exceeds the sum of its allocations. It is not silently discarded.
     */
    public function record(Student $student, array $data): Payment
    {
        return DB::transaction(function () use ($student, $data) {
            $payment = Payment::create([
                'receipt_number' => Payment::nextReceiptNumber(),
                'student_id'     => $student->id,
                'amount'         => $data['amount'],
                'method'         => $data['method'],
                'reference'      => $data['reference'] ?? null,
                'paid_on'        => $data['paid_on'],
                'received_by'    => Auth::id(),
                'note'           => $data['note'] ?? null,
            ]);

            $remaining = (float) $data['amount'];

            $invoices = Invoice::with(['lines', 'allocations'])
                ->where('student_id', $student->id)
                ->issued()
                ->orderBy('issue_date')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $owing = $invoice->balance;

                if ($owing <= 0) {
                    continue;
                }

                $apply = min($owing, $remaining);

                $payment->allocations()->create([
                    'invoice_id' => $invoice->id,
                    'amount'     => $apply,
                ]);

                $remaining = round($remaining - $apply, 2);
            }

            return $payment->load('allocations.invoice');
        });
    }

    /**
     * Reverse a payment with a compensating negative row.
     *
     * Deleting the original would leave a hole in the receipt sequence and
     * destroy the evidence of the mistake. A reversal keeps both facts:
     * money was recorded, then it was taken back, by whom and why.
     */
    public function reverse(Payment $original, string $reason): Payment
    {
        return DB::transaction(function () use ($original, $reason) {
            if ($original->isReversal()) {
                throw new \RuntimeException('A reversal cannot itself be reversed.');
            }

            $already = Payment::where('reverses_payment_id', $original->id)->exists();

            if ($already) {
                throw new \RuntimeException('This payment has already been reversed.');
            }

            $reversal = Payment::create([
                'receipt_number'      => Payment::nextReceiptNumber(),
                'student_id'          => $original->student_id,
                'amount'              => -1 * (float) $original->amount,
                'method'              => $original->method,
                'reference'           => $original->receipt_number,
                'paid_on'             => now()->toDateString(),
                'received_by'         => Auth::id(),
                'reverses_payment_id' => $original->id,
                'reversal_reason'     => $reason,
            ]);

            // Undo the original's allocations with matching negative ones so
            // each invoice's balance returns to what it was.
            foreach ($original->allocations as $allocation) {
                $reversal->allocations()->create([
                    'invoice_id' => $allocation->invoice_id,
                    'amount'     => -1 * (float) $allocation->amount,
                ]);
            }

            return $reversal;
        });
    }

    /** A student's statement: every charge and every payment, in order. */
    public function statement(Student $student): array
    {
        $invoices = Invoice::with(['lines', 'term.academicYear', 'allocations'])
            ->where('student_id', $student->id)
            ->orderBy('issue_date')
            ->get();

        $payments = $student->payments()->orderBy('paid_on')->get();

        return [
            'student' => [
                'id'               => $student->id,
                'name'             => $student->full_name,
                'admission_number' => $student->admission_number,
            ],
            'invoices' => $invoices->map(fn ($i) => [
                'id'      => $i->id,
                'number'  => $i->number,
                'term'    => $i->term->label(),
                'issued'  => $i->issue_date->toDateString(),
                'due'     => $i->due_date->toDateString(),
                'total'   => $i->total,
                'paid'    => $i->paid,
                'balance' => $i->balance,
                'lines'   => $i->lines->map(fn ($l) => [
                    'description' => $l->description,
                    'amount'      => (float) $l->amount,
                ]),
            ]),
            'payments' => $payments->map(fn ($p) => [
                'receipt'    => $p->receipt_number,
                'paid_on'    => $p->paid_on->toDateString(),
                'amount'     => (float) $p->amount,
                'method'     => $p->method,
                'reference'  => $p->reference,
                'reversal'   => $p->isReversal(),
            ]),
            'totals' => [
                'charged'     => round((float) $invoices->sum('total'), 2),
                'paid'        => round((float) $payments->sum('amount'), 2),
                'outstanding' => $student->outstandingBalance(),
            ],
        ];
    }
}
