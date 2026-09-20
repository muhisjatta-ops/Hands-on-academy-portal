<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeCategory extends Model
{
    protected $fillable = ['name', 'is_recurring', 'is_optional'];

    protected $casts = [
        'is_recurring' => 'boolean',
        'is_optional'  => 'boolean',
    ];

    public function structures()
    {
        return $this->hasMany(FeeStructure::class);
    }
}
