<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskRating extends Model
{
    protected $fillable = ['user_id', 'risk_profile_id'];

    public function generated_by()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function risk_profile()
    {
        return $this->belongsTo(RiskProfile::class);
    }

    public function results()
    {
        return $this->hasMany(RiskRatingCustomerResult::class);
    }
}
