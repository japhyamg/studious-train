<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalWatchList extends Model
{
    protected $fillable = [
        'first_name', 'middle_name', 'last_name',
        'account_no', 'bvn', 'nin',
    ];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }
}
