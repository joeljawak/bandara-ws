@extends('layouts.app')
@section('title', 'Data bandara - BANDARA ASEAN')

@section('content')
<div class="page-head">
  <h1>Data bandara</h1>
  <span class="count">{{ number_format($total, 0, ',', '.') }} bandara di database relasional</span>
</div>

<form class="filter" method="get" action="{{ route('data.airports') }}">
  <div class="field">
    <label for="q">Cari</label>
    <input id="q" type="text" name="q" value="{{ $search }}" placeholder="Nama bandara, IATA, ICAO, kota, atau operator">
  </div>
  <div class="field">
    <label for="country">Negara</label>
    <select id="country" name="country">
      <option value="">Semua negara</option>
      @foreach ($countries as $country)
        <option value="{{ $country->id }}" @selected($countryId === $country->id)>{{ $country->label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="asean">Kawasan</label>
    <select id="asean" name="asean">
      <option value="">Semua</option>
      <option value="1" @selected($asean === '1')>ASEAN</option>
      <option value="0" @selected($asean === '0')>Luar ASEAN</option>
    </select>
  </div>
  <button class="btn" type="submit">Cari</button>
</form>

@if ($airports->isEmpty())
  <div class="state">Tidak ada bandara yang cocok. Coba kata kunci atau filter lain.</div>
@else
  <div class="list">
    @foreach ($airports as $airport)
      <div class="res">
        <div>
          <div class="res-top">
            <span class="code">{{ $airport->iata_code ?? '—' }}</span>
            @if ($airport->in_asean)
              <span class="badge asean">ASEAN</span>
            @endif
            <span>{{ collect([$airport->country?->label, $airport->region?->label])->filter()->implode(', ') ?: 'Lokasi belum tersedia' }}</span>
          </div>
          @if ($airport->iata_code)
            <a class="res-name" href="{{ route('data.airport', $airport->iata_code) }}">{{ $airport->label }}</a>
          @else
            <span class="res-name">{{ $airport->label }}</span>
          @endif
          <div class="res-sub">
            ICAO {{ $airport->icao_code ?? '-' }}
            &middot; {{ $airport->category ?? 'Kategori belum ada' }}
            &middot; {{ $airport->status ?? 'Status belum ada' }}
          </div>
        </div>
        <div class="res-sub" style="text-align:right">
          @if ($airport->annual_passengers !== null)
            <strong>{{ number_format($airport->annual_passengers, 0, ',', '.') }}</strong><br>penumpang/tahun
          @elseif ($airport->number_of_destinations !== null)
            <strong>{{ $airport->number_of_destinations }}</strong><br>tujuan
          @endif
        </div>
      </div>
    @endforeach
  </div>

  @include('data.partials.pager', ['paginator' => $airports])
@endif
@endsection
