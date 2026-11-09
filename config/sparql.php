<?php

return [
    // FUSEKI_URL harus menunjuk langsung ke endpoint query dataset, bukan dashboard.
    'endpoint' => env('SPARQL_ENDPOINT', env('FUSEKI_URL', 'http://localhost:3030/bandara/sparql')),
    'aviationstack_key' => env('AVIATIONSTACK_KEY'),
];
