@extends('layouts.app')
@section('title', 'Peta bandara - BANDARA ASEAN')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
@endsection

@section('content')
<div class="page-head">
  <h1>Peta bandara</h1>
  <span class="count">{{ number_format($total, 0, ',', '.') }} bandara memiliki koordinat</span>
</div>

<form class="filter wide" method="get" action="{{ route('data.map') }}">
  <div class="field">
    <label for="asean">Kawasan</label>
    <select id="asean" name="asean">
      <option value="">Semua bandara</option>
      <option value="1" @selected($asean === '1')>ASEAN saja</option>
      <option value="0" @selected($asean === '0')>Luar ASEAN</option>
    </select>
  </div>
  <button class="btn" type="submit">Terapkan</button>
</form>

<div class="statrow">
  <div class="statbox"><div class="n">{{ number_format($airports->count(), 0, ',', '.') }}</div><div class="k">titik ditampilkan</div></div>
  <div class="statbox"><div class="n">{{ number_format($airports->where('in_asean', true)->count(), 0, ',', '.') }}</div><div class="k">bandara ASEAN</div></div>
  <div class="statbox"><div class="n">{{ number_format($airports->where('in_asean', false)->count(), 0, ',', '.') }}</div><div class="k">bandara luar ASEAN</div></div>
</div>

<div id="peta-data" class="mapbig" role="region" aria-label="Peta seluruh bandara"></div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function () {
  var bandara = @json($airports);
  var peta = L.map('peta-data').setView([5, 110], 4);
  var batas = [];

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(peta);

  bandara.forEach(function (b) {
    if (typeof b.latitude !== 'number' || typeof b.longitude !== 'number') {
      return;
    }

    var titik = [b.latitude, b.longitude];
    batas.push(titik);

    var penanda = L.circleMarker(titik, {
      radius: 5,
      weight: 1,
      color: b.in_asean ? '#1d3f9e' : '#d62839',
      fillColor: b.in_asean ? '#1d3f9e' : '#d62839',
      fillOpacity: 0.75,
    }).addTo(peta);

    var isi = document.createElement('div');
    var judul = document.createElement('strong');
    judul.textContent = (b.iata || '—') + ' · ' + b.label;
    isi.appendChild(judul);

    var lokasi = document.createElement('div');
    lokasi.textContent = [b.region, b.country].filter(Boolean).join(', ') || 'Lokasi belum tersedia';
    isi.appendChild(lokasi);

    if (b.url) {
      var tautan = document.createElement('a');
      tautan.href = b.url;
      tautan.textContent = 'Lihat detail';
      isi.appendChild(document.createElement('br'));
      isi.appendChild(tautan);
    }

    penanda.bindPopup(isi);
  });

  if (batas.length > 0) {
    peta.fitBounds(batas, { padding: [30, 30] });
  }
})();
</script>
@endpush
