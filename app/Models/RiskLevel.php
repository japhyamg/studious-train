<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskLevel extends Model
{
    protected $fillable = [
        'label', 'min_score', 'max_score',
        'review_schedule_days', 'diligence_type',
        'case_tat_hours',
    ];

    /**
     * Get the diligence type (CDD for Low, EDD for Medium/High)
     */
    public function getDiligenceLabel(): string
    {
        return $this->diligence_type ?? (strtolower($this->label) === 'low' ? 'CDD' : 'EDD');
    }

    /**
     * Calculate next review date from a given start date
     */
    public function calculateNextReviewDate($fromDate = null): ?\Carbon\Carbon
    {
        if (!$this->review_schedule_days) return null;

        $from = $fromDate ? \Carbon\Carbon::parse($fromDate) : now();
        return $from->addDays($this->review_schedule_days);
    }
}
