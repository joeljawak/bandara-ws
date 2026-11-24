@extends('layouts.app')
@section('title', 'Live Flight Radar - BANDARA ASEAN')

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://sg-webapi.opnavi.com/maps?v=2.0&key={{ $amapKey }}"></script>
<style>
  /* Styling Tile AMAP Dark Mode */
  .amap-dark-tiles {
    filter: invert(100%) hue-rotate(180deg) brightness(85%) contrast(115%) saturate(70%) !important;
  }
  /* Container Radar Gelap */
  .radar-page {
    margin-top: 20px;
    margin-bottom: 24px;
  }
  .radar-container {
    position: relative;
    width: 100%;
    height: 720px;
    min-height: 600px;
    border-radius: 24px;
    overflow: hidden;
    background: #090d16;
    border: 1px solid #1e293b;
    box-shadow: 0 20px 40px rgba(2, 6, 23, 0.4);
    display: flex;
    flex-direction: column;
  }

  /* Toolbar Atas Gelap */
  .radar-header {
    position: absolute;
    top: 14px;
    left: 14px;
    right: 14px;
    z-index: 1000;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    pointer-events: none;
  }
  .radar-header > * {
    pointer-events: auto;
  }
  .radar-brand-badge {
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(51, 65, 85, 0.6);
    color: #f8fafc;
    padding: 8px 16px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
    font-weight: 700;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
  }
  .radar-live-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 10px #10b981;
    animation: pulse-green 1.8s infinite;
  }
  @keyframes pulse-green {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
  }

  /* Search Box Radar */
  .radar-search-box {
    position: relative;
    flex: 1;
    max-width: 380px;
    min-width: 220px;
  }
  .radar-search-input {
    width: 100%;
    height: 42px;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(51, 65, 85, 0.6);
    border-radius: 999px;
    padding: 0 16px 0 40px;
    font-size: 13.5px;
    color: #f8fafc;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
    transition: border-color .15s, box-shadow .15s;
  }
  .radar-search-input:focus {
    outline: none;
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
  }
  .radar-search-input::placeholder {
    color: #94a3b8;
  }
  .radar-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
  }

  /* Filter Pills */
  .radar-filters {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .radar-filter-select {
    height: 42px;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(51, 65, 85, 0.6);
    color: #f8fafc;
    padding: 0 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
  }
  .radar-filter-select:focus {
    outline: none;
    border-color: #38bdf8;
  }
  .radar-filter-select option {
    background: #0f172a;
    color: #f8fafc;
  }

  /* Map Container */
  #map-radar {
    width: 100%;
    height: 100%;
    background: #070b14;
  }

  /* Marker Styling Pesawat */
  .plane-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.8s linear;
  }
  .plane-svg {
    width: 26px;
    height: 26px;
    fill: #f59e0b;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.85));
    transition: transform 0.3s ease, filter 0.2s ease;
  }
  .plane-svg:hover, .plane-active .plane-svg {
    fill: #38bdf8;
    filter: drop-shadow(0 0 8px #38bdf8);
    transform: scale(1.25);
  }

  /* Telemetry Drawer / Pop-up Card */
  .telemetry-card {
    position: absolute;
    right: 18px;
    bottom: 24px;
    width: 320px;
    max-width: calc(100% - 36px);
    background: rgba(15, 23, 42, 0.94);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(51, 65, 85, 0.8);
    border-radius: 20px;
    padding: 20px;
    color: #f8fafc;
    z-index: 1001;
    box-shadow: 0 20px 40px rgba(0,0,0,0.6);
    display: none;
    animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  }
  @keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .telemetry-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 14px;
    border-bottom: 1px solid rgba(51, 65, 85, 0.5);
    padding-bottom: 12px;
  }
  .telemetry-callsign {
    font-size: 22px;
    font-weight: 800;
    color: #38bdf8;
    line-height: 1.1;
  }
  .telemetry-airline {
    font-size: 13px;
    color: #94a3b8;
    margin-top: 3px;
  }
  .telemetry-close {
    background: rgba(51, 65, 85, 0.4);
    border: 0;
    color: #94a3b8;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    display: grid;
    place-items: center;
    font-weight: bold;
    transition: background .15s, color .15s;
  }
  .telemetry-close:hover {
    background: #ef4444;
    color: #fff;
  }
  .telemetry-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    font-size: 12.5px;
  }
  .telemetry-item {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(51, 65, 85, 0.4);
    border-radius: 12px;
    padding: 8px 12px;
  }
  .telemetry-label {
    color: #94a3b8;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 2px;
  }
  .telemetry-val {
    font-size: 15px;
    font-weight: 700;
    color: #f8fafc;
  }

  /* Bottom Banner & Legend */
  .radar-footer-banner {
    position: absolute;
    bottom: 14px;
    left: 14px;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(51, 65, 85, 0.6);
    color: #cbd5e1;
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 12.5px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
  }
  .radar-legend {
    display: flex;
    align-items: center;
    gap: 14px;
    font-weight: 600;
  }
  .radar-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
  }

  /* Styling Leaflet Controls di Dark Mode */
  .leaflet-control-layers {
    background: rgba(15, 23, 42, 0.92) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(51, 65, 85, 0.7) !important;
    border-radius: 14px !important;
    color: #f8fafc !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
    font-family: inherit;
    font-size: 12.5px;
    padding: 8px 12px !important;
  }
  .leaflet-control-layers label {
    color: #f8fafc !important;
    cursor: pointer;
    margin: 4px 0;
  }
  .leaflet-control-zoom a {
    background: rgba(15, 23, 42, 0.9) !important;
    color: #f8fafc !important;
    border-color: rgba(51, 65, 85, 0.7) !important;
  }
  .leaflet-control-zoom a:hover {
    background: #1e293b !important;
    color: #38bdf8 !important;
  }

  @media(max-width: 768px) {
    .radar-container {
      height: 560px;
    }
    .radar-header {
      flex-direction: column;
      align-items: stretch;
    }
    .radar-search-box {
      max-width: 100%;
    }
    .radar-footer-banner {
      display: none;
    }
  }
</style>
@endsection

@section('content')
<div class="radar-page">
  <div class="page-head" style="margin-bottom: 16px;">
    <div>
      <h1 style="margin:0; font-size: 28px;">Live Flight Radar</h1>
      <p style="margin:4px 0 0; color: var(--muted); font-size: 14.5px;">
        Pelacakan langsung posisi pesawat (live ADS-B) di wilayah udara Indonesia & Asia Tenggara.
      </p>
    </div>
  </div>

  <div class="radar-container" role="region" aria-label="Radar penerbangan live">
    <!-- Toolbar Atas -->
    <div class="radar-header">
      <div class="radar-brand-badge">
        <span class="radar-live-dot"></span>
        <span id="clock-display">00:00:00 WIB</span>
        <span style="opacity: 0.4;">|</span>
        <span id="flight-count-badge">Memuat pesawat...</span>
      </div>

      <!-- Kotak Pencarian Callsign / Maskapai -->
      <div class="radar-search-box">
        <svg class="radar-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="radar-search" class="radar-search-input" placeholder="Cari nomor penerbangan / maskapai (mis. GIA)...">
      </div>

      <!-- Filter Kategori & Maskapai -->
      <div class="radar-filters">
        <select id="filter-airline" class="radar-filter-select" aria-label="Filter Maskapai">
          <option value="">Semua Maskapai</option>
          <option value="Garuda Indonesia">Garuda Indonesia</option>
          <option value="Lion Air">Lion Air</option>
          <option value="Citilink">Citilink</option>
          <option value="Batik Air">Batik Air</option>
          <option value="AirAsia">AirAsia</option>
          <option value="Singapore Airlines">Singapore Airlines</option>
          <option value="Malaysia Airlines">Malaysia Airlines</option>
          <option value="Scoot">Scoot</option>
        </select>

        <select id="filter-status" class="radar-filter-select" aria-label="Filter Status Terbang">
          <option value="all">Semua Status</option>
          <option value="air" selected>Sedang Terbang</option>
          <option value="ground">Di Darat / Landasan</option>
        </select>
      </div>
    </div>

    <!-- Peta Leaflet Dark -->
    <div id="map-radar"></div>

    <!-- Telemetry Card (Slide in saat pesawat diklik) -->
    <div id="telemetry-card" class="telemetry-card">
      <div class="telemetry-header">
        <div>
          <div id="tel-callsign" class="telemetry-callsign">GIA000</div>
          <div id="tel-airline" class="telemetry-airline">Garuda Indonesia 🇮🇩</div>
        </div>
        <button id="tel-close" class="telemetry-close" aria-label="Tutup detail">&times;</button>
      </div>

      <div class="telemetry-grid">
        <div class="telemetry-item">
          <div class="telemetry-label">Ketinggian</div>
          <div id="tel-alt" class="telemetry-val">0 m</div>
        </div>
        <div class="telemetry-item">
          <div class="telemetry-label">Kecepatan</div>
          <div id="tel-speed" class="telemetry-val">0 km/h</div>
        </div>
        <div class="telemetry-item">
          <div class="telemetry-label">Arah (Heading)</div>
          <div id="tel-heading" class="telemetry-val">0°</div>
        </div>
        <div class="telemetry-item">
          <div class="telemetry-label">Status</div>
          <div id="tel-status" class="telemetry-val" style="color: #10b981;">Di Udara</div>
        </div>
        <div class="telemetry-item" style="grid-column: span 2;">
          <div class="telemetry-label">Transponder (ICAO24)</div>
          <div id="tel-icao" class="telemetry-val" style="font-family: monospace;">-</div>
        </div>
      </div>

      <div id="tel-nearest-airport" style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(51,65,85,0.4); font-size: 12px; color: #94a3b8;">
        <!-- Terisi otomatis jarak bandara terdekat -->
      </div>
    </div>

    <!-- Floating Legend & Info di Bawah -->
    <div class="radar-footer-banner">
      <div class="radar-legend">
        <div class="radar-legend-item">
          <span style="color: #f59e0b; font-size: 16px;">✈️</span> Pesawat Live
        </div>
        <div class="radar-legend-item">
          <span class="legend-dot" style="background: #38bdf8;"></span> Bandara Internasional
        </div>
        <div class="radar-legend-item">
          <span class="legend-dot" style="background: #10b981;"></span> Bandara Domestik
        </div>
      </div>
      <span style="opacity: 0.4;">•</span>
      <span>💡 Ketuk pesawat untuk melihat telemetri real-time</span>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function() {
  // Inisialisasi Jam Live
  function updateClock() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2, '0');
    var m = String(now.getMinutes()).padStart(2, '0');
    var s = String(now.getSeconds()).padStart(2, '0');
    var clockEl = document.getElementById('clock-display');
    if (clockEl) clockEl.textContent = h + ':' + m + ':' + s + ' WIB';
  }
  setInterval(updateClock, 1000);
  updateClock();

  // Inisialisasi Peta Leaflet Dark
  // Titik tengah Indonesia: lat -2.5, lng 118.0, zoom 5
  var map = L.map('map-radar', {
    zoomControl: false
  }).setView([-1.5, 117.0], 5);

  L.control.zoom({ position: 'bottomright' }).addTo(map);

  // Key AMAP Platform dari konfigurasi .env
  var amapKey = '{{ $amapKey }}';

  // 1. Layer Utama: AMAP Platform / AutoNavi dengan Dark Radar Filter (Memakai API Key Anda)
  var amapDarkLayer = L.tileLayer('https://webrd0{s}.is.autonavi.com/appmaptile?lang=en&size=1&scale=1&style=8&x={x}&y={y}&z={z}&key=' + amapKey, {
    subdomains: '1234',
    maxZoom: 18,
    className: 'amap-dark-tiles',
    attribution: '&copy; <a href="https://console.opnavi.com" target="_blank" style="color:#38bdf8;">AMAP Platform</a> (AutoNavi) &copy; OpenSky Network'
  }).addTo(map);

  // 2. Layer AMAP Citra Satelit
  var amapSatLayer = L.tileLayer('https://webst0{s}.is.autonavi.com/appmaptile?style=6&x={x}&y={y}&z={z}&key=' + amapKey, {
    subdomains: '1234',
    maxZoom: 18,
    attribution: '&copy; AMAP Platform (AutoNavi Satellite)'
  });

  // 3. Layer AMAP Peta Jalan Standard
  var amapRoadLayer = L.tileLayer('https://webrd0{s}.is.autonavi.com/appmaptile?lang=en&size=1&scale=1&style=8&x={x}&y={y}&z={z}&key=' + amapKey, {
    subdomains: '1234',
    maxZoom: 18,
    attribution: '&copy; AMAP Platform (AutoNavi)'
  });

  // Base Maps Alternatif
  var esriDarkBase = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 16,
    attribution: '&copy; Esri, HERE, Garmin, OpenStreetMap'
  });

  var esriDarkLabels = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Reference/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 16,
    pane: 'overlayPane',
    opacity: 0.85
  });

  var darkRadarLayer = L.layerGroup([esriDarkBase, esriDarkLabels]);

  var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 18,
    attribution: '&copy; Esri, Maxar, Earthstar Geographics'
  });

  var osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  });

  // Load Bandara dari database lokal
  var airports = @json($airports);
  var airportLayer = L.layerGroup().addTo(map);

  // Kontrol Pilihan Layer di Kanan Bawah
  L.control.layers({
    "🗺️ AMAP Dark Radar (API Key Anda)": amapDarkLayer,
    "🛰️ AMAP Citra Satelit": amapSatLayer,
    "🌐 AMAP Peta Jalan (Standard)": amapRoadLayer,
    "🌙 Esri Dark Gray (Alternatif)": darkRadarLayer,
    "🗺️ OpenStreetMap": osmLayer
  }, {
    "📍 Titik Bandara ASEAN": airportLayer
  }, { position: 'bottomright' }).addTo(map);

  airports.forEach(function(a) {
    if (!a.lat || !a.lng) return;
    var kat = (a.kategori || '').toLowerCase();
    var isIntl = kat.includes('internasional') || kat.includes('international');
    var dotColor = isIntl ? '#38bdf8' : '#10b981';

    var dot = L.circleMarker([a.lat, a.lng], {
      radius: isIntl ? 4 : 2.5,
      fillColor: dotColor,
      color: '#020617',
      weight: 1,
      opacity: 0.8,
      fillOpacity: isIntl ? 0.85 : 0.6
    });

    var airportUrl = a.iata ? ('/bandara/' + encodeURIComponent(a.iata.toLowerCase())) : (a.url || '');

    var popupHtml = '<div style="color: #0f172a; font-family: sans-serif; font-size: 13px;">' +
      '<strong>' + (a.iata || '—') + ' · ' + a.nama + '</strong><br>' +
      '<span style="color: #64748b; font-size: 11.5px;">' + a.negara + ' (' + (a.kategori || 'Bandara') + ')</span><br>' +
      (airportUrl ? '<a href="' + airportUrl + '" style="color: #0284c7; font-weight: 600; text-decoration: none; display: inline-block; margin-top: 4px;">Buka Detail Bandara &rarr;</a>' : '') +
      '</div>';

    dot.bindPopup(popupHtml);
    airportLayer.addLayer(dot);
  });

  // Manajemen Marker Pesawat
  var planeMarkers = {}; // icao24 -> { marker, data }
  var allFlights = [];
  var selectedFlight = null;

  // Template SVG Pesawat
  function getPlaneIcon(heading) {
    var svg = '<svg class="plane-svg" style="transform: rotate(' + (heading || 0) + 'deg);" viewBox="0 0 24 24">' +
      '<path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>' +
      '</svg>';

    return L.divIcon({
      className: 'plane-icon-wrapper',
      html: svg,
      iconSize: [26, 26],
      iconAnchor: [13, 13]
    });
  }

  // Hitung jarak Haversine (km)
  function hitungJarak(lat1, lon1, lat2, lon2) {
    var R = 6371;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon/2) * Math.sin(dLon/2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return Math.round(R * c);
  }

  // Cari bandara terdekat
  function cariBandaraTerdekat(lat, lng) {
    var terdekat = null;
    var minJarak = Infinity;
    for (var i = 0; i < airports.length; i++) {
      var ap = airports[i];
      if (!ap.lat || !ap.lng) continue;
      var d = hitungJarak(lat, lng, ap.lat, ap.lng);
      if (d < minJarak) {
        minJarak = d;
        terdekat = ap;
      }
    }
    return terdekat ? { bandara: terdekat, jarak: minJarak } : null;
  }

  // Tampilkan Telemetry Card
  function showTelemetry(flight) {
    selectedFlight = flight;
    var card = document.getElementById('telemetry-card');
    document.getElementById('tel-callsign').textContent = flight.callsign;
    document.getElementById('tel-airline').textContent = (flight.airline ? flight.airline + ' ' : '') + (flight.flag || '') + ' (' + flight.country + ')';
    document.getElementById('tel-alt').textContent = flight.altitude_m ? flight.altitude_m.toLocaleString('id-ID') + ' m (' + flight.altitude_ft.toLocaleString('id-ID') + ' ft)' : '—';
    document.getElementById('tel-speed').textContent = flight.speed_kmh ? flight.speed_kmh + ' km/h (' + flight.speed_kts + ' kts)' : '—';
    document.getElementById('tel-heading').textContent = flight.heading + '°';

    var statusEl = document.getElementById('tel-status');
    if (flight.on_ground) {
      statusEl.textContent = 'Di Darat';
      statusEl.style.color = '#94a3b8';
    } else {
      statusEl.textContent = 'Sedang Terbang';
      statusEl.style.color = '#10b981';
    }

    document.getElementById('tel-icao').textContent = flight.icao24.toUpperCase() + (flight.squawk ? ' • Squawk: ' + flight.squawk : '');

    // Bandara terdekat
    var nearest = cariBandaraTerdekat(flight.lat, flight.lng);
    var nearestEl = document.getElementById('tel-nearest-airport');
    if (nearest && nearest.bandara) {
      var nUrl = nearest.bandara.iata ? ('/bandara/' + encodeURIComponent(nearest.bandara.iata.toLowerCase())) : (nearest.bandara.url || '');
      nearestEl.innerHTML = '📍 <strong>Dekat ' + (nearest.bandara.iata || '') + ' ' + nearest.bandara.nama + '</strong> (' + nearest.jarak + ' km)' +
        (nUrl ? ' &middot; <a href="' + nUrl + '" style="color:#38bdf8; text-decoration:none;">Lihat bandara</a>' : '');
    } else {
      nearestEl.innerHTML = '';
    }

    card.style.display = 'block';
  }

  document.getElementById('tel-close').addEventListener('click', function() {
    document.getElementById('telemetry-card').style.display = 'none';
    selectedFlight = null;
  });

  // Filter Pesawat
  function filterFlights() {
    var search = document.getElementById('radar-search').value.trim().toUpperCase();
    var airline = document.getElementById('filter-airline').value;
    var status = document.getElementById('filter-status').value;

    var visibleCount = 0;

    Object.keys(planeMarkers).forEach(function(icao) {
      var item = planeMarkers[icao];
      var f = item.data;
      var show = true;

      // Filter Status
      if (status === 'air' && f.on_ground) show = false;
      if (status === 'ground' && !f.on_ground) show = false;

      // Filter Airline
      if (airline && f.airline !== airline) show = false;

      // Filter Search
      if (search) {
        var matchCallsign = f.callsign && f.callsign.toUpperCase().includes(search);
        var matchAirline = f.airline && f.airline.toUpperCase().includes(search);
        var matchCountry = f.country && f.country.toUpperCase().includes(search);
        if (!matchCallsign && !matchAirline && !matchCountry) show = false;
      }

      if (show) {
        if (!map.hasLayer(item.marker)) {
          item.marker.addTo(map);
        }
        visibleCount++;
      } else {
        if (map.hasLayer(item.marker)) {
          map.removeLayer(item.marker);
        }
      }
    });

    document.getElementById('flight-count-badge').textContent = visibleCount + ' Pesawat Live';
  }

  document.getElementById('radar-search').addEventListener('input', filterFlights);
  document.getElementById('filter-airline').addEventListener('change', filterFlights);
  document.getElementById('filter-status').addEventListener('change', filterFlights);

  // Fetch Live Data dari Backend Laravel
  function fetchLiveFlights() {
    fetch('{{ route("api.live-flights") }}')
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !Array.isArray(data.flights)) return;

        allFlights = data.flights;
        var currentIcaos = {};

        allFlights.forEach(function(f) {
          currentIcaos[f.icao24] = true;

          if (planeMarkers[f.icao24]) {
            // Update posisi marker yang sudah ada
            var marker = planeMarkers[f.icao24].marker;
            marker.setLatLng([f.lat, f.lng]);
            marker.setIcon(getPlaneIcon(f.heading));
            planeMarkers[f.icao24].data = f;
          } else {
            // Buat marker baru
            var marker = L.marker([f.lat, f.lng], {
              icon: getPlaneIcon(f.heading)
            });

            marker.on('click', function() {
              showTelemetry(f);
            });

            marker.bindTooltip(
              '<strong>' + f.callsign + '</strong><br>' +
              (f.airline ? f.airline + '<br>' : '') +
              (f.altitude_ft ? f.altitude_ft.toLocaleString() + ' ft' : ''),
              { direction: 'top', offset: [0, -10], opacity: 0.9 }
            );

            planeMarkers[f.icao24] = { marker: marker, data: f };
            marker.addTo(map);
          }
        });

        // Hapus pesawat yang sudah mendarat / keluar radar
        Object.keys(planeMarkers).forEach(function(icao) {
          if (!currentIcaos[icao]) {
            map.removeLayer(planeMarkers[icao].marker);
            delete planeMarkers[icao];
          }
        });

        filterFlights();
      })
      .catch(function(err) {
        console.warn('Gagal memperbarui data live flight:', err);
      });
  }

  // Jalankan fetch pertama kali
  fetchLiveFlights();

  // Polling tiap 8 detik
  setInterval(fetchLiveFlights, 8000);
})();
</script>
@endpush
