<?php

/*
|--------------------------------------------------------------------------
| Pre-emptive Alerting (CBN 5.5(a)(i)/(ii))
|--------------------------------------------------------------------------
| Config-driven behavioural factors. Each customer is scored daily; each
| triggered factor contributes its points. When total points reach the
| threshold, a PREEMPTIVE case is raised with the factor breakdown in
| trigger_details (explainability for 5.4(a)(iv)).
|
| Threshold can be overridden at runtime via the `preemptive_alert_threshold`
| setting.
*/

return [

    'threshold' => (int) env('PREEMPTIVE_ALERT_THRESHOLD', 5),

    'factors' => [
        'prior_str' => [
            'label' => 'Prior STR case in 30 days',
            'field' => 'str_count',
            'operator' => 'greater_than',
            'value' => '0',
            'window_days' => 30,
            'points' => 2,
        ],
        'prior_ctr' => [
            'label' => 'Prior CTR case in 30 days',
            'field' => 'ctr_count',
            'operator' => 'greater_than',
            'value' => '0',
            'window_days' => 30,
            'points' => 1,
        ],
        'pep_receipt' => [
            'label' => 'Received funds from a PEP account in 7 days',
            'field' => 'pep_receipt_count',
            'operator' => 'greater_than',
            'value' => '0',
            'window_days' => 7,
            'points' => 3,
        ],
        'midnight_activity' => [
            'label' => '5+ midnight (23:00–04:00) transactions in 7 days',
            'field' => 'midnight_transaction_count',
            'operator' => 'greater_than_equal',
            'value' => '5',
            'window_days' => 7,
            'points' => 2,
        ],
        'repeat_sender' => [
            'label' => '5+ credits from a single sender in 7 days',
            'field' => 'same_sender_count',
            'operator' => 'greater_than_equal',
            'value' => '5',
            'window_days' => 7,
            'points' => 2,
        ],
        'high_frequency' => [
            'label' => '20+ transactions in 7 days',
            'field' => 'transaction_count',
            'operator' => 'greater_than',
            'value' => '20',
            'window_days' => 7,
            'points' => 1,
        ],
        'high_credit_volume' => [
            'label' => 'Credit volume over ₦5,000,000 in 7 days',
            'field' => 'credit_value',
            'operator' => 'greater_than',
            'value' => '5000000',
            'window_days' => 7,
            'points' => 2,
        ],
    ],

];
