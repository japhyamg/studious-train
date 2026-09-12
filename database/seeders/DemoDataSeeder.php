<?php

namespace Database\Seeders;

use App\Models\BusinessDetails;
use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\FlaggedCaseComment;
use App\Models\NfiuIndicator;
use App\Models\NibssWatchList;
use App\Models\RiskLevel;
use App\Models\Setting;
use App\Models\State;
use App\Models\Transaction;
use App\Models\TransactionRule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@finsyt.com'],
            ['name' => 'System Admin', 'password' => bcrypt('password')]
        );
        $admin->assignRole('admin');

        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor@finsyt.com'],
            ['name' => 'Adebayo Ogundimu', 'password' => bcrypt('password')]
        );
        $supervisor->assignRole('supervisor');

        $reviewer1 = User::firstOrCreate(
            ['email' => 'reviewer1@finsyt.com'],
            ['name' => 'Chioma Nwosu', 'password' => bcrypt('password')]
        );
        $reviewer1->assignRole('reviewer');

        $reviewer2 = User::firstOrCreate(
            ['email' => 'reviewer2@finsyt.com'],
            ['name' => 'Ibrahim Musa', 'password' => bcrypt('password')]
        );
        $reviewer2->assignRole('reviewer');

        $auditor = User::firstOrCreate(
            ['email' => 'auditor@finsyt.com'],
            ['name' => 'Funke Adeyemi', 'password' => bcrypt('password')]
        );
        $auditor->assignRole('auditor');

        $reviewers = [$reviewer1, $reviewer2];

        // Transaction Rules — all 26 rules from production
        $rules = [
            ['name' => 'Rule #1', 'description' => 'Debit between 400,000 and 999,999 naira at once', 'search_attributes' => json_encode([['attribute'=>'amount','action'=>'between','value'=>'400000,999999','type'=>'column'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'debit','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #2', 'description' => 'Total debit of 400,000+ in 24 hours', 'search_attributes' => json_encode([['attribute'=>'total_amount','action'=>'equal_to_greater_than','value'=>'400000','type'=>'advance'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'debit','type'=>'column'],['attribute'=>'sender_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'duration','action'=>'equal_to','value'=>'24','type'=>'column']]), 'run_time' => '24HrTask', 'report_type' => 'STR'],
            ['name' => 'Rule #3', 'description' => 'Credit between 400,000 and 999,999 naira at once', 'search_attributes' => json_encode([['attribute'=>'transaction_type','action'=>'equal_to','value'=>'credit','type'=>'column'],['attribute'=>'amount','action'=>'between','value'=>'400000,999999','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #4', 'description' => 'Credit inflow of 400,000+ in 24 hours', 'search_attributes' => json_encode([['attribute'=>'beneficiary_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'credit','type'=>'column'],['attribute'=>'total_amount','action'=>'equal_to_greater_than','value'=>'400000','type'=>'advance'],['attribute'=>'duration','action'=>'equal_to','value'=>'24','type'=>'column']]), 'run_time' => '24HrTask', 'report_type' => 'STR'],
            ['name' => 'Rule #5', 'description' => 'Transaction between 11pm and 4am', 'search_attributes' => json_encode([['attribute'=>'transaction_time','action'=>'between','value'=>['from'=>'23:00:00','to'=>'04:00:00'],'type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #6', 'description' => '20+ transactions in 24 hours', 'search_attributes' => json_encode([['attribute'=>'total_transactions','action'=>'equal_to_greater_than','value'=>'20','type'=>'advance'],['attribute'=>'duration','action'=>'equal_to','value'=>'24','type'=>'column']]), 'run_time' => '24HrTask', 'report_type' => 'STR'],
            ['name' => 'Rule #7', 'description' => 'Credit between 1M and 4.99M', 'search_attributes' => json_encode([['attribute'=>'amount','action'=>'between','value'=>'1000000,4999999','type'=>'advance'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'credit','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #8', 'description' => 'Debit between 1M and 4.99M', 'search_attributes' => json_encode([['attribute'=>'amount','action'=>'between','value'=>'1000000,4999999','type'=>'advance'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'debit','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #9', 'description' => 'Transaction after 90 days inactive', 'search_attributes' => json_encode([['attribute'=>'last_transaction_date','action'=>'equal_to_greater_than','value'=>'90','type'=>'advance']]), 'run_time' => '24HrTask', 'report_type' => 'STR'],
            ['name' => 'Rule #10', 'description' => 'Credit 100% higher than last credit', 'search_attributes' => json_encode([['attribute'=>'beneficiary_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'credit','type'=>'column'],['attribute'=>'last_transaction_amount','action'=>'increased_by','value'=>'100','type'=>'advance']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #11', 'description' => 'Fractional amount transaction', 'search_attributes' => json_encode([['attribute'=>'amount','action'=>'has_fractions','value'=>'','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #12', 'description' => 'North-East state transaction 500K+ in 24hrs', 'search_attributes' => json_encode([['attribute'=>'state_of_residence','action'=>'in','value'=>'Adamawa,Bauchi,Borno,Gombe,Taraba','type'=>'column'],['attribute'=>'total_amount','action'=>'equal_to_greater_than','value'=>'500000','type'=>'advance'],['attribute'=>'duration','action'=>'equal_to','value'=>'24','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #13', 'description' => 'Crypto-related narration', 'search_attributes' => json_encode([['attribute'=>'narration','action'=>'has','value'=>'btc,bitcoin,buybit,binance,ethereum','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #14', 'description' => 'Individual debit 5M+ daily limit', 'search_attributes' => json_encode([['attribute'=>'account_type','action'=>'equal_to','value'=>'individual','type'=>'column'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'debit','type'=>'column'],['attribute'=>'sender_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'daily_transaction_limit','action'=>'equal_to_greater_than','value'=>'5000000','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #15', 'description' => 'Corporate debit 10M+ daily limit', 'search_attributes' => json_encode([['attribute'=>'account_type','action'=>'equal_to','value'=>'corporate','type'=>'column'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'debit','type'=>'column'],['attribute'=>'sender_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'daily_transaction_limit','action'=>'equal_to_greater_than','value'=>'10000000','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #16', 'description' => '5+ senders to one beneficiary in 24hrs', 'search_attributes' => json_encode([['attribute'=>'beneficiary_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'multiple_sender','action'=>'equal_to_greater_than','value'=>'5','type'=>'column']]), 'run_time' => '24HrTask', 'report_type' => 'STR'],
            ['name' => 'Rule #17', 'description' => 'One sender to 5+ beneficiaries in 24hrs', 'search_attributes' => json_encode([['attribute'=>'sender_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'multiple_beneficiary','action'=>'equal_to_greater_than','value'=>'5','type'=>'column']]), 'run_time' => '24HrTask', 'report_type' => 'STR'],
            ['name' => 'Rule #18', 'description' => 'Self transfer', 'search_attributes' => json_encode([['attribute'=>'self_transfer','action'=>'','value'=>'','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #19', 'description' => 'Individual single txn 5M+ (CTR)', 'search_attributes' => json_encode([['attribute'=>'account_type','action'=>'equal_to','value'=>'individual','type'=>'column'],['attribute'=>'single_transaction_limit','action'=>'greater_than','value'=>'4999999','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'CTR'],
            ['name' => 'Rule #20', 'description' => 'Corporate single txn 10M+ (CTR)', 'search_attributes' => json_encode([['attribute'=>'account_type','action'=>'equal_to','value'=>'corporate','type'=>'column'],['attribute'=>'single_transaction_limit','action'=>'greater_than','value'=>'9999999','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'CTR'],
            ['name' => 'Rule #21', 'description' => 'Individual credit 5M+ daily accumulative', 'search_attributes' => json_encode([['attribute'=>'account_type','action'=>'equal_to','value'=>'individual','type'=>'column'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'credit','type'=>'column'],['attribute'=>'beneficiary_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'daily_transaction_limit','action'=>'equal_to_greater_than','value'=>'5000000','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #22', 'description' => 'Corporate credit 10M+ daily accumulative', 'search_attributes' => json_encode([['attribute'=>'account_type','action'=>'equal_to','value'=>'corporate','type'=>'column'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'credit','type'=>'column'],['attribute'=>'beneficiary_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'daily_transaction_limit','action'=>'equal_to_greater_than','value'=>'10000000','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #23', 'description' => 'Debit 100% higher than last debit', 'search_attributes' => json_encode([['attribute'=>'sender_account','action'=>'','value'=>'','type'=>'column'],['attribute'=>'transaction_type','action'=>'equal_to','value'=>'debit','type'=>'column'],['attribute'=>'last_transaction_amount','action'=>'increased_by','value'=>'100','type'=>'advance']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #24', 'description' => 'Sender or beneficiary in Internal watchlist', 'search_attributes' => json_encode([['attribute'=>'in_internal_watchlist','action'=>'','value'=>'','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #25', 'description' => 'Sender or beneficiary in NIBSS watchlist', 'search_attributes' => json_encode([['attribute'=>'in_nibss_watchlist','action'=>'','value'=>'','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR'],
            ['name' => 'Rule #26', 'description' => 'Transaction risk score above threshold', 'search_attributes' => json_encode([['attribute'=>'transaction_risk_score','action'=>'equal_to_greater_than','value'=>'90','type'=>'column']]), 'run_time' => 'Instantly', 'report_type' => 'STR', 'status' => true],
        ];

        $ruleModels = [];
        foreach ($rules as $rule) {
            $ruleModels[] = TransactionRule::firstOrCreate(['name' => $rule['name']], $rule);
        }

        // Customers (1001 records from SQL file)
        $this->call(CustomerSeeder::class);
        $customers = Customer::inRandomOrder()->limit(20)->get()->all();
        if (empty($customers)) {
            $this->command?->warn('No customers found. Skipping transaction/case generation.');
            return;
        }

        // Transactions
        $types = ['credit', 'debit'];
        $channels = ['bank', 'mobile', 'atm', 'pos', 'online'];

        for ($i = 0; $i < 150; $i++) {
            $sender = $customers[array_rand($customers)];
            $receiver = $customers[array_rand($customers)];

            Transaction::create([
                'sender_first_name' => $sender->first_name,
                'sender_middle_name' => $sender->middle_name,
                'sender_last_name' => $sender->last_name,
                'sender_account_no' => $sender->account_number,
                'sender_bvn' => $sender->bvn,
                'sender_nin' => $sender->nin,
                'beneficiary_first_name' => $receiver->first_name,
                'beneficiary_middle_name' => $receiver->middle_name,
                'beneficiary_last_name' => $receiver->last_name,
                'beneficiary_account_no' => $receiver->account_number,
                'beneficiary_bvn' => $receiver->bvn,
                'beneficiary_nin' => $receiver->nin,
                'amount' => rand(5000, 10000000),
                'transaction_type' => $types[array_rand($types)],
                'narration' => 'Transaction ' . ($i + 1),
                'channel' => $channels[array_rand($channels)],
                'location' => $sender->state_of_residence,
                'transaction_ref' => 'TXN-' . strtoupper(Str::random(10)),
                'transaction_datetime' => now()->subDays(rand(0, 90))->subHours(rand(0, 23)),
            ]);
        }

        // Flagged Cases
        $caseStatuses = ['open', 'open', 'open', 'closed_filed', 'closed_not_filed', 'escalated'];

        for ($i = 0; $i < 25; $i++) {
            $rule = $ruleModels[array_rand($ruleModels)];
            $txn = Transaction::inRandomOrder()->first();
            $status = $caseStatuses[array_rand($caseStatuses)];

            $case = FlaggedCase::create([
                'slug' => sprintf('CASE-%s-%05d', date('Ymd'), $i + 1),
                'transaction_rule_id' => $rule->id,
                'transaction_id' => $txn->id,
                'account_no' => $txn->sender_account_no,
                'status' => $status,
                'classification' => rand(0, 4) === 0 ? 'false_positive' : 'true_positive',
                'report_type' => $rule->report_type,
                'user_id' => $reviewers[array_rand($reviewers)]->id,
                'closed_by' => in_array($status, ['closed_filed', 'closed_not_filed']) ? $supervisor->id : null,
                'created_at' => now()->subDays(rand(1, 60)),
            ]);

            FlaggedCaseComment::create([
                'flagged_case_id' => $case->id,
                'comment' => 'Case opened for review.',
                'user_id' => $reviewers[array_rand($reviewers)]->id,
                'created_at' => $case->created_at,
            ]);
        }

        // NFIU Indicators (328 records — dedicated seeder)
        $this->call(NfiuIndicatorSeeder::class);

        // Risk Levels
        RiskLevel::firstOrCreate(['label' => 'Low'], ['min_score' => 0, 'max_score' => 33, 'review_schedule_days' => 365, 'diligence_type' => 'CDD']);
        RiskLevel::firstOrCreate(['label' => 'Medium'], ['min_score' => 34, 'max_score' => 66, 'review_schedule_days' => 90, 'diligence_type' => 'EDD']);
        RiskLevel::firstOrCreate(['label' => 'High'], ['min_score' => 67, 'max_score' => 100, 'review_schedule_days' => 30, 'diligence_type' => 'EDD']);

        // Settings
        Setting::firstOrCreate(['name' => 'two_step_verification'], ['value' => 'false']);
        Setting::firstOrCreate(['name' => 'case_notification'], ['value' => 'true']);
        Setting::firstOrCreate(['name' => 'false_positive_threshold'], ['value' => '30']);
        Setting::firstOrCreate(['name' => 'pg_selected_fields'], ['value' => 'gender, customer_type']);
        Setting::firstOrCreate(['name' => 'pg_recompute_interval'], ['value' => '7']);
        Setting::firstOrCreate(['name' => 'risk_scoring_threshold'], ['value' => '100']);
        Setting::firstOrCreate(['name' => 'risk_rating_demo_mode'], ['value' => 'false']);

        // Business Details
        BusinessDetails::firstOrCreate(['name' => 'business_name'], ['value' => 'FINSYT']);
        BusinessDetails::firstOrCreate(['name' => 'case_prefix'], ['value' => 'CASE']);
        BusinessDetails::firstOrCreate(['name' => 'phone'], ['value' => '08012345678']);
        BusinessDetails::firstOrCreate(['name' => 'address'], ['value' => 'Lagos, Nigeria']);
        BusinessDetails::firstOrCreate(['name' => 'state'], ['value' => 'Lagos']);
        BusinessDetails::firstOrCreate(['name' => 'business_uuid'], ['value' => Str::uuid()->toString()]);

        // Nigerian States
        $statesData = [
            ['Lagos', 'South West'], ['Ogun', 'South West'], ['Oyo', 'South West'], ['Osun', 'South West'], ['Ondo', 'South West'], ['Ekiti', 'South West'],
            ['Rivers', 'South South'], ['Delta', 'South South'], ['Bayelsa', 'South South'], ['Edo', 'South South'], ['Akwa Ibom', 'South South'], ['Cross River', 'South South'],
            ['Anambra', 'South East'], ['Enugu', 'South East'], ['Imo', 'South East'], ['Abia', 'South East'], ['Ebonyi', 'South East'],
            ['Kano', 'North West'], ['Kaduna', 'North West'], ['Katsina', 'North West'], ['Sokoto', 'North West'], ['Zamfara', 'North West'], ['Kebbi', 'North West'], ['Jigawa', 'North West'],
            ['Borno', 'North East'], ['Adamawa', 'North East'], ['Bauchi', 'North East'], ['Gombe', 'North East'], ['Taraba', 'North East'], ['Yobe', 'North East'],
            ['Plateau', 'North Central'], ['Niger', 'North Central'], ['Benue', 'North Central'], ['Kwara', 'North Central'], ['Kogi', 'North Central'], ['Nasarawa', 'North Central'], ['FCT', 'North Central'],
        ];
        foreach ($statesData as [$name, $zone]) {
            State::firstOrCreate(['name' => $name], ['zone' => $zone]);
        }

        // NIBSS Watchlist sample
        NibssWatchList::firstOrCreate(['bvn' => '22345678901'], [
            'first_name' => 'Suspicious', 'last_name' => 'Person',
            'category' => '1', 'reason' => 'Fraud',
            'watchlisted_date' => now()->subMonths(3)->toDateString(),
        ]);
    }
}
