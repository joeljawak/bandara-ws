@extends('layouts.app')
@section('title', 'Rute Penerbangan — BANDARA ASEAN')
@php
    $seo = [
        'description' => 'Temukan lokasi Anda dan lihat perjalanan menuju bandara ASEAN, atau hitung rute penerbangan antar bandara.',
        'canonical' => route('route.index', array_filter([
            'dari' => $dari,
            'ke' => $ke,
        ])),
    ];
@endphp

@section('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
.routeform.routeform-location{grid-template-columns:minmax(190px,1fr) minmax(190px,1fr) minmax(240px,1.2fr) auto}
.location-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.btn.secondary{background:#fff;color:var(--blue-dark);border:1px solid var(--line);box-shadow:none}
.btn.secondary:hover{background:var(--blue-soft);border-color:var(--blue)}
.location-status{margin:7px 0 0;color:var(--muted);font-size:13px;line-height:1.45}
.route-note{margin-top:12px;color:var(--muted);font-size:13px}
.route-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:18px}
.route-stat{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;box-shadow:var(--shadow)}
.route-stat .k{font-size:12px;color:var(--muted);font-weight:600}
.route-stat .v{font-size:19px;font-weight:800;color:var(--blue-deep)}
#route-status{margin-top:12px}
@media(max-width:720px){.routeform.routeform-location{grid-template-columns:1fr}}
</style>
@endsection

@section('content')
<div class="panel">
  <h1>Cari rute menuju bandara</h1>
  <p class="lead">Gunakan lokasi GPS Anda sebagai titik awal untuk melihat perjalanan menuju bandara tujuan. Anda juga tetap bisa memilih bandara asal secara manual untuk melihat rute penerbangan.</p>

  <form class="routeform routeform-location" method="get" action="{{ route('route.index') }}" id="form-rute">
    <input type="hidden" name="mode" id="mode-asal" value="{{ $mode }}">
    <input type="hidden" name="_token" id="token-lokasi" value="{{ csrf_token() }}" disabled>
    <input type="hidden" name="lat" id="lokasi-lat" value="{{ $lokasiAsal['lat'] ?? '' }}">
    <input type="hidden" name="lon" id="lokasi-lon" value="{{ $lokasiAsal['lon'] ?? '' }}">

    <div class="field">
      <label>Lokasi asal</label>
      <div class="location-actions">
        <button class="btn" type="button" id="gunakan-lokasi">
          <span aria-hidden="true">◎</span> Gunakan lokasi saya
        </button>
      </div>
      <p class="location-status" id="status-lokasi" role="status" aria-live="polite">
        @if($mode === 'lokasi' && $lokasiAsal)
          Lokasi berhasil dimuat. Pilih bandara tujuan.
        @else
          Izinkan akses lokasi di browser, atau pilih bandara asal secara manual.
        @endif
      </p>
    </div>

    <div class="field">
      <label for="dari">Atau pilih bandara asal</label>
      <select id="dari" name="dari">
        <option value="">Pilih bandara asal</option>
        @foreach($bandara as $b)
          <option value="{{ $b['iata'] }}" @selected($mode === 'bandara' && $dari === $b['iata'])>{{ $b['iata'] }} - {{ $b['nama'] }}</option>
        @endforeach
      </select>
    </div>

    <div class="field">
      <label for="ke">Bandara tujuan</label>
      <select id="ke" name="ke" required>
        <option value="">Pilih bandara tujuan</option>
        @foreach($bandara as $b)
          <option value="{{ $b['iata'] }}" @selected($ke === $b['iata'])>{{ $b['iata'] }} - {{ $b['nama'] }}</option>
        @endforeach
      </select>
    </div>

    <button class="btn" type="submit">Cari rute</button>
  </form>
  <p class="route-note">Rute dari lokasi GPS menggunakan jalur jalan raya. Rute antarbandara tetap menampilkan informasi penerbangan langsung atau transit jika tersedia.</p>
</div>

@if($error)
  <div class="state" role="alert">{{ $error }}</div>
@endif

@if($mode === 'lokasi' && $lokasiAsal && $tujuan)
  <div class="facts">
    <div class="fact"><div class="k">Asal</div><div class="v">Lokasi saya</div></div>
    <div class="fact"><div class="k">Tujuan</div><div class="v">{{ $tujuan['iata'] }}</div></div>
    <div class="fact"><div class="k">Jarak garis lurus</div><div class="v">{{ number_format($jarak, 0, ',', '.') }} km</div></div>
  </div>

  <div class="route-stats" aria-live="polite">
    <div class="route-stat"><div class="k">Jarak perjalanan</div><div class="v" id="jarak-jalan">Menghitung...</div></div>
    <div class="route-stat"><div class="k">Estimasi waktu berkendara</div><div class="v" id="waktu-jalan">Menghitung...</div></div>
  </div>

  <div
    id="peta-rute"
    data-mode="lokasi"
    data-asal="{{ json_encode([$lokasiAsal['lat'], $lokasiAsal['lon']]) }}"
    data-tujuan="{{ json_encode([(float) $tujuan['lat'], (float) $tujuan['lon']]) }}"
    data-nama-asal="Lokasi saya"
    data-nama-tujuan="{{ $tujuan['iata'] }} - {{ $tujuan['nama'] }}"
  ></div>
  <div class="state" id="route-status" role="status" aria-live="polite">Sedang mencari jalur jalan raya menuju bandara...</div>

@elseif($mode === 'bandara' && $dari && $ke && $dari === $ke)
  <div class="state">Bandara asal dan tujuan tidak boleh sama.</div>
@elseif($mode === 'bandara' && $dari && $ke && (!$asal || !$tujuan))
  <div class="state">Salah satu bandara belum punya data koordinat, rute tidak bisa ditampilkan.</div>
@elseif($mode === 'bandara' && $asal && $tujuan)
  <div class="facts">
    <div class="fact"><div class="k">Asal</div><div class="v">{{ $asal['iata'] }}</div></div>
    <div class="fact"><div class="k">Tujuan</div><div class="v">{{ $tujuan['iata'] }}</div></div>
    <div class="fact"><div class="k">Jarak garis lurus</div><div class="v">{{ number_format($jarak, 0, ',', '.') }} km</div></div>
  </div>

  <div
    id="peta-rute"
    data-mode="bandara"
    data-asal="{{ json_encode([(float) $asal['lat'], (float) $asal['lon']]) }}"
    data-tujuan="{{ json_encode([(float) $tujuan['lat'], (float) $tujuan['lon']]) }}"
    data-nama-asal="{{ $asal['iata'] }} - {{ $asal['nama'] }}"
    data-nama-tujuan="{{ $tujuan['iata'] }} - {{ $tujuan['nama'] }}"
  ></div>

  @if($langsung->isNotEmpty())
    <h2 class="section">Rute penerbangan langsung</h2>
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
    <h2 class="section">Rute penerbangan langsung</h2>
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
@endif

@if(($mode === 'lokasi' && $lokasiAsal && $tujuan) || ($mode === 'bandara' && $asal && $tujuan))
  @push('scripts')
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <script>
  (function () {
    var petaEl = document.getElementById('peta-rute');
    if (!petaEl || typeof L === 'undefined') return;

    var asal = JSON.parse(petaEl.dataset.asal || '[0,0]');
    var tujuan = JSON.parse(petaEl.dataset.tujuan || '[0,0]');
    var namaAsal = petaEl.dataset.namaAsal || 'Asal';
    var namaTujuan = petaEl.dataset.namaTujuan || 'Tujuan';
    var mode = petaEl.dataset.mode || 'bandara';

    var peta = L.map('peta-rute');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(peta);

    var markerAsal = L.marker(asal).addTo(peta).bindPopup(namaAsal);
    L.marker(tujuan).addTo(peta).bindPopup(namaTujuan);
    var garis = L.polyline([asal, tujuan], {
      color: '#1f5fd6',
      weight: 4,
      dashArray: mode === 'lokasi' ? null : '5 8'
    }).addTo(peta);

    peta.fitBounds([asal, tujuan], { padding: [40, 40] });

    if (mode === 'lokasi') {
      var status = document.getElementById('route-status');
      var jarakEl = document.getElementById('jarak-jalan');
      var waktuEl = document.getElementById('waktu-jalan');
      var koordinat = asal[1] + ',' + asal[0] + ';' + tujuan[1] + ',' + tujuan[0];
      var url = 'https://router.project-osrm.org/route/v1/driving/' + koordinat + '?overview=full&geometries=geojson';

      fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (response) {
          if (!response.ok) throw new Error('Layanan rute tidak merespons.');
          return response.json();
        })
        .then(function (data) {
          if (!data.routes || !data.routes.length) throw new Error('Jalur jalan raya tidak ditemukan untuk lokasi ini.');
          var route = data.routes[0];
          var latlngs = route.geometry.coordinates.map(function (point) {
            return [point[1], point[0]];
          });

          peta.removeLayer(garis);
          garis = L.polyline(latlngs, { color: '#087fce', weight: 5, opacity: .9 }).addTo(peta);
          peta.fitBounds(garis.getBounds(), { padding: [40, 40] });

          jarakEl.textContent = (route.distance / 1000).toFixed(1).replace('.', ',') + ' km';
          var menit = Math.round(route.duration / 60);
          var jam = Math.floor(menit / 60);
          var sisaMenit = menit % 60;
          waktuEl.textContent = jam > 0 ? jam + ' jam ' + sisaMenit + ' menit' : menit + ' menit';
          status.textContent = 'Rute jalan raya berhasil ditemukan. Estimasi waktu dapat berubah mengikuti kondisi lalu lintas.';
        })
        .catch(function (error) {
          jarakEl.textContent = 'Tidak tersedia';
          waktuEl.textContent = 'Tidak tersedia';
          status.textContent = error.message + ' Peta tetap menampilkan garis langsung antara lokasi Anda dan bandara.';
        });
    }
  })();
  </script>
  @endpush
@endif

@push('scripts')
<script>
(function () {
  var form = document.getElementById('form-rute');
  if (!form) return;

  var tombol = document.getElementById('gunakan-lokasi');
  var status = document.getElementById('status-lokasi');
  var mode = document.getElementById('mode-asal');
  var lat = document.getElementById('lokasi-lat');
  var lon = document.getElementById('lokasi-lon');
  var pilihAsal = document.getElementById('dari');
  var token = document.getElementById('token-lokasi');
  var routeGet = @json(route('route.index'));
  var routePost = @json(route('route.location'));

  function setModeLokasi() {
    form.method = 'post';
    form.action = routePost;
    token.disabled = false;
  }

  function setModeBandara() {
    form.method = 'get';
    form.action = routeGet;
    token.disabled = true;
  }

  if (mode.value === 'lokasi' && lat.value && lon.value) {
    setModeLokasi();
  }

  if (pilihAsal) {
    pilihAsal.addEventListener('change', function () {
      if (pilihAsal.value) {
        mode.value = 'bandara';
        lat.value = '';
        lon.value = '';
        setModeBandara();
        status.textContent = 'Bandara asal dipilih. Rute akan dihitung sebagai rute penerbangan.';
      }
    });
  }

  tombol.addEventListener('click', function () {
    if (!navigator.geolocation) {
      status.textContent = 'Browser ini tidak mendukung fitur lokasi. Silakan pilih bandara asal secara manual.';
      return;
    }

    tombol.disabled = true;
    tombol.textContent = 'Mengambil lokasi...';
    status.textContent = 'Meminta izin lokasi dari browser...';

    navigator.geolocation.getCurrentPosition(function (position) {
      lat.value = position.coords.latitude.toFixed(7);
      lon.value = position.coords.longitude.toFixed(7);
      mode.value = 'lokasi';
      setModeLokasi();
      if (pilihAsal) pilihAsal.value = '';
      status.textContent = 'Lokasi berhasil ditemukan. Sekarang pilih bandara tujuan, lalu tekan Cari rute.';
      tombol.disabled = false;
      tombol.textContent = 'Lokasi berhasil ditemukan';
    }, function (error) {
      var pesan = 'Lokasi tidak berhasil diambil.';
      if (error.code === error.PERMISSION_DENIED) pesan = 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini, atau pilih bandara asal secara manual.';
      else if (error.code === error.POSITION_UNAVAILABLE) pesan = 'Informasi lokasi tidak tersedia. Coba aktifkan GPS atau pilih bandara asal manual.';
      else if (error.code === error.TIMEOUT) pesan = 'Waktu pencarian lokasi habis. Coba lagi.';
      status.textContent = pesan;
      tombol.disabled = false;
      tombol.textContent = 'Coba gunakan lokasi saya lagi';
    }, {
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 60000
    });
  });
})();
</script>
@endpush
@endsection
