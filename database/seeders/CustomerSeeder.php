<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $sqlFile = database_path('data/customers.sql');

        if (!file_exists($sqlFile)) {
            $this->command?->warn("Customer SQL file not found at: {$sqlFile}");
            $this->command?->info("Place your customers.sql file in database/data/customers.sql");
            return;
        }

        $sql = file_get_contents($sqlFile);

        // Extract all value tuples from INSERT statements
        preg_match_all('/\((\d+),\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*(\d+),\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\'\)/', $sql, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            // Try alternate pattern (some fields might be NULL)
            preg_match_all('/\((\d+),\s*(NULL|\'[^\']*\'),\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*(\d+),\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\'\)/', $sql, $matches, PREG_SET_ORDER);
        }

        $count = 0;
        $batch = [];

        foreach ($matches as $m) {
            // Map SQL columns: id, bankId, first_name, middle_name, last_name, account_number,
            //                   date_of_birth, bvn, nin, account_type, tier_level, gender,
            //                   isPep, customer_type, state_of_residence, local_govt_area,
            //                   date_onboarded, created_at, updated_at

            $bankId = str_replace("'", "", $m[2] ?? '');
            if ($bankId === 'NULL') $bankId = null;

            $dob = $m[7] ?? null;
            $dateOnboarded = $m[17] ?? null;
            $createdAt = $m[18] ?? null;
            $updatedAt = $m[19] ?? null;

            // Fix invalid dates
            $dob = ($dob && $dob !== '0000-00-00' && strpos($dob, '-00') === false) ? $dob : null;
            $dateOnboarded = ($dateOnboarded && $dateOnboarded !== '0000-00-00') ? $dateOnboarded : null;
            $createdAt = ($createdAt && $createdAt !== '0000-00-00 00:00:00') ? $createdAt : now();
            $updatedAt = ($updatedAt && $updatedAt !== '0000-00-00 00:00:00') ? $updatedAt : now();

            // Fix dates with invalid month/day like '2003-00-13'
            if ($dob && preg_match('/\d{4}-00-/', $dob)) $dob = null;
            if ($dateOnboarded && preg_match('/\d{4}-00-/', $dateOnboarded)) $dateOnboarded = null;

            $batch[] = [
                'bankId' => $bankId,
                'first_name' => $m[3] ?? '',
                'middle_name' => $m[4] ?? '',
                'last_name' => $m[5] ?? '',
                'account_number' => $m[6] ?? '',
                'date_of_birth' => $dob,
                'bvn' => $m[8] ?? '',
                'nin' => $m[9] ?? '',
                'account_type' => $m[10] ?? '',
                'tier_level' => $m[11] ?? '',
                'gender' => $m[12] ?? '',
                'isPep' => ((int)($m[13] ?? 0)) === 1 ? 'yes' : 'no',
                'customer_type' => $m[14] ?? '',
                'state_of_residence' => $m[15] ?? '',
                'local_govt_area' => $m[16] ?? '',
                'date_onboarded' => $dateOnboarded,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];

            $count++;

            // Insert in chunks of 100
            if (count($batch) >= 100) {
                Customer::insert($batch);
                $batch = [];
            }
        }

        // Insert remaining
        if (!empty($batch)) {
            Customer::insert($batch);
        }

        $this->command?->info("Seeded {$count} customers.");
    }
}
