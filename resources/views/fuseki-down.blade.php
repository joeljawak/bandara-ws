@extends('layouts.app')
@section('title', 'Fuseki tidak tersedia — BANDARA ASEAN')

@section('content')
<div class="page-head">
  <h1>Apache Jena Fuseki tidak tersedia</h1>
</div>

<div class="state" role="alert">
  Aplikasi ini mengambil seluruh data bandara dari Apache Jena Fuseki.
  Selama Fuseki belum berjalan, pencarian dan halaman data tidak bisa dipakai.
</div>

<p style="margin-top:18px"><strong>Penyebab:</strong> {{ $alasan }}</p>
<p>Endpoint yang dicoba: <code>{{ $endpoint }}</code></p>

<p style="margin-top:18px"><strong>Cara memperbaiki:</strong></p>
<ol>
  <li>Jalankan Apache Jena Fuseki dan pastikan dataset <code>bandara</code> ada (tipe persistent TDB2).</li>
  <li>Pastikan <code>SPARQL_ENDPOINT</code> di <code>.env</code> sama dengan alamat dataset, misalnya <code>http://localhost:3030/bandara/sparql</code>.</li>
  <li>Kalau dataset masih kosong, muat datanya: <code>php artisan fuseki:load</code></li>
  <li>Segarkan halaman ini.</li>
</ol>
@endsection
