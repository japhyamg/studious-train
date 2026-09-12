<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\Customer;

class TransactionQueryBuilder
{
    private $query;
    private array $advanceChecks = [];

    public function __construct()
    {
        $this->query = DB::table('transactions');
    }

    /**
     * Apply runtime-specific filters (Instantly vs 24HrTask)
     */
    public function applyRuntimeFilters(string $runtime, array $conditions, ?Transaction $transaction): self
    {
        if ($runtime === TransactionQueryService::RUNTIME_INSTANT && $transaction) {
            $this->query->where('id', $transaction->id);
        } elseif ($runtime === TransactionQueryService::RUNTIME_24HR) {
            // Get duration from conditions (default 24 hours)
            $durationHours = 24;
            foreach ($conditions as $c) {
                if (($c['attribute'] ?? '') === 'duration') {
                    $durationHours = (int)($c['value'] ?? 24);
                    break;
                }
            }
            $this->query->where('transaction_datetime', '>=', Carbon::now()->subHours($durationHours));
        }

        return $this;
    }

    /**
     * Apply all conditions from a rule's search_attributes
     */
    public function applyConditions(array $conditions, ?Transaction $transaction, ?string $accountNo): self
    {
        foreach ($conditions as $condition) {
            $attribute = $condition['attribute'] ?? '';
            $action = $condition['action'] ?? '';
            $value = $condition['value'] ?? '';
            $type = $condition['type'] ?? 'column';

            // Skip meta attributes
            if (in_array($attribute, ['duration'])) continue;

            // Handle account-based grouping
            if ($attribute === 'sender_account') {
                if ($accountNo) {
                    $this->query->where('sender_account_no', $accountNo);
                } elseif ($transaction) {
                    $this->query->where('sender_account_no', $transaction->sender_account_no);
                }
                continue;
            }

            if ($attribute === 'beneficiary_account') {
                if ($accountNo) {
                    $this->query->where('beneficiary_account_no', $accountNo);
                } elseif ($transaction) {
                    $this->query->where('beneficiary_account_no', $transaction->beneficiary_account_no);
                }
                continue;
            }

            // Advanced checks are evaluated post-query
            if ($type === 'advance') {
                $this->advanceChecks[] = $condition;
                continue;
            }

            // Column-level conditions
            $this->applyColumnCondition($attribute, $action, $value, $transaction);
        }

        return $this;
    }

    /**
     * Apply a single column-level condition
     */
    private function applyColumnCondition(string $attribute, string $action, mixed $value, ?Transaction $transaction): void
    {
        switch ($attribute) {
            case 'amount':
            case 'single_transaction_limit':
                $this->applyNumericCondition('amount', $action, $value);
                break;

            case 'transaction_type':
                if ($action === 'equal_to' && $value) {
                    $this->query->whereRaw('LOWER(transaction_type) = LOWER(?)', [$value]);
                }
                break;

            case 'channel':
                if ($action === 'equal_to' && $value) {
                    $this->query->whereRaw('LOWER(channel) = LOWER(?)', [$value]);
                }
                break;

            case 'narration':
                if ($action === 'has' && $value) {
                    $keywords = array_map('trim', explode(',', $value));
                    $this->query->where(function ($q) use ($keywords) {
                        foreach ($keywords as $kw) {
                            $q->orWhereRaw('LOWER(narration) LIKE ?', ['%' . strtolower($kw) . '%']);
                        }
                    });
                }
                break;

            case 'account_type':
                // Check customer's account_type via join
                if ($action === 'equal_to' && $value) {
                    $this->query->where(function ($q) use ($value) {
                        $q->whereIn('sender_account_no', function ($sq) use ($value) {
                            $sq->select('account_number')->from('customers')
                               ->whereRaw('LOWER(account_type) = LOWER(?)', [$value]);
                        })->orWhereIn('beneficiary_account_no', function ($sq) use ($value) {
                            $sq->select('account_number')->from('customers')
                               ->whereRaw('LOWER(account_type) = LOWER(?)', [$value]);
                        });
                    });
                }
                break;

            case 'state_of_residence':
                if ($action === 'in' && $value) {
                    $states = array_map('trim', explode(',', strtolower($value)));
                    $this->query->where(function ($q) use ($states) {
                        $q->whereIn('sender_account_no', function ($sq) use ($states) {
                            $sq->select('account_number')->from('customers')
                               ->whereIn(DB::raw('LOWER(state_of_residence)'), $states);
                        })->orWhereIn('beneficiary_account_no', function ($sq) use ($states) {
                            $sq->select('account_number')->from('customers')
                               ->whereIn(DB::raw('LOWER(state_of_residence)'), $states);
                        });
                    });
                }
                break;

            case 'transaction_time':
                if ($action === 'between' && is_array($value)) {
                    $from = $value['from'] ?? '23:00:00';
                    $to = $value['to'] ?? '04:00:00';
                    // Handle overnight time ranges (e.g., 23:00 to 04:00)
                    if ($from > $to) {
                        $this->query->where(function ($q) use ($from, $to) {
                            $q->whereRaw("TIME(transaction_datetime) >= ?", [$from])
                              ->orWhereRaw("TIME(transaction_datetime) <= ?", [$to]);
                        });
                    } else {
                        $this->query->whereRaw("TIME(transaction_datetime) BETWEEN ? AND ?", [$from, $to]);
                    }
                }
                break;

            case 'self_transfer':
                $this->query->whereColumn('sender_account_no', 'beneficiary_account_no');
                break;

            case 'in_internal_watchlist':
                $this->query->where(function ($q) {
                    $q->whereIn('sender_account_no', function ($sq) {
                        $sq->select('account_no')->from('internal_watch_lists');
                    })->orWhereIn('beneficiary_account_no', function ($sq) {
                        $sq->select('account_no')->from('internal_watch_lists');
                    })->orWhereIn('sender_bvn', function ($sq) {
                        $sq->select('bvn')->from('internal_watch_lists')->whereNotNull('bvn');
                    })->orWhereIn('beneficiary_bvn', function ($sq) {
                        $sq->select('bvn')->from('internal_watch_lists')->whereNotNull('bvn');
                    });
                });
                break;

            case 'in_nibss_watchlist':
                $this->query->where(function ($q) {
                    $q->whereIn('sender_bvn', function ($sq) {
                        $sq->select('bvn')->from('nibss_watch_lists')->whereNotNull('bvn');
                    })->orWhereIn('beneficiary_bvn', function ($sq) {
                        $sq->select('bvn')->from('nibss_watch_lists')->whereNotNull('bvn');
                    });
                });
                break;

            case 'transaction_risk_score':
                if ($transaction) {
                    $riskScore = DB::table('transaction_risks')
                        ->where('transaction_id', $transaction->id)
                        ->max('total_score') ?? 0;
                    if (!$this->evaluateNumeric($riskScore, $action, $value)) {
                        // Force empty result if risk score doesn't match
                        $this->query->whereRaw('1 = 0');
                    }
                }
                break;

            default:
                // Generic column check
                if ($action && $value) {
                    $this->applyNumericCondition($attribute, $action, $value);
                }
                break;
        }
    }

    private function applyNumericCondition(string $column, string $action, mixed $value): void
    {
        switch ($action) {
            case 'equal_to':
                $this->query->where($column, (float)$value);
                break;
            case 'not_equal_to':
                $this->query->where($column, '!=', (float)$value);
                break;
            case 'greater_than':
                $this->query->where($column, '>', (float)$value);
                break;
            case 'less_than':
                $this->query->where($column, '<', (float)$value);
                break;
            case 'equal_to_greater_than':
                $this->query->where($column, '>=', (float)$value);
                break;
            case 'equal_to_less_than':
                $this->query->where($column, '<=', (float)$value);
                break;
            case 'between':
                $parts = is_string($value) ? explode(',', $value) : [$value, $value];
                if (count($parts) >= 2) {
                    $this->query->whereBetween($column, [(float)trim($parts[0]), (float)trim($parts[1])]);
                }
                break;
            case 'has_fractions':
                // Amount has decimal part (not a round number)
                $this->query->whereRaw("$column != CAST($column AS INTEGER)");
                break;
        }
    }

    private function evaluateNumeric($actual, string $action, $expected): bool
    {
        $a = (float)$actual;
        $e = (float)$expected;
        return match($action) {
            'equal_to' => $a == $e,
            'greater_than' => $a > $e,
            'less_than' => $a < $e,
            'equal_to_greater_than' => $a >= $e,
            'equal_to_less_than' => $a <= $e,
            default => false,
        };
    }

    /**
     * Execute the query and evaluate advance checks
     */
    public function execute(): Collection
    {
        $results = $this->query->get();

        // Apply advance checks on the result set
        foreach ($this->advanceChecks as $check) {
            $results = $this->applyAdvanceCheck($results, $check);
        }

        return $results;
    }

    /**
     * Evaluate advance conditions on result set (aggregations)
     */
    private function applyAdvanceCheck(Collection $results, array $check): Collection
    {
        if ($results->isEmpty()) return $results;

        $attribute = $check['attribute'] ?? '';
        $action = $check['action'] ?? '';
        $value = $check['value'] ?? '';

        switch ($attribute) {
            case 'total_amount':
                $totalAmount = $results->sum('amount');
                return $this->evaluateNumeric($totalAmount, $action, $value) ? $results : collect();

            case 'total_transactions':
                $totalCount = $results->count();
                return $this->evaluateNumeric($totalCount, $action, $value) ? $results : collect();

            case 'daily_transaction_limit':
                // Group by day and check if any day exceeds the limit
                $grouped = $results->groupBy(fn($t) => Carbon::parse($t->transaction_datetime)->toDateString());
                foreach ($grouped as $date => $dayTxns) {
                    $dayTotal = $dayTxns->sum('amount');
                    if ($this->evaluateNumeric($dayTotal, $action, $value)) {
                        return $dayTxns;
                    }
                }
                return collect();

            case 'last_transaction_amount':
                if ($action === 'increased_by' && $results->count() >= 2) {
                    $sorted = $results->sortByDesc('transaction_datetime')->values();
                    $current = (float)$sorted[0]->amount;
                    $previous = (float)$sorted[1]->amount;
                    if ($previous > 0) {
                        $percentIncrease = (($current - $previous) / $previous) * 100;
                        return $percentIncrease >= (float)$value ? $results : collect();
                    }
                }
                return collect();

            case 'last_transaction_date':
                // Check if account was inactive for X days
                if ($results->count() >= 2) {
                    $sorted = $results->sortByDesc('transaction_datetime')->values();
                    $current = Carbon::parse($sorted[0]->transaction_datetime);
                    $previous = Carbon::parse($sorted[1]->transaction_datetime);
                    $daysDiff = $current->diffInDays($previous);
                    return $this->evaluateNumeric($daysDiff, $action, $value) ? $results : collect();
                }
                return collect();

            case 'multiple_sender':
                $uniqueSenders = $results->pluck('sender_account_no')->unique()->count();
                return $this->evaluateNumeric($uniqueSenders, $action, $value) ? $results : collect();

            case 'multiple_beneficiary':
                $uniqueBeneficiaries = $results->pluck('beneficiary_account_no')->unique()->count();
                return $this->evaluateNumeric($uniqueBeneficiaries, $action, $value) ? $results : collect();

            default:
                return $results;
        }
    }

    /**
     * Get advance checks for external evaluation
     */
    public function getAdvanceChecks(): array
    {
        return $this->advanceChecks;
    }
}
