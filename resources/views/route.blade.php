@extends('layouts.app')
@section('title', 'Rute Penerbangan — BANDARA ASEAN')
@php
    $seo = [
        'description' => 'Hitung jarak dan lihat rute langsung atau satu transit antar bandara Asia Tenggara di BANDARA ASEAN.',
        'canonical' => route('route.index', array_filter(['dari' => $dari, 'ke' => $ke])),
    ];
@endphp

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
@endsection

@section('content')
<div class="panel">
  <h1>Lihat rute antar bandara</h1>
  <p class="lead">Pilih bandara asal dan tujuan untuk melihat jalurnya di peta, rute langsung, dan kemungkinan satu transit.</p>

  <form class="routeform" method="get" action="{{ route('route.index') }}">
    <div class="field">
      <label for="dari">Dari</label>
      <select id="dari" name="dari" required>
        <option value="">Pilih bandara</option>
        @foreach($bandara as $b)
          <option value="{{ $b['iata'] }}" @selected($dari === $b['iata'])>{{ $b['iata'] }} - {{ $b['nama'] }}</option>
        @endforeach
      </select>
    </div>
    <div class="field">
      <label for="ke">Ke</label>
      <select id="ke" name="ke" required>
        <option value="">Pilih bandara</option>
        @foreach($bandara as $b)
          <option value="{{ $b['iata'] }}" @selected($ke === $b['iata'])>{{ $b['iata'] }} - {{ $b['nama'] }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn" type="submit">Lihat rute</button>
  </form>
</div>

@if($dari && $ke && $dari === $ke)
  <div class="state">Bandara asal dan tujuan tidak boleh sama.</div>
@elseif($dari && $ke && (!$asal || !$tujuan))
  <div class="state">Salah satu bandara belum punya data koordinat, rute tidak bisa ditampilkan.</div>
@elseif($asal && $tujuan)
  <div class="facts">
    <div class="fact"><div class="k">Asal</div><div class="v">{{ $asal['iata'] }}</div></div>
    <div class="fact"><div class="k">Tujuan</div><div class="v">{{ $tujuan['iata'] }}</div></div>
    <div class="fact"><div class="k">Jarak garis lurus</div><div class="v">{{ number_format($jarak, 0, ',', '.') }} km</div></div>
  </div>

  <div
    id="peta-rute"
    data-asal="{{ json_encode([$asal['lat'], $asal['lon']]) }}"
    data-tujuan="{{ json_encode([$tujuan['lat'], $tujuan['lon']]) }}"
    data-nama-asal="{{ $asal['iata'] }} - {{ $asal['nama'] }}"
    data-nama-tujuan="{{ $tujuan['iata'] }} - {{ $tujuan['nama'] }}"
  ></div>

  @if($langsung->isNotEmpty())
    <h2 class="section">Rute langsung</h2>
    <div class="routelist">
      @foreach($langsung as $r)
        <div class="routecard">
          <div class="r-head">
            <span class="code">{{ $r->originAirport->iata_code }}</span>
            <span class="arrow" aria-hidden="true">&rarr;</span>
            <span class="code">{{ $r->destinationAirport->iata_code }}</span>
            @if($r->airline)<span class="badge">{{ $r->airline->label }}</span>@endif
          </div>
          <div class="r-sub">{{ $r->originAirport->label }} &rarr; {{ $r->destinationAirport->label }}</div>
        </div>
      @endforeach
    </div>
  @else
    <h2 class="section">Rute langsung</h2>
    <div class="state">Belum ada rute langsung {{ $dari }} &rarr; {{ $ke }} di data.</div>

    @if($transit->isNotEmpty())
      <h2 class="section">Kemungkinan satu transit</h2>
      <div class="routelist">
        @foreach($transit as $t)
          <div class="routecard">
            <div class="r-head">
              <span class="code">{{ $dari }}</span>
              <span class="arrow" aria-hidden="true">&rarr;</span>
              <span class="code">{{ $t['transit'] }}</span>
              <span class="arrow" aria-hidden="true">&rarr;</span>
              <span class="code">{{ $ke }}</span>
            </div>
            <div class="r-sub">{{ $t['nama'] }} &middot; maskapai lanjutan: {{ implode(', ', $t['maskapai']) ?: 'belum tercatat' }}</div>
          </div>
        @endforeach
      </div>
    @else
      <div class="state">Tidak ditemukan rute langsung maupun satu transit untuk pasangan ini.</div>
    @endif
  @endif

  @push('scripts')
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <script>
  (function () {
    var petaEl = document.getElementById('peta-rute');
    var asal = JSON.parse(petaEl.dataset.asal || '[0,0]');
    var tujuan = JSON.parse(petaEl.dataset.tujuan || '[0,0]');
    var namaAsal = petaEl.dataset.namaAsal || 'Asal';
    var namaTujuan = petaEl.dataset.namaTujuan || 'Tujuan';

    var peta = L.map('peta-rute');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 18,
    }).addTo(peta);

    L.marker(asal).addTo(peta).bindPopup(namaAsal);
    L.marker(tujuan).addTo(peta).bindPopup(namaTujuan);

    L.polyline([asal, tujuan], { color: '#1f5fd6', weight: 3, dashArray: '2 8' }).addTo(peta);

    peta.fitBounds([asal, tujuan], { padding: [40, 40] });
  })();
  </script>
  @endpush
@endif
@endsection
