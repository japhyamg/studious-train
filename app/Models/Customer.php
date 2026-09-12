<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'bankId', 'first_name', 'middle_name', 'last_name',
        'account_number', 'date_of_birth', 'bvn', 'nin', 'gender',
        'isPep', 'customer_type', 'account_type', 'tier_level',
        'state_of_residence', 'local_govt_area', 'date_onboarded',
        'occupation', 'source_of_funds', 'income_range',
        'business_activity', 'employer_name', 'phone_number', 'email', 'address',
        'current_risk_level', 'current_risk_score',
        'next_review_date', 'last_reviewed_at', 'review_status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_onboarded' => 'date',
        'next_review_date' => 'date',
        'last_reviewed_at' => 'date',
    ];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    /**
     * Get all transactions where this customer is sender OR beneficiary
     */
    public function transactions()
    {
        return Transaction::where('sender_account_no', $this->account_number)
            ->orWhere('beneficiary_account_no', $this->account_number);
    }

    /**
     * Get all flagged cases related to this customer via their transactions
     * This is the proper way — through the transaction, not account_no on flagged_cases
     */
    public function flaggedCases()
    {
        $txnIds = Transaction::where('sender_account_no', $this->account_number)
            ->orWhere('beneficiary_account_no', $this->account_number)
            ->pluck('id');

        return FlaggedCase::whereIn('transaction_id', $txnIds);
    }

    /**
     * Count STR reports involving this customer
     */
    public function strCount(): int
    {
        return $this->flaggedCases()->where('report_type', 'STR')->count();
    }

    /**
     * Count CTR reports involving this customer
     */
    public function ctrCount(): int
    {
        return $this->flaggedCases()->where('report_type', 'CTR')->count();
    }

    /**
     * Get transaction stats (credit/debit volume and value)
     */
    public function transactionStats(): array
    {
        $acct = $this->account_number;

        $creditQuery = Transaction::where('beneficiary_account_no', $acct);
        $debitQuery = Transaction::where('sender_account_no', $acct);

        return [
            'total_count' => Transaction::where('sender_account_no', $acct)
                ->orWhere('beneficiary_account_no', $acct)->count(),
            'credit_count' => (clone $creditQuery)->count(),
            'credit_value' => (clone $creditQuery)->sum('amount'),
            'debit_count' => (clone $debitQuery)->count(),
            'debit_value' => (clone $debitQuery)->sum('amount'),
        ];
    }

    public function risk_rating()
    {
        return $this->hasMany(RiskRatingCustomerResult::class);
    }

    /**
     * Check if customer review is due or overdue
     */
    public function isReviewDue(): bool
    {
        if (!$this->next_review_date) return false;
        return Carbon::parse($this->next_review_date)->lte(now());
    }

    public function isReviewOverdue(): bool
    {
        if (!$this->next_review_date) return false;
        return Carbon::parse($this->next_review_date)->lt(now());
    }

    public function getDaysUntilReviewAttribute(): ?int
    {
        if (!$this->next_review_date) return null;
        return (int) now()->diffInDays($this->next_review_date, false);
    }

    /**
     * Set review schedule based on risk level
     *
     * @param bool $fromNow When true, schedule from now (event-driven review);
     *                      otherwise from onboarding / last review (periodic).
     */
    public function scheduleNextReview(bool $fromNow = false): void
    {
        if (!$this->current_risk_level) return;

        $riskLevel = RiskLevel::where('label', $this->current_risk_level)->first();
        if (!$riskLevel || !$riskLevel->review_schedule_days) return;

        $fromDate = $fromNow
            ? now()
            : ($this->last_reviewed_at ?? $this->date_onboarded ?? $this->created_at);

        $this->update([
            'next_review_date' => $riskLevel->calculateNextReviewDate($fromDate),
            'review_status' => 'pending',
        ]);
    }

    /**
     * Apply a risk level and record a change in the append-only history when
     * the classification changes (CBN 5.4(a)(v) + 5.2(a)(ii)).
     *
     * When the level changes, an event-driven CDD/EDD review is scheduled from
     * now, in addition to the periodic review cadence.
     */
    public function applyRiskLevel(?string $level, float $score, string $driver = 'risk_rating'): void
    {
        $previousLevel = $this->current_risk_level;

        $this->update([
            'current_risk_level' => $level,
            'current_risk_score' => $score,
        ]);

        if ($previousLevel !== $level) {
            RiskLevelChange::create([
                'customer_id' => $this->id,
                'from_level' => $previousLevel,
                'to_level' => $level,
                'score' => $score,
                'driver' => $driver,
            ]);

            activity()->performedOn($this)->withProperties([
                'from_level' => $previousLevel,
                'to_level' => $level,
                'score' => $score,
                'driver' => $driver,
            ])->log("Risk level changed from {$previousLevel} to {$level}");

            // Event-driven review: a risk-category change reschedules the
            // customer's CDD/EDD review from the date of the change.
            $this->scheduleNextReview(fromNow: true);
        }
    }

    /**
     * Mark review as completed and schedule next
     */
    public function markReviewed(): void
    {
        $this->update([
            'last_reviewed_at' => now(),
            'review_status' => 'completed',
        ]);
        $this->scheduleNextReview();
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'DESC');
    }

    public function scopeReviewsDue(Builder $query): Builder
    {
        return $query->whereNotNull('next_review_date')
            ->where('next_review_date', '<=', now())
            ->where('review_status', '!=', 'completed');
    }

    public function scopeReviewsUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->whereNotNull('next_review_date')
            ->whereBetween('next_review_date', [now(), now()->addDays($days)])
            ->where('review_status', '!=', 'completed');
    }
}
