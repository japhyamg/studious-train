<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeerGroupThreshold extends Model
{
    protected $fillable = ['name_key', 'name', 'thresholds'];

    protected $casts = [
        'thresholds' => 'array',
    ];
}
