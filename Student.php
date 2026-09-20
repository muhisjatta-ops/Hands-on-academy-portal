<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'admission_number', 'user_id', 'first_name', 'middle_name', 'last_name',
        'date_of_birth', 'gender', 'photo_path', 'address',
        'admission_date', 'status', 'notes',
    ];

    protected $casts = [
        'date_of_birth'  => 'date',
        'admission_date' => 'date',
    ];

    protected $appends = ['full_name'];

    public const STATUSES = ['ACTIVE', 'WITHDRAWN', 'GRADUATED', 'SUSPENDED'];

    // ---------------- relationships ----------------

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class)->latest('date_enrolled');
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['is_primary', 'is_fee_payer'])
            ->withTimestamps();
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    public function attendance()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }

    // ---------------- derived ----------------

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name,
        ])));
    }

    public function currentEnrollment(): ?Enrollment
    {
        return $this->enrollments()
            ->whereHas('academicYear', fn ($q) => $q->where('is_current', true))
            ->with('classRoom.gradeLevel')
            ->first();
    }

    /**
     * Total owed across every issued invoice, minus every payment.
     * Computed, never stored — see the fee migration for why.
     */
    public function outstandingBalance(): float
    {
        $charged = (float) $this->invoices()
            ->where('status', 'ISSUED')
            ->withSum('lines', 'amount')
            ->get()
            ->sum('lines_sum_amount');

        $paid = (float) $this->payments()->sum('amount');

        return round($charged - $paid, 2);
    }

    // ---------------- scopes ----------------

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $like = '%' . str_replace('%', '', $term) . '%';

        return $query->where(function ($q) use ($like) {
            $q->where('first_name', 'ilike', $like)
              ->orWhere('last_name', 'ilike', $like)
              ->orWhere('admission_number', 'ilike', $like);
        });
        // `ilike` is Postgres-specific case-insensitive matching. On MySQL
        // use `like` — the default collation is already case-insensitive.
    }

    public function scopeInClassRoom($query, $classRoomId)
    {
        return $query->whereHas('enrollments', fn ($q) => $q->where('class_room_id', $classRoomId));
    }

    /**
     * Admission numbers are generated, never typed: HOA-2026-0001.
     * Wrapped in a lock so two clerks admitting at once cannot collide.
     */
    public static function nextAdmissionNumber(string $prefix = 'HOA'): string
    {
        $year = AcademicYear::current()?->start_date->format('Y') ?? date('Y');
        $stem = $prefix . '-' . $year . '-';

        $last = static::withTrashed()
            ->where('admission_number', 'like', $stem . '%')
            ->orderByDesc('admission_number')
            ->lockForUpdate()
            ->value('admission_number');

        $next = $last ? ((int) substr($last, strlen($stem))) + 1 : 1;

        return $stem . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
