<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScreeningResult extends Model
{
    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'fullname', 'slug',
        'customer_id', 'search_context',
        'pep_status', 'pep_matches', 'pep_meta',
        'sanction_status', 'sanction_matches',
        'adverse_media_status', 'adverse_media_articles',
        'risk_level', 'pep_detected', 'sanctions_detected', 'adverse_media_detected',
        'screened_at', 'screened_by',
    ];

    protected $casts = [
        'search_context' => 'array',
        'pep_matches' => 'array',
        'pep_meta' => 'array',
        'sanction_matches' => 'array',
        'adverse_media_articles' => 'array',
        'screened_at' => 'datetime',
        'pep_detected' => 'boolean',
        'sanctions_detected' => 'boolean',
        'adverse_media_detected' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
