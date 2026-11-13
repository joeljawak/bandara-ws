<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SPARQL endpoint
    |--------------------------------------------------------------------------
    |
    | FUSEKI_URL dipertahankan agar .env lama tetap jalan, tetapi aplikasi
    | hanya membaca satu kunci ini. Arahkan langsung ke endpoint /sparql.
    |
    */

    'endpoint' => env('SPARQL_ENDPOINT', env('FUSEKI_URL', 'http://localhost:3030/bandara/sparql')),

    'timeout' => (int) env('SPARQL_TIMEOUT', 15),

    'vocabulary' => env('SPARQL_VOCABULARY', 'http://example.org/asean-airport#'),

    'legacy_vocabulary' => env('SPARQL_LEGACY_VOCABULARY', 'http://example.org/bandara#'),

    'aviationstack_key' => env('AVIATIONSTACK_KEY'),

];
