<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NibssWatchList extends Model
{
    protected $fillable = [
        'requesting_bank', 'bvn', 'first_name', 'middle_name',
        'last_name', 'category', 'reason', 'watchlisted_date', 'status',
    ];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }
}
