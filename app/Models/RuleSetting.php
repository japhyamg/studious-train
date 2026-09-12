<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleSetting extends Model
{
    protected $fillable = [
        'transaction_rule_id', 'search_attributes', 'status',
    ];

    protected $casts = [
        'search_attributes' => 'array',
        'status' => 'boolean',
    ];

    public function transaction_rule()
    {
        return $this->belongsTo(TransactionRule::class);
    }
}
