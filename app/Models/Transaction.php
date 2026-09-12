<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bankId', 'sender_first_name', 'sender_middle_name', 'sender_last_name',
        'sender_account_no', 'sender_account_type', 'sender_nin', 'sender_bvn',
        'beneficiary_first_name', 'beneficiary_middle_name', 'beneficiary_last_name',
        'beneficiary_account_no', 'beneficiary_account_type', 'beneficiary_nin', 'beneficiary_bvn',
        'amount', 'transaction_type', 'narration', 'channel', 'location',
        'transaction_ref', 'transaction_datetime',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'transaction_datetime' => 'datetime',
    ];

    public function getSenderNameAttribute(): string
    {
        return trim("{$this->sender_first_name} {$this->sender_middle_name} {$this->sender_last_name}");
    }

    public function getBeneficiaryNameAttribute(): string
    {
        return trim("{$this->beneficiary_first_name} {$this->beneficiary_middle_name} {$this->beneficiary_last_name}");
    }

    public function getRefAttribute(): string
    {
        return $this->transaction_ref ?? '---';
    }

    public function sender()
    {
        return $this->belongsTo(Customer::class, 'sender_account_no', 'account_number');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Customer::class, 'beneficiary_account_no', 'account_number');
    }

    public function risk_scores()
    {
        return $this->hasMany(TransactionRisk::class);
    }

    public function aiScores()
    {
        return $this->hasMany(AiScore::class);
    }

    public function flaggedCases()
    {
        return $this->hasMany(FlaggedCase::class);
    }

    public function scopeTransactions(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'DESC');
    }
}
