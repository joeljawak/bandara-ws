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

    /*
    | Alamat dataset Fuseki (tanpa /sparql), dipakai untuk Graph Store Protocol.
    | Kalau kosong, diturunkan dari endpoint di atas.
    */
    'dataset_url' => env('SPARQL_DATASET_URL'),

    /*
    | Mode live: Fuseki menjadi sumber data RDF. Katalog /bandara memakai SELECT
    | langsung dan pagination di Fuseki; halaman lama yang masih memakai relasi
    | Eloquent memakai FusekiDataset sebagai adapter sementara.
    | Matikan hanya untuk pengujian (FUSEKI_LIVE=false memakai database lokal).
    */
    'live' => (bool) env('FUSEKI_LIVE', true),

    /*
    | Lama (detik) hasil unduhan dari Fuseki dipakai ulang supaya halaman tidak
    | mengunduh ulang puluhan ribu triple di setiap klik. Fuseki tetap dicek
    | di SETIAP permintaan: kalau mati, web langsung berhenti mencari. Perubahan
    | jumlah triple terdeteksi seketika; perubahan lain paling lambat setelah
    | masa ini. Isi 0 untuk membaca ulang dari Fuseki pada setiap permintaan.
    */
    'cache_seconds' => (int) env('FUSEKI_CACHE_SECONDS', 10),

    'timeout' => (int) env('SPARQL_TIMEOUT', 15),

    // Unduh seluruh graph (puluhan ribu triple) butuh waktu lebih lama.
    'dump_timeout' => (int) env('SPARQL_DUMP_TIMEOUT', 120),

    'vocabulary' => env('SPARQL_VOCABULARY', 'http://example.org/asean-airport#'),

    'legacy_vocabulary' => env('SPARQL_LEGACY_VOCABULARY', 'http://example.org/bandara#'),

    'aviationstack_key' => env('AVIATIONSTACK_KEY'),

];
