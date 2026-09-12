<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskProfile extends Model
{
    protected $fillable = ['name', 'data_points', 'template_uploaded', 'isDefault', 'status'];

    protected $casts = [
        'data_points' => 'array',
        'template_uploaded' => 'boolean',
        'isDefault' => 'boolean',
        'status' => 'boolean',
    ];

    public function template_data()
    {
        return $this->hasMany(RiskProfileTemplateData::class);
    }

    public function ratings()
    {
        return $this->hasMany(RiskRating::class);
    }
}
