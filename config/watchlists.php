<?php

return [
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
