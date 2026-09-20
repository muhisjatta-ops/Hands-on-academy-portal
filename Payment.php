<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only. There is no update() or delete() path for a payment in this
 * system: a mistake is corrected with a reversing negative row, so the
 * ledger always explains itself.
 */
class Payment extends Model
{
    use Auditable;

    public const METHODS = ['CASH', 'BANK', 'MOBILE', 'CHEQUE'];

    protected $fillable = [
        'receipt_number', 'student_id', 'amount', 'method', 'reference',
        'paid_on', 'received_by', 'note',
        'reverses_payment_id', 'reversal_reason',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_on' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function reverses()
    {
        return $this->belongsTo(Payment::class, 'reverses_payment_id');
    }

    public function isReversal(): bool
    {
        return ! is_null($this->reverses_payment_id);
    }

    public static function nextReceiptNumber(): string
    {
        $stem = 'RCP-' . date('Y') . '-';

        $last = static::where('receipt_number', 'like', $stem . '%')
            ->orderByDesc('receipt_number')
            ->lockForUpdate()
            ->value('receipt_number');

        $next = $last ? ((int) substr($last, strlen($stem))) + 1 : 1;

        return $stem . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
