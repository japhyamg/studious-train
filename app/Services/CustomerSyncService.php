<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CustomerSyncService
{
    /**
     * Default column mapping: secondary_db_column => monisurv_column
     * This can be overridden via the settings table (customer_sync_column_mapping)
     */
    protected array $defaultMapping = [
        'first_name' => 'first_name',
        'middle_name' => 'middle_name',
        'last_name' => 'last_name',
        'account_number' => 'account_number',
        'date_of_birth' => 'date_of_birth',
        'bvn' => 'bvn',
        'nin' => 'nin',
        'gender' => 'gender',
        'customer_type' => 'customer_type',
        'account_type' => 'account_type',
        'tier_level' => 'tier_level',
        'state_of_residence' => 'state_of_residence',
        'local_govt_area' => 'local_govt_area',
        'date_onboarded' => 'date_onboarded',
        'occupation' => 'occupation',
        'source_of_funds' => 'source_of_funds',
        'income_range' => 'income_range',
        'business_activity' => 'business_activity',
        'employer_name' => 'employer_name',
        'phone_number' => 'phone_number',
        'email' => 'email',
        'address' => 'address',
    ];

    /**
     * Run the full sync process
     */
    public function sync(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        // Check if secondary DB is configured
        $connection = $this->getConnectionName();
        $table = $this->getSourceTable();

        if (!$this->isConfigured()) {
            Log::warning('Customer sync: Secondary database not configured.');
            return array_merge($stats, ['message' => 'Secondary database not configured.']);
        }

        try {
            // Test connection
            DB::connection($connection)->getPdo();
        } catch (\Exception $e) {
            Log::error("Customer sync: Cannot connect to secondary DB - {$e->getMessage()}");
            return array_merge($stats, ['message' => "Connection failed: {$e->getMessage()}"]);
        }

        $mapping = $this->getColumnMapping();
        $accountField = $this->getSourceAccountField();
        $batchSize = (int) settings('customer_sync_batch_size', 500);

        // Get the unique key column from the source
        $lastSyncAt = settings('customer_sync_last_run', null);

        try {
            $query = DB::connection($connection)->table($table);

            // If we have a last sync timestamp, only get records modified since then
            $updatedAtField = $this->getSourceUpdatedAtField();
            if ($lastSyncAt && $updatedAtField) {
                $query->where($updatedAtField, '>', $lastSyncAt);
            }

            $totalRecords = (clone $query)->count();
            Log::info("Customer sync: Found {$totalRecords} records to process from '{$table}'");

            // Process in chunks
            $query->orderBy($accountField)->chunk($batchSize, function ($rows) use ($mapping, $accountField, &$stats) {
                foreach ($rows as $row) {
                    try {
                        $mapped = $this->mapRow($row, $mapping);

                        if (empty($mapped['account_number'])) {
                            $stats['skipped']++;
                            continue;
                        }

                        $existing = Customer::where('account_number', $mapped['account_number'])->first();

                        if ($existing) {
                            // Update only non-empty fields
                            $updateData = array_filter($mapped, fn($v) => !empty($v) && $v !== '—');
                            unset($updateData['account_number']); // Don't update the key

                            if (!empty($updateData)) {
                                $existing->update($updateData);
                                $stats['updated']++;
                            } else {
                                $stats['skipped']++;
                            }
                        } else {
                            // Create new customer
                            Customer::create($mapped);
                            $stats['created']++;
                        }
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        Log::warning("Customer sync row error: {$e->getMessage()}");
                    }
                }
            });

            // Update last sync timestamp
            Setting::updateOrCreate(
                ['name' => 'customer_sync_last_run'],
                ['value' => now()->toDateTimeString()]
            );
            Cache::forget(config('cache.prefix') . '-settings');

            $stats['message'] = "Sync completed: {$stats['created']} created, {$stats['updated']} updated, {$stats['skipped']} skipped, {$stats['errors']} errors.";
            Log::info("Customer sync: {$stats['message']}");

        } catch (\Exception $e) {
            $stats['message'] = "Sync failed: {$e->getMessage()}";
            Log::error("Customer sync: {$stats['message']}");
        }

        return $stats;
    }

    /**
     * Map a row from the source DB to MoniSurv customer fields
     */
    protected function mapRow(object $row, array $mapping): array
    {
        $data = [];
        $rowArray = (array) $row;

        foreach ($mapping as $sourceCol => $targetCol) {
            $value = $rowArray[$sourceCol] ?? null;

            // Clean invalid dates
            if (in_array($targetCol, ['date_of_birth', 'date_onboarded'])) {
                if ($value === '0000-00-00' || (is_string($value) && strpos($value, '-00') !== false)) {
                    $value = null;
                }
            }

            // Convert isPep from 0/1 to yes/no
            if ($targetCol === 'isPep' && isset($rowArray[$sourceCol])) {
                $value = ((int) $rowArray[$sourceCol] === 1 || strtolower($rowArray[$sourceCol] ?? '') === 'yes') ? 'yes' : 'no';
            }

            $data[$targetCol] = $value;
        }

        return $data;
    }

    /**
     * Check if secondary DB is configured
     */
    public function isConfigured(): bool
    {
        $db = config('database.connections.secondary_mysql.database')
            ?: settings('customer_sync_database', '');
        return !empty($db);
    }

    /**
     * Get the connection name (supports runtime override from settings)
     */
    protected function getConnectionName(): string
    {
        // Allow overriding connection params from settings at runtime
        $host = settings('customer_sync_host', '');
        $database = settings('customer_sync_database', '');

        if ($host && $database) {
            config([
                'database.connections.secondary_mysql.host' => $host,
                'database.connections.secondary_mysql.port' => settings('customer_sync_port', '3306'),
                'database.connections.secondary_mysql.database' => $database,
                'database.connections.secondary_mysql.username' => settings('customer_sync_username', 'root'),
                'database.connections.secondary_mysql.password' => settings('customer_sync_password', ''),
            ]);
            DB::purge('secondary_mysql');
        }

        return 'secondary_mysql';
    }

    protected function getSourceTable(): string
    {
        return settings('customer_sync_table', env('SECONDARY_DB_TABLE', 'customers'));
    }

    protected function getSourceAccountField(): string
    {
        return settings('customer_sync_account_field', 'account_number');
    }

    protected function getSourceUpdatedAtField(): ?string
    {
        $field = settings('customer_sync_updated_at_field', 'updated_at');
        return !empty($field) ? $field : null;
    }

    protected function getColumnMapping(): array
    {
        $custom = settings('customer_sync_column_mapping', '');
        if ($custom && is_string($custom)) {
            $decoded = json_decode($custom, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }
        return $this->defaultMapping;
    }

    /**
     * Get sync interval in hours from settings
     */
    public static function getSyncIntervalHours(): int
    {
        return (int) settings('customer_sync_interval_hours', 24);
    }
}
