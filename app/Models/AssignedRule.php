<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignedRule extends Model
{
    protected $table = 'assigned_rule';

    protected $fillable = [
        'transaction_rule_id', 'user_id',
    ];

    public function transaction_rule()
    {
        return $this->belongsTo(TransactionRule::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
