<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * The price list. Audited, because "who raised Grade 6 tuition by 2,000
 * and when" is a question that will eventually be asked.
 */
class FeeStructure extends Model
{
    use Auditable;

    protected $fillable = ['fee_category_id', 'grade_level_id', 'term_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function category()
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }
}
