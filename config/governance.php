<?php

/*
|--------------------------------------------------------------------------
| Case, reporting & governance (CBN 5.7 / 5.8 / 5.9)
|--------------------------------------------------------------------------
| Central configuration for Phase 5:
|  - Case TAT/SLA per risk level (5.7(a)(i))
|  - Maker-checker disposition flow (5.7(a)(ii))
|  - CTR detection + filing (5.8(a)(i))
|  - STR filing SLA
|  - Audit retention/archival (5.9(a)(iii))
|
| Every value below is a default — it can be overridden at runtime via the
| `settings` table using the same keys (settings('key', config(...))).
*/

return [

    // ── 5.1 Case TAT / SLA (hours) per customer risk level ────────────
    'sla' => [
        'enabled' => true,
        'default_tat_hours' => 48,
        'risk_level_defaults' => [
            'Low' => 72,
            'Medium' => 48,
            'High' => 24,
        ],
    ],

    // ── 5.2 Maker-checker disposition flow ────────────────────────────
    'maker_checker' => [
        'enabled' => true,
    ],

    // ── 5.4 CTR detection (cash transaction reports) ───────────────────
    'ctr' => [
        'threshold_individual' => 5000000,    // ₦5,000,000 per day (individual)
        'threshold_corporate'  => 10000000,   // ₦10,000,000 per day (corporate)
        'cash_channels'        => ['atm', 'bank', 'cash'],
        'window_days'          => 1,
    ],

    // ── 5.4 STR filing SLA (days from case creation) ───────────────────
    'filing' => [
        'str_sla_days' => 5,
    ],

    // ── 5.5 Audit retention / archival (days, 5 years by default) ──────
    'audit' => [
        'retention_days' => 1825,
    ],

];
