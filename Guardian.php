<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $fillable = [
        'user_id', 'full_name', 'relationship', 'phone',
        'email', 'occupation', 'address',
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class)
            ->withPivot(['is_primary', 'is_fee_payer'])
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
