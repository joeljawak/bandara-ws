@extends('layouts.app')
@section('title', 'Jelajahi Bandara - BANDARA ASEAN')
@section('content')
<form class="filter" method="get" action="{{ route('bandara.index') }}">
  <div class="field">
    <label for="q">Cari</label>
    <input id="q" type="text" name="q" value="{{ $q }}" placeholder="Nama bandara, kode IATA, kota, atau provinsi">
  </div>
  <div class="field">
    <label for="negara">Negara</label>
    <select id="negara" name="negara">
      <option value="">Semua negara</option>
      @foreach ($negaraList as $n)
        <option value="{{ $n['negara'] }}" @selected($n['negara'] === $negara)>{{ $n['negara'] }}</option>
      @endforeach
    </select>
  </div>
  <button class="btn" type="submit">Cari</button>
</form>

<div class="page-head">
  <h1>Jelajahi bandara</h1>
  @if(!$error)<span class="count">{{ count($bandara) }} bandara (maksimal 50)</span>@endif
</div>

@if($error)
  <div class="state">{{ $error }}</div>
@elseif(count($bandara) === 0)
  <div class="state">Tidak ada hasil. Coba kata kunci atau negara lain.</div>
@else
  <div class="list">
    @foreach ($bandara as $b)
      @include('partials.row', [
          'r' => $b,
          'sub' => collect([$b['wilayah'] ?? null, $b['provinsi'] ?? null, $b['negara']])->filter()->implode(', '),
      ])
    @endforeach
  </div>
@endif
@endsection
