<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'search_attributes',
        'type', 'run_time', 'report_type', 'status',
    ];

    protected $casts = [
        'search_attributes' => 'array',
        'status' => 'boolean',
    ];

    public function flagged_cases()
    {
        return $this->hasMany(FlaggedCase::class, 'transaction_rule_id');
    }

    public function rule_settings()
    {
        return $this->hasOne(RuleSetting::class);
    }

    public function assigned_users()
    {
        return $this->belongsToMany(User::class, 'assigned_rule');
    }

    /**
     * Scope to find rule by watchlist attribute name.
     */
    public function scopeWhereAttribute($query, string $attribute)
    {
        return $query->whereJsonContains('search_attributes', [['attribute' => $attribute]]);
    }
}
