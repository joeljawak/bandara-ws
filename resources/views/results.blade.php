@extends('layouts.app')
@section('title', 'Hasil: ' . $q . ' — AeroSearch')
@section('content')
<div class="searchbar">
  <form class="searchrow" action="{{ route('search') }}" method="get">
    <input type="search" name="q" value="{{ $q }}" aria-label="Kata kunci pencarian" required>
    <button type="submit">Cari</button>
  </form>
</div>

@if($error)
  <div class="state">{{ $error }}</div>
@elseif($q === '')
  <div class="state">Masukkan kata kunci untuk mulai mencari.</div>
@elseif(count($results) === 0)
  <div class="state">Tidak ada bandara untuk &quot;{{ $q }}&quot;. Coba nama kota atau kode IATA.</div>
@else
  <p class="count">{{ count($results) }} bandara ditemukan</p>
  <div class="list">
    @foreach($results as $r)
      <a class="board-row" href="{{ route('airport.show', $r['iata']) }}">
        <span class="code">{{ $r['iata'] }}</span>
        <span>
          <div class="row-name">{{ $r['nama'] }}</div>
          <div class="row-sub">{{ $r['kota'] }}{{ isset($r['icao']) ? ', ' . $r['icao'] : '' }}</div>
        </span>
      </a>
    @endforeach
  </div>
@endif
@endsection
