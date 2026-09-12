<?php

/*
|--------------------------------------------------------------------------
| Sanction / Watchlist Sources
|--------------------------------------------------------------------------
| Downloaded, structured lists used by screening. Each source exposes a URL
| and a parser. The Nigerian sanctions list has no canonical machine-readable
| feed, so it ships disabled and must be pointed at a configured endpoint.
*/

return [

    'sources' => [
        'ofac_consolidated' => [
            'name' => 'OFAC Consolidated Sanctions',
            'url' => 'https://sanctionslistservice.ofac.treas.gov/api/PublicationPreview/exports/CONS_ENHANCED.XML',
            'parser' => 'ofac',
            'enabled' => (bool) env('SANCTIONS_OFAC_ENABLED', true),
        ],
        'un_consolidated' => [
            'name' => 'UN Security Council Consolidated List',
            'url' => 'https://scsanctions.un.org/resources/xml/en/consolidated.xml',
            'parser' => 'un',
            'enabled' => (bool) env('SANCTIONS_UN_ENABLED', true),
        ],
        'nigeria_sanctions' => [
            'name' => 'Nigerian Sanctions List',
            'url' => env('NIGERIA_SANCTIONS_URL', ''),
            'parser' => 'un',
            'enabled' => (bool) env('SANCTIONS_NIGERIA_ENABLED', false),
        ],
    ],

];
