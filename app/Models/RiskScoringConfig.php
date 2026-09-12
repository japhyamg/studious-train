<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskScoringConfig extends Model
{
    protected $fillable = [
        'factor_name', 'factor_description', 'weight', 'is_active', 'conditions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conditions' => 'array',
    ];
}
