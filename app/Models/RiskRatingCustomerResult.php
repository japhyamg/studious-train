<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RiskRatingCustomerResult extends Model
{
    protected $fillable = ['id', 'risk_rating_id', 'customer_id', 'score'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function risk_rating()
    {
        return $this->belongsTo(RiskRating::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
