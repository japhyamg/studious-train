<?php

return [
    'searchapi_key' => env('SEARCHAPI_KEY', ''),

    'pep_keywords' => [
        'president', 'minister', 'senator', 'governor',
        'chairman', 'director general', 'commissioner',
        'ambassador', 'chief justice', 'speaker',
        'attorney general', 'comptroller', 'inspector general',
        'secretary', 'representative', 'councillor',
        'political', 'government', 'public office',
    ],

    'adverse_media_keywords' => [
        'fraud', 'corruption', 'crime', 'money laundering',
        'arrest', 'conviction', 'sanction', 'terrorist',
        'trafficking', 'embezzlement', 'bribery', 'scandal',
        'indictment', 'investigation', 'criminal',
    ],
];
