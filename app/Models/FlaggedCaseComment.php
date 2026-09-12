<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlaggedCaseComment extends Model
{
    protected $fillable = [
        'flagged_case_id', 'comment', 'user_id',
    ];

    public function flagged_case()
    {
        return $this->belongsTo(FlaggedCase::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
