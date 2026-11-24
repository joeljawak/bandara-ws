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
.routeform.routeform-location{display:grid;grid-template-columns:minmax(250px,.82fr) minmax(420px,1.55fr);align-items:end;gap:28px;margin-top:28px}
.route-field{min-width:0}
.route-field label{display:block;margin-bottom:9px;font-size:13px;font-weight:700;color:var(--ink,#24324a)}
.location-box{min-height:48px;display:flex;align-items:center;gap:12px;padding:10px 14px;border:1px solid var(--line);border-radius:13px;background:#f8fbff}
.location-dot{width:10px;height:10px;flex:0 0 10px;border-radius:50%;background:#0b91e8;box-shadow:0 0 0 5px #e2f3ff}
.location-copy{min-width:0}
.location-copy strong{display:block;font-size:14px;color:var(--ink,#24324a)}
.location-status{margin:5px 0 0;color:var(--muted);font-size:12px;line-height:1.45}
.airport-search{position:relative}
.airport-search input{width:100%;min-height:48px;padding:0 15px;border:1px solid var(--line);border-radius:13px;background:#fff;color:var(--ink,#24324a);font:inherit;outline:none}
.airport-search input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,132,224,.12)}
.airport-results{position:absolute;z-index:30;top:calc(100% + 7px);left:0;right:0;display:none;max-height:270px;overflow-y:auto;padding:6px;background:#fff;border:1px solid var(--line);border-radius:13px;box-shadow:0 12px 30px rgba(25,55,90,.14)}
.airport-results.is-open{display:block}
.airport-option{display:flex;width:100%;align-items:center;gap:11px;padding:10px;border:0;border-radius:9px;background:#fff;text-align:left;cursor:pointer;color:var(--ink,#24324a)}
.airport-option:hover,.airport-option:focus{background:var(--blue-soft,#eef7ff);outline:none}
.airport-code{flex:0 0 43px;font-size:12px;font-weight:800;color:var(--blue)}
.airport-name{font-size:13px;line-height:1.35}
.destination-controls{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:12px}
.route-submit{min-height:48px;white-space:nowrap;padding-inline:24px;align-self:stretch}
.routeform .btn.secondary{min-height:44px;border-radius:12px;font-size:14px;font-weight:700}
.route-note{margin-top:20px}
.route-note{margin-top:16px;color:var(--muted);font-size:13px;line-height:1.5}
.route-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:18px}
.route-stat{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;box-shadow:var(--shadow)}
.route-stat .k{font-size:12px;color:var(--muted);font-weight:600}
.route-stat .v{font-size:19px;font-weight:800;color:var(--blue-deep)}
#route-status{margin-top:12px}
@media(max-width:900px){.routeform.routeform-location{grid-template-columns:1fr;gap:20px}.destination-controls{grid-template-columns:minmax(0,1fr) auto}}
@media(max-width:520px){.destination-controls{grid-template-columns:1fr}.route-submit{width:100%}}
</style>
@endsection

@section('content')
<div class="panel">
  <h1>Cari rute menuju bandara</h1>
  <p class="lead">Gunakan lokasi perangkat Anda sebagai titik awal, lalu cari bandara tujuan berdasarkan nama, kota, atau kode IATA.</p>

  <form class="routeform routeform-location" method="post" action="{{ route('route.location') }}" id="form-rute">
    @csrf
    <input type="hidden" name="mode" value="lokasi">
    <input type="hidden" name="lat" id="lokasi-lat" value="{{ $lokasiAsal['lat'] ?? '' }}">
    <input type="hidden" name="lon" id="lokasi-lon" value="{{ $lokasiAsal['lon'] ?? '' }}">
    <input type="hidden" name="ke" id="bandara-tujuan" value="{{ $ke }}">

    <div class="route-field">
      <label>Lokasi asal</label>
      <div class="location-box">
        <span class="location-dot" aria-hidden="true"></span>
        <div class="location-copy">
          <strong id="label-lokasi">{{ $lokasiAsal ? 'Lokasi berhasil ditemukan' : 'Lokasi perangkat saya' }}</strong>
          <p class="location-status" id="status-lokasi" role="status" aria-live="polite">{{ $lokasiAsal ? 'Lokasi siap digunakan sebagai titik awal.' : 'Tekan tombol di bawah untuk mengambil lokasi GPS Anda.' }}</p>
        </div>
      </div>
      <button class="btn secondary" type="button" id="gunakan-lokasi" style="margin-top:10px;width:100%">◎ Gunakan lokasi saya</button>
    </div>

    <div class="route-field">
      <label for="cari-bandara">Bandara tujuan</label>
      <div class="destination-controls">
        <div class="airport-search">
          <input type="search" id="cari-bandara" placeholder="Cari nama bandara, kota, atau kode IATA..." autocomplete="off" aria-autocomplete="list" aria-controls="daftar-bandara" aria-expanded="false" value="{{ $tujuan ? $tujuan['iata'].' - '.$tujuan['nama'] : '' }}">
          <div class="airport-results" id="daftar-bandara" role="listbox">
            @foreach($bandara as $b)
              <button class="airport-option" type="button" role="option" data-iata="{{ $b['iata'] }}" data-label="{{ $b['iata'] }} - {{ $b['nama'] }}">
                <span class="airport-code">{{ $b['iata'] }}</span>
                <span class="airport-name">{{ $b['nama'] }}</span>
              </button>
            @endforeach
          </div>
        </div>
        <button class="btn route-submit" type="submit">Cari rute <span aria-hidden="true">→</span></button>
      </div>
      <p class="location-status" id="status-tujuan">{{ $tujuan ? 'Tujuan: '.$tujuan['nama'] : 'Ketik untuk mencari, lalu pilih salah satu bandara.' }}</p>
    </div>
  </form>
  <p class="route-note">Rute dihitung dari lokasi GPS Anda menuju bandara tujuan menggunakan jalur jalan raya.</p>
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
  var statusLokasi = document.getElementById('status-lokasi');
  var labelLokasi = document.getElementById('label-lokasi');
  var lat = document.getElementById('lokasi-lat');
  var lon = document.getElementById('lokasi-lon');
  var input = document.getElementById('cari-bandara');
  var tujuan = document.getElementById('bandara-tujuan');
  var daftar = document.getElementById('daftar-bandara');
  var statusTujuan = document.getElementById('status-tujuan');
  var opsi = Array.prototype.slice.call(daftar.querySelectorAll('.airport-option'));

  function bukaDaftar() {
    daftar.classList.add('is-open');
    input.setAttribute('aria-expanded', 'true');
  }
  function tutupDaftar() {
    daftar.classList.remove('is-open');
    input.setAttribute('aria-expanded', 'false');
  }
  function filterBandara() {
    var q = input.value.trim().toLowerCase();
    var tampil = 0;
    opsi.forEach(function (item) {
      var cocok = !q || item.dataset.label.toLowerCase().includes(q) || item.dataset.iata.toLowerCase().includes(q);
      item.hidden = !cocok;
      if (cocok) tampil++;
    });
    bukaDaftar();
    if (!tampil) {
      daftar.innerHTML = '<p style="padding:10px;margin:0;color:#718096;font-size:13px">Bandara tidak ditemukan.</p>';
    } else if (!daftar.querySelector('.airport-option')) {
      daftar.innerHTML = '';
      opsi.forEach(function (item) { daftar.appendChild(item); });
      opsi.forEach(function (item) { item.hidden = !( !q || item.dataset.label.toLowerCase().includes(q) || item.dataset.iata.toLowerCase().includes(q)); });
    }
  }

  input.addEventListener('focus', filterBandara);
  input.addEventListener('input', function () {
    tujuan.value = '';
    statusTujuan.textContent = 'Pilih salah satu hasil pencarian bandara.';
    filterBandara();
  });
  opsi.forEach(function (item) {
    item.addEventListener('click', function () {
      tujuan.value = item.dataset.iata;
      input.value = item.dataset.label;
      statusTujuan.textContent = 'Tujuan dipilih: ' + item.dataset.label;
      tutupDaftar();
    });
  });
  document.addEventListener('click', function (event) {
    if (!event.target.closest('.airport-search')) tutupDaftar();
  });
  input.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') tutupDaftar();
    if (event.key === 'Enter') {
      var pertama = daftar.querySelector('.airport-option:not([hidden])');
      if (pertama && !tujuan.value) { event.preventDefault(); pertama.click(); }
    }
  });

  if (lat.value && lon.value) {
    statusLokasi.textContent = 'Lokasi GPS siap digunakan sebagai titik awal.';
    labelLokasi.textContent = 'Lokasi berhasil ditemukan';
  }

  tombol.addEventListener('click', function () {
    if (!navigator.geolocation) {
      statusLokasi.textContent = 'Browser tidak mendukung fitur lokasi.';
      return;
    }
    tombol.disabled = true;
    tombol.textContent = 'Mengambil lokasi...';
    statusLokasi.textContent = 'Meminta izin lokasi dari browser...';
    navigator.geolocation.getCurrentPosition(function (position) {
      lat.value = position.coords.latitude.toFixed(7);
      lon.value = position.coords.longitude.toFixed(7);
      labelLokasi.textContent = 'Lokasi berhasil ditemukan';
      statusLokasi.textContent = 'Lokasi GPS siap digunakan sebagai titik awal.';
      tombol.disabled = false;
      tombol.textContent = '✓ Perbarui lokasi saya';
    }, function (error) {
      var pesan = 'Lokasi tidak berhasil diambil.';
      if (error.code === error.PERMISSION_DENIED) pesan = 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini.';
      else if (error.code === error.POSITION_UNAVAILABLE) pesan = 'Informasi lokasi tidak tersedia. Periksa pengaturan GPS.';
      else if (error.code === error.TIMEOUT) pesan = 'Waktu pencarian lokasi habis. Silakan coba lagi.';
      statusLokasi.textContent = pesan;
      tombol.disabled = false;
      tombol.textContent = 'Coba gunakan lokasi saya lagi';
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
  });

  form.addEventListener('submit', function (event) {
    if (!lat.value || !lon.value) {
      event.preventDefault();
      statusLokasi.textContent = 'Ambil lokasi Anda terlebih dahulu sebelum mencari rute.';
      tombol.focus();
      return;
    }
    if (!tujuan.value) {
      event.preventDefault();
      statusTujuan.textContent = 'Pilih bandara tujuan dari daftar pencarian terlebih dahulu.';
      input.focus();
    }
  });
})();
</script>
@endpush
@endsection
