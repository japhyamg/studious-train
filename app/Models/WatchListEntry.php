<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchListEntry extends Model
{
    protected $fillable = [
        'source', 'entity_type', 'first_name', 'middle_name', 'last_name',
        'full_name', 'aliases', 'country', 'date_of_birth', 'reference',
        'program', 'listed_on',
    ];

    protected $casts = [
        'aliases' => 'array',
        'listed_on' => 'date',
    ];
}
