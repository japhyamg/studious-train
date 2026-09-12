<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskProfileTemplateData extends Model
{
    protected $table = 'risk_profile_template_data';

    protected $fillable = ['risk_profile_id', 'data_point', 'type', 'value'];

    public function risk_profile()
    {
        return $this->belongsTo(RiskProfile::class);
    }
}
