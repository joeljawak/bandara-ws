@extends('layouts.app')
@section('title', 'Rute Penerbangan — AeroSearch')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
@endsection

@section('content')
<div class="panel">
  <h1>Lihat rute antar bandara</h1>
  <p class="lead">Pilih bandara asal dan tujuan untuk melihat jalurnya di peta.</p>

  @if($error)
    <div class="state">{{ $error }}</div>
  @else
    <form class="routeform" method="get" action="{{ route('route.index') }}">
      <div>
        <label for="dari">Dari</label>
        <select id="dari" name="dari" required>
          <option value="">Pilih bandara</option>
          @foreach($bandara as $b)
            <option value="{{ $b['iata'] }}" {{ $dari === $b['iata'] ? 'selected' : '' }}>{{ $b['iata'] }} - {{ $b['nama'] }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="ke">Ke</label>
        <select id="ke" name="ke" required>
          <option value="">Pilih bandara</option>
          @foreach($bandara as $b)
            <option value="{{ $b['iata'] }}" {{ $ke === $b['iata'] ? 'selected' : '' }}>{{ $b['iata'] }} - {{ $b['nama'] }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn" type="submit">Lihat rute</button>
    </form>
  @endif
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

  <div id="peta-rute"></div>

  @push('scripts')
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <script>
  (function () {
    var asal = [{{ $asal['lat'] }}, {{ $asal['lon'] }}];
    var tujuan = [{{ $tujuan['lat'] }}, {{ $tujuan['lon'] }}];

    var peta = L.map('peta-rute');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 18,
    }).addTo(peta);

    L.marker(asal).addTo(peta).bindPopup('{{ $asal['iata'] }} - {{ addslashes($asal['nama']) }}');
    L.marker(tujuan).addTo(peta).bindPopup('{{ $tujuan['iata'] }} - {{ addslashes($tujuan['nama']) }}');

    L.polyline([asal, tujuan], { color: '#1f5fd6', weight: 3, dashArray: '2 8' }).addTo(peta);

    peta.fitBounds([asal, tujuan], { padding: [40, 40] });
  })();
  </script>
  @endpush
@endif
@endsection
