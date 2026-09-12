<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuzzy match threshold
    |--------------------------------------------------------------------------
    | Watchlist name matching (WatchListMatcher) returns a 0–100 score; a hit
    | is declared at or above this value. Overridable at runtime via the
    | `watchlist_match_threshold` setting.
    */
    'match_threshold' => (int) env('WATCHLIST_MATCH_THRESHOLD', 75),

    'internal_watch_lists' => [
        'table' => 'internal_watch_lists',
        'fields' => [
            'first_name' => 'like',
            'last_name' => 'like',
            'account_no' => '=',
            'bvn' => '=',
            'nin' => '=',
        ],
    ],
    'nibss_watch_lists' => [
        'table' => 'nibss_watch_lists',
        'fields' => [
            'first_name' => 'like',
            'last_name' => 'like',
            'bvn' => '=',
        ],
    ],
];
