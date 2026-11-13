@extends('layouts.app')
@section('title', 'Jelajahi Bandara — BANDARA ASEAN')
@php
    $token = app(\App\Services\AirportSearch::class)->token($q);
    $seo = [
        'description' => 'Jelajahi daftar bandara Asia Tenggara. Filter berdasarkan negara, kategori, status ASEAN, dan ketersediaan rute.',
        'canonical' => route('bandara.index'),
    ];
@endphp
@section('content')
<form class="facets" method="get" action="{{ route('bandara.index') }}" aria-label="Filter daftar bandara">
  <div class="field">
    <label for="q">Cari</label>
    <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama bandara, kode IATA, kota, atau alias">
  </div>
  <div class="field">
    <label for="negara">Negara</label>
    <select id="negara" name="negara">
      <option value="">Semua negara</option>
      @foreach ($negaraList as $n)
        <option value="{{ $n->label }}" @selected($n->label === $negara)>{{ $n->label }} ({{ $n->airports_count }})</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="kategori">Kategori</label>
    <select id="kategori" name="kategori">
      <option value="">Semua kategori</option>
      @foreach ($kategoriList as $kat)
        <option value="{{ $kat }}" @selected($kat === $kategori)>{{ $kat }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="urut">Urutkan</label>
    <select id="urut" name="urut">
      <option value="relevan" @selected($urut === 'relevan')>Paling relevan</option>
      <option value="nama" @selected($urut === 'nama')>Nama A-Z</option>
      <option value="penumpang" @selected($urut === 'penumpang')>Penumpang terbanyak</option>
    </select>
  </div>
  <div class="field">
    <label>Filter</label>
    <label class="check"><input type="checkbox" name="asean" value="1" @checked($asean === '1')> Hanya ASEAN</label>
    <label class="check"><input type="checkbox" name="rute" value="1" @checked($rute === '1')> Punya rute</label>
  </div>
  <div class="aksi">
    <button class="btn" type="submit">Terapkan</button>
    <a class="btn ghost" href="{{ route('bandara.index') }}">Reset</a>
  </div>
</form>

<div class="page-head">
  <h1>Jelajahi bandara</h1>
  <span class="count">{{ number_format($bandara->total(), 0, ',', '.') }} bandara</span>
</div>

@if($bandara->isEmpty())
  <div class="state">
    Tidak ada hasil. Coba kata kunci atau filter lain.
    <ul class="empty-help">
      <li>Gunakan kode IATA 3 huruf, mis. CGK atau SIN.</li>
      <li>Coba alias populer seperti Cengkareng, Ngurah Rai, atau KLIA.</li>
    </ul>
  </div>
@else
  <div class="list">
    @foreach ($bandara as $b)
      @include('partials.row', [
          'r' => $b,
          'token' => $token,
          'sub' => collect([$b['kota'] ?? null, $b['wilayah'] ?? null, $b['negara']])->filter()->unique()->implode(', '),
      ])
    @endforeach
  </div>

  @include('partials.pager', ['paginator' => $bandara])
@endif
@endsection
