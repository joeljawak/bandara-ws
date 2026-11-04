@extends('layouts.app')
@section('title', $q . ' - BANDARA ASEAN')
@section('content')
<div class="top-search">@include('partials.searchbox')</div>

@if($error)
  <div class="state">{{ $error }}</div>
@elseif($q === '')
  <div class="state">Masukkan kata kunci untuk mulai mencari.</div>
@elseif(count($results) === 0)
  <div class="state">Tidak ada bandara untuk &quot;{{ $q }}&quot;. Coba nama kota atau kode IATA.</div>
@else
  <p class="count" style="margin:22px 0 0">{{ count($results) }} bandara ditemukan untuk &quot;{{ $q }}&quot;</p>
  <div class="list">
    @foreach($results as $r)
      @include('partials.row', [
          'r' => $r,
          'sub' => collect([$r['kota'] ?? 'Lokasi tidak tersedia', $r['negara'] ?? null])->filter()->implode(', '),
      ])
    @endforeach
  </div>
@endif
@endsection
