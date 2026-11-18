@php
    $lokasi = collect([$b['wilayah'] ?? null, $b['provinsi'] ?? null, $b['negara']])->filter()->implode(', ');
    $art = [
        'iata' => $iata,
        'nama' => $b['nama'],
        'lat' => $b['lat'] ?? null,
        'lon' => $b['long'] ?? null,
        'runway' => $b['runway'] ?? null,
        'penumpang' => $b['penumpang'] ?? null,
    ];
    $punyaPeta = isset($b['lat'], $b['long']);
    $ada = fn (string $k): bool => isset($b[$k]) && $b[$k] !== '';
    $angka = fn ($n): string => number_format((float) $n, 0, ',', '.');
    $desimal = fn ($n): string => rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
    $adaFakta = $punyaPeta || collect(['icao', 'status', 'kategori', 'operator', 'penumpang', 'penumpang_puncak', 'tahun'])->contains(fn ($k) => ! empty($b[$k]));
    $adaLandasan = collect(['runway', 'landasan_lebar', 'landasan_jumlah', 'landasan_nama', 'permukaan'])->contains(fn ($k) => $ada($k));
    $adaLokasi = collect(['elevasi', 'jarak_kota', 'jarak_terdekat'])->contains(fn ($k) => $ada($k)) || ! empty($b['terdekat']);
    $adaKonektivitas = collect(['jml_maskapai', 'tujuan', 'tujuan_asean', 'tujuan_luar'])->contains(fn ($k) => $ada($k));
    $deskripsi = 'Informasi bandara '.$b['nama'].($lokasi !== '' ? ' di '.$lokasi : '').'. Lihat kode IATA/ICAO, koordinat, landasan pacu, jarak, rute, maskapai, dan data terkait.';
    $jsonld = [
        '@context' => 'https://schema.org',
        '@type' => 'Airport',
        'name' => $b['nama'],
        'iataCode' => $iata,
        'url' => route('bandara.show', $iata),
    ];
    if (! empty($b['icao'])) { $jsonld['icaoCode'] = $b['icao']; }
    if ($ada('nama_lokal')) { $jsonld['alternateName'] = $b['nama_lokal']; }
    if (! empty($b['negara'])) { $jsonld['address'] = ['@type' => 'PostalAddress', 'addressCountry' => $b['negara'], 'addressLocality' => $b['wilayah'] ?? null, 'addressRegion' => $b['provinsi'] ?? null]; }
    if ($punyaPeta) {
        $jsonld['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $b['lat'], 'longitude' => (float) $b['long']];
        if ($ada('elevasi')) { $jsonld['geo']['elevation'] = (float) $b['elevasi']; }
    }
    $sama = collect([$b['sameAs'] ?? null, $b['wikidata'] ?? null])->filter()->unique()->values()->all();
    if ($sama !== []) { $jsonld['sameAs'] = count($sama) === 1 ? $sama[0] : $sama; }
    if (! empty($b['website'])) { $jsonld['url'] = $b['website']; $jsonld['mainEntityOfPage'] = route('bandara.show', $iata); }
    $seo = [
        'title' => $b['nama'].' ('.$iata.') — BANDARA ASEAN',
        'description' => $deskripsi,
        'canonical' => route('bandara.show', $iata),
        'type' => 'article',
        'image' => $foto['url'] ?? null,
        'jsonld' => $jsonld,
    ];
@endphp
@extends('layouts.app')
@section('title', $b['nama'] . ' (' . $iata . ') — BANDARA ASEAN')

@section('head')
@if($punyaPeta)
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
.navtools{margin-top:24px;padding:16px 18px;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:var(--shadow)}
.navbtns{display:flex;flex-wrap:wrap;gap:10px}
.navbtn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:42px;padding:0 18px;border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--blue-dark);font:700 14px 'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:border-color .15s,background .15s,color .15s}
.navbtn:hover:not(:disabled){border-color:var(--blue);background:var(--blue-soft)}
.navbtn.primary{background:var(--blue);border-color:var(--blue);color:#fff}
.navbtn.primary:hover:not(:disabled){background:var(--blue-dark);border-color:var(--blue-dark)}
.navbtn:disabled{opacity:.5;cursor:not-allowed}
.navstat{margin:12px 0 0;color:var(--muted);font-size:14px}
.navstat.err{color:#c0392b}
.navsum{display:flex;flex-wrap:wrap;gap:6px 28px;margin-top:12px}
.navsum[hidden],.navsteps[hidden]{display:none}
.navsum b{display:block;font-size:22px;line-height:1.2}
.navsum span{color:var(--muted);font-size:13px}
.navsteps{margin:16px 0 0;padding:0;list-style:none;border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#fff}
.navsteps li{display:flex;justify-content:space-between;gap:12px;padding:11px 16px;border-bottom:1px solid var(--line);font-size:14.5px;cursor:pointer}
.navsteps li:last-child{border-bottom:0}
.navsteps li:hover{background:var(--blue-soft)}
.navsteps small{color:var(--muted);white-space:nowrap}
.me-dot span{display:block;width:18px;height:18px;border-radius:50%;background:#1a73e8;border:3px solid #fff;box-shadow:0 0 0 0 rgba(26,115,232,.5);animation:mepulse 2s infinite}
@keyframes mepulse{70%{box-shadow:0 0 0 14px rgba(26,115,232,0)}100%{box-shadow:0 0 0 0 rgba(26,115,232,0)}}
</style>
@endif
@endsection

@section('content')
<a class="back" href="{{ route('bandara.index') }}">&larr; Semua bandara</a>

<article vocab="https://schema.org/" typeof="Airport">
  <div class="entity">
    <span class="code" property="iataCode">{{ $iata }}</span>
    <h1 property="name">{{ $b['nama'] }}</h1>
    <p property="address" typeof="PostalAddress">
      @if(!empty($b['wilayah']))<span property="addressLocality">{{ $b['wilayah'] }}</span>, @endif
      @if(!empty($b['provinsi']))<span property="addressRegion">{{ $b['provinsi'] }}</span>, @endif
      <span property="addressCountry">{{ $b['negara'] ?? 'Lokasi belum tercatat' }}</span>
    </p>
    @if($ada('nama_lokal'))<p style="margin-top:2px">Nama lokal: <span property="alternateName">{{ $b['nama_lokal'] }}</span></p>@endif
    <div class="res-meta" style="margin-top:8px">
      @if($airport->in_asean)<span class="pill asean">ASEAN</span>@endif
      @if($airport->category)<span class="pill">{{ $airport->category }}</span>@endif
      @if($airport->number_of_destinations)<span class="pill">{{ number_format($airport->number_of_destinations, 0, ',', '.') }} tujuan</span>@endif
      <span class="pill">{{ number_format($airport->departure_routes_count, 0, ',', '.') }} rute keluar</span>
      <span class="pill">{{ number_format($airport->arrival_routes_count, 0, ',', '.') }} rute masuk</span>
    </div>
  </div>

  <div class="visual">
    @if($foto)
      <img src="{{ $foto['url'] }}" alt="Foto {{ $b['nama'] }}">
      <a class="credit" href="{{ $foto['page'] }}" target="_blank" rel="noopener">Foto: Wikipedia</a>
    @elseif($punyaPeta)
      <div id="hero-sat" role="img" aria-label="Citra satelit {{ $b['nama'] }}"></div>
    @else
      @include('partials.art', ['a' => $art, 'mode' => 'hero'])
    @endif
  </div>

  @if($adaFakta)
  <div class="facts">
    @if(!empty($b['icao']))<div class="fact"><div class="k">Kode ICAO</div><div class="v" property="icaoCode">{{ $b['icao'] }}</div></div>@endif
    @if(!empty($b['status']))<div class="fact"><div class="k">Status</div><div class="v">{{ $b['status'] }}</div></div>@endif
    @if(!empty($b['kategori']))<div class="fact"><div class="k">Kategori</div><div class="v">{{ $b['kategori'] }}</div></div>@endif
    @if(!empty($b['operator']))<div class="fact"><div class="k">Pengelola</div><div class="v">{{ $b['operator'] }}</div></div>@endif
    @if(!empty($b['penumpang']))<div class="fact"><div class="k">Penumpang per tahun</div><div class="v">{{ number_format((float) $b['penumpang'], 0, ',', '.') }}</div></div>@endif
    @if(!empty($b['penumpang_puncak']))<div class="fact"><div class="k">Rekor penumpang per tahun</div><div class="v">{{ $angka($b['penumpang_puncak']) }}</div></div>@endif
    @if(!empty($b['tahun']))<div class="fact"><div class="k">Tahun dibuka</div><div class="v">{{ $b['tahun'] }}</div></div>@endif
    @if($punyaPeta)<div class="fact"><div class="k">Koordinat</div><div class="v">{{ number_format((float) $b['lat'], 4) }}, {{ number_format((float) $b['long'], 4) }}</div></div>@endif
  </div>
  @endif

  @if($adaLandasan)
  <h2 class="section">Landasan pacu</h2>
  <div class="facts compact">
    @if($ada('runway'))<div class="fact"><div class="k">Landasan terpanjang</div><div class="v">{{ $angka($b['runway']) }} m</div></div>@endif
    @if($ada('landasan_lebar'))<div class="fact"><div class="k">Lebar landasan</div><div class="v">{{ $angka($b['landasan_lebar']) }} m</div></div>@endif
    @if($ada('landasan_jumlah'))<div class="fact"><div class="k">Jumlah landasan</div><div class="v">{{ $b['landasan_jumlah'] }}</div></div>@endif
    @if($ada('landasan_nama'))<div class="fact"><div class="k">Penamaan landasan</div><div class="v">{{ $b['landasan_nama'] }}</div></div>@endif
    @if($ada('permukaan'))<div class="fact"><div class="k">Permukaan</div><div class="v">{{ $b['permukaan'] }}</div></div>@endif
  </div>
  @endif

  @if($adaLokasi)
  <h2 class="section">Lokasi dan jarak</h2>
  <div class="facts compact">
    @if($ada('elevasi'))<div class="fact"><div class="k">Elevasi</div><div class="v">{{ $angka($b['elevasi']) }} m di atas permukaan laut</div></div>@endif
    @if($ada('jarak_kota'))<div class="fact"><div class="k">Jarak ke pusat kota</div><div class="v">{{ $desimal($b['jarak_kota']) }} km</div></div>@endif
    @if(!empty($b['terdekat']))
      <div class="fact"><div class="k">Bandara terdekat</div><div class="v">
        @if(!empty($b['terdekat']['iata']))
          <a class="link" href="{{ route('bandara.show', $b['terdekat']['iata']) }}">{{ $b['terdekat']['nama'] }} ({{ $b['terdekat']['iata'] }})</a>
        @else
          {{ $b['terdekat']['nama'] }}
        @endif
      </div></div>
    @endif
    @if($ada('jarak_terdekat'))<div class="fact"><div class="k">Jarak ke bandara terdekat</div><div class="v">{{ $desimal($b['jarak_terdekat']) }} km</div></div>@endif
  </div>
  @endif

  @if($adaKonektivitas)
  <h2 class="section">Konektivitas</h2>
  <div class="facts compact">
    @if($ada('jml_maskapai'))<div class="fact"><div class="k">Jumlah maskapai</div><div class="v">{{ $angka($b['jml_maskapai']) }}</div></div>@endif
    @if($ada('tujuan'))<div class="fact"><div class="k">Total tujuan</div><div class="v">{{ $angka($b['tujuan']) }}</div></div>@endif
    @if($ada('tujuan_asean'))<div class="fact"><div class="k">Tujuan di ASEAN</div><div class="v">{{ $angka($b['tujuan_asean']) }}</div></div>@endif
    @if($ada('tujuan_luar'))<div class="fact"><div class="k">Tujuan di luar ASEAN</div><div class="v">{{ $angka($b['tujuan_luar']) }}</div></div>@endif
  </div>
  @endif

  @if($punyaPeta)
    <span property="geo" typeof="GeoCoordinates">
      <meta property="latitude" content="{{ $b['lat'] }}">
      <meta property="longitude" content="{{ $b['long'] }}">
    </span>
  @endif

  <div class="linkrow">
    @if($punyaPeta)
      <a class="link" href="{{ route('route.index', ['dari' => $iata]) }}">Lihat rute dari {{ $iata }} di peta</a>
    @endif
    @if(!empty($b['website']) && preg_match('#^https?://#i', $b['website']))
      <a class="link" href="{{ $b['website'] }}" target="_blank" rel="noopener nofollow">Website resmi</a>
    @endif
    @if(!empty($b['sameAs']))
      <link property="sameAs" href="{{ $b['sameAs'] }}">
      <a class="link" href="{{ $b['sameAs'] }}" target="_blank" rel="noopener">
        Lihat di {{ str_contains($b['sameAs'], 'wikidata') ? 'Wikidata' : 'DBpedia' }}
      </a>
    @endif
    @if($ada('wikidata') && preg_match('#^https?://#i', $b['wikidata']) && $b['wikidata'] !== ($b['sameAs'] ?? null))
      <a class="link" href="{{ $b['wikidata'] }}" target="_blank" rel="noopener">Lihat di Wikidata</a>
    @endif
    <a class="link" href="{{ route('search', ['q' => $iata]) }}">Cari dokumen lain tentang {{ $iata }}</a>
  </div>

  @if($punyaPeta)
    <div class="navtools">
      <div class="navbtns">
        <button type="button" class="navbtn" id="btn-lacak">Lacak lokasi saya</button>
        <button type="button" class="navbtn" id="btn-pilih">Pilih titik awal di peta</button>
        <button type="button" class="navbtn primary" id="btn-rute" disabled>Rute ke bandara</button>
        <a class="navbtn" id="btn-gmaps" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&amp;destination={{ (float) $b['lat'] }},{{ (float) $b['long'] }}&amp;travelmode=driving">Buka di Google Maps</a>
      </div>
      <p class="navstat" id="nav-status" aria-live="polite">Klik “Lacak lokasi saya” untuk melihat posisimu di peta, lalu “Rute ke bandara” untuk petunjuk arah.</p>
      <div class="navsum" id="nav-ringkas" hidden>
        <div><b id="nav-jarak">-</b><span>jarak</span></div>
        <div><b id="nav-waktu">-</b><span>perkiraan waktu berkendara</span></div>
      </div>
    </div>
    <div class="mapbox" style="margin-top:14px"><div id="peta" role="region" aria-label="Peta lokasi {{ $b['nama'] }}"></div></div>
    <ol class="navsteps" id="nav-langkah" hidden></ol>
  @endif

  @if($dbpedia)
    <h2 class="section">Tentang bandara</h2>
    <p class="about" property="description">{{ $dbpedia['teks'] }}</p>
    <p class="about-src">Sumber: <a class="link" href="{{ $dbpedia['halaman'] }}" target="_blank" rel="noopener nofollow">DBpedia</a>@if($dbpedia['bahasa'] === 'en') (bahasa Inggris)@endif, diambil langsung lewat SPARQL endpoint DBpedia.</p>
  @endif

  @if($airport->hubAirlines->isNotEmpty())
    <h2 class="section">Maskapai dengan hub di sini</h2>
    <div class="linkrow">
      @foreach($airport->hubAirlines as $maskapai)
        <a class="link" href="{{ route('data.airlines', ['q' => $maskapai->label]) }}">{{ $maskapai->label }}</a>
      @endforeach
    </div>
  @endif

  @php
    $hubId = $airport->hubAirlines->pluck('id');
    $beroperasi = $airport->airlines->reject(fn ($m) => $hubId->contains($m->id))->sortBy('label');
  @endphp
  @if($beroperasi->isNotEmpty())
    <h2 class="section">Maskapai yang beroperasi di sini</h2>
    <div class="linkrow">
      @foreach($beroperasi as $maskapai)
        <a class="link" href="{{ route('data.airlines', ['q' => $maskapai->label]) }}">{{ $maskapai->label }}</a>
      @endforeach
    </div>
  @endif
</article>

<h2 class="section">Rute langsung dari {{ $iata }}</h2>
@if ($rute->isEmpty())
  <div class="state">Data rute langsung untuk bandara ini belum tersedia. Coba <a class="link" href="{{ route('route.index', ['dari' => $iata]) }}">halaman rute</a> untuk mencari kemungkinan transit.</div>
@else
  <div class="scroll">
    <table>
      <thead><tr><th>Tujuan</th><th>Maskapai</th>@if($punyaPeta)<th></th>@endif</tr></thead>
      <tbody>
        @foreach ($rute as $kode => $items)
          <tr>
            <td><a class="link" href="{{ route('bandara.show', $kode) }}">{{ $items->first()['dst_nama'] }}</a> ({{ $kode }})</td>
            <td>{{ $items->pluck('maskapai')->filter()->unique()->implode(', ') }}</td>
            @if($punyaPeta)<td><a class="link" href="{{ route('route.index', ['dari' => $iata, 'ke' => $kode]) }}">Peta</a></td>@endif
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<h2 class="section">Rute langsung ke {{ $iata }}</h2>
@if ($masuk->isEmpty())
  <div class="state">Data rute masuk ke bandara ini belum tersedia.</div>
@else
  <div class="scroll">
    <table>
      <thead><tr><th>Asal</th><th>Maskapai</th></tr></thead>
      <tbody>
        @foreach ($masuk as $kode => $items)
          <tr>
            <td><a class="link" href="{{ route('bandara.show', $kode) }}">{{ $items->first()['src_nama'] }}</a> ({{ $kode }})</td>
            <td>{{ $items->pluck('maskapai')->filter()->unique()->implode(', ') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<h2 class="section">Jadwal keberangkatan</h2>
@if(count($jadwal))
  <div class="scroll">
    <table>
      <thead><tr><th>Penerbangan</th><th>Maskapai</th><th>Tujuan</th><th>Jadwal</th><th>Status</th></tr></thead>
      <tbody>
        @foreach($jadwal as $f)
          <tr>
            <td>{{ $f['flight']['iata'] ?? '-' }}</td>
            <td>{{ $f['airline']['name'] ?? '-' }}</td>
            <td>{{ $f['arrival']['airport'] ?? '-' }}</td>
            <td>{{ isset($f['departure']['scheduled']) ? \Carbon\Carbon::parse($f['departure']['scheduled'])->format('d M H:i') : '-' }}</td>
            <td><span class="status s-{{ $f['flight_status'] ?? 'scheduled' }}">{{ $f['flight_status'] ?? '-' }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@else
  <div class="state">Jadwal langsung belum tersedia. Isi <code>AVIATIONSTACK_KEY</code> di file .env, lalu muat ulang halaman. Data utama bandara tetap tampil dari database.</div>
@endif
@endsection

@if($punyaPeta)
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function () {
  var titik = [{{ (float) $b['lat'] }}, {{ (float) $b['long'] }}];
  var kreditSat = '{{ \App\Support\Satellite::KREDIT }}';
  var urlSat = '{{ \App\Support\Satellite::URL }}';

  function satelit() { return L.tileLayer(urlSat, { maxZoom: 18, attribution: kreditSat }); }
  function jalan() { return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }); }

  var hero = document.getElementById('hero-sat');
  if (hero) {
    var h = L.map(hero, { center: titik, zoom: 14, scrollWheelZoom: false, layers: [satelit()] });
    L.marker(titik).addTo(h);
  }

  var kotak = document.getElementById('peta');
  if (!kotak) return;

  var namaBandara = @json($b['nama']);
  var jl = jalan();
  var m = L.map(kotak, { center: titik, zoom: 13, scrollWheelZoom: false, layers: [jl] });
  L.marker(titik).addTo(m).bindPopup(namaBandara);
  L.control.layers({ 'Peta': jl, 'Satelit': satelit() }, null, { collapsed: false }).addTo(m);

  // ---------- lacak lokasi + rute (gaya Google Maps) ----------
  var $ = function (id) { return document.getElementById(id); };
  var btnLacak = $('btn-lacak'), btnRute = $('btn-rute'), gmaps = $('btn-gmaps');
  var status = $('nav-status'), ringkas = $('nav-ringkas'), langkah = $('nav-langkah');

  var posisi = null, watchId = null, penanda = null, lingkar = null, garis = null;
  var aktif = false, memuat = false, asalRute = null, waktuRute = 0;
  var ikonSaya = L.divIcon({ className: 'me-dot', html: '<span></span>', iconSize: [18, 18], iconAnchor: [9, 9] });

  function setStatus(teks, galat) {
    status.textContent = teks;
    status.classList.toggle('err', !!galat);
  }

  function fmtJarak(mtr) {
    if (mtr < 1000) return (Math.round(mtr / 10) * 10) + ' m';
    return (mtr / 1000).toFixed(mtr < 10000 ? 1 : 0).replace('.', ',') + ' km';
  }

  function fmtWaktu(detik) {
    var mnt = Math.max(1, Math.round(detik / 60));
    if (mnt < 60) return mnt + ' mnt';
    return Math.floor(mnt / 60) + ' j ' + (mnt % 60) + ' mnt';
  }

  var arah = {
    'left': 'kiri', 'right': 'kanan', 'slight left': 'sedikit ke kiri', 'slight right': 'sedikit ke kanan',
    'sharp left': 'tajam ke kiri', 'sharp right': 'tajam ke kanan'
  };

  function teksLangkah(s) {
    var mv = s.maneuver || {};
    var d = arah[mv.modifier] || '';
    var ke = s.name ? ' ke ' + s.name : '';
    var di = s.name ? ' di ' + s.name : '';
    switch (mv.type) {
      case 'depart': return 'Mulai perjalanan' + di;
      case 'arrive': return 'Tiba di tujuan';
      case 'turn':
        if (mv.modifier === 'straight') return 'Lurus' + di;
        if (mv.modifier === 'uturn') return 'Putar balik' + di;
        return 'Belok ' + d + ke;
      case 'merge': return 'Bergabung' + ke;
      case 'on ramp': return 'Masuk jalur akses' + ke;
      case 'off ramp': return 'Keluar lewat jalur' + ke;
      case 'fork': return 'Ambil percabangan ' + (d || 'lurus') + ke;
      case 'end of road': return 'Di ujung jalan, belok ' + (d || 'sesuai arah') + ke;
      case 'roundabout':
      case 'rotary':
      case 'roundabout turn':
        return 'Masuk bundaran' + (mv.exit ? ', keluar di exit ke-' + mv.exit : '') + ke;
      case 'exit roundabout':
      case 'exit rotary':
        return 'Keluar dari bundaran' + ke;
      default: return 'Lanjutkan' + di;
    }
  }

  function gambarPosisi(p) {
    var ll = [p.coords.latitude, p.coords.longitude];
    if (!penanda) {
      penanda = L.marker(ll, { icon: ikonSaya, keyboard: false, zIndexOffset: 1000 }).addTo(m).bindPopup('Lokasimu sekarang');
      lingkar = L.circle(ll, { radius: p.coords.accuracy || 0, weight: 1, color: '#1a73e8', fillColor: '#1a73e8', fillOpacity: 0.1 }).addTo(m);
    } else {
      penanda.setLatLng(ll);
      lingkar.setLatLng(ll).setRadius(p.coords.accuracy || 0);
    }
    return ll;
  }

  function bersihkanRute() {
    if (garis) { m.removeLayer(garis); garis = null; }
    ringkas.hidden = true;
    langkah.hidden = true;
    langkah.textContent = '';
    asalRute = null;
  }

  function hitungRute(diam) {
    if (!posisi || memuat) return;
    memuat = true;
    btnRute.disabled = true;
    if (!diam) setStatus('Mencari rute terbaik...');

    var url = 'https://router.project-osrm.org/route/v1/driving/' +
      posisi[1] + ',' + posisi[0] + ';' + titik[1] + ',' + titik[0] +
      '?overview=full&geometries=geojson&steps=true';
    var ctl = ('AbortController' in window) ? new AbortController() : null;
    var timer = ctl ? setTimeout(function () { ctl.abort(); }, 15000) : null;

    fetch(url, ctl ? { signal: ctl.signal } : {})
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.code !== 'Ok' || !d.routes || !d.routes.length) {
          bersihkanRute();
          aktif = false;
          setStatus('Tidak ada rute jalan darat dari lokasimu ke bandara ini (mungkin beda pulau atau negara). Coba tombol “Buka di Google Maps”.', true);
          return;
        }
        var r = d.routes[0];
        var titikGaris = r.geometry.coordinates.map(function (c) { return [c[1], c[0]]; });
        if (garis) m.removeLayer(garis);
        garis = L.polyline(titikGaris, { color: '#1a73e8', weight: 6, opacity: 0.85 }).addTo(m);
        if (!diam) m.fitBounds(garis.getBounds(), { padding: [40, 40] });

        $('nav-jarak').textContent = fmtJarak(r.distance);
        $('nav-waktu').textContent = fmtWaktu(r.duration);
        ringkas.hidden = false;

        langkah.textContent = '';
        (r.legs[0].steps || []).forEach(function (st) {
          var li = document.createElement('li');
          var t = document.createElement('span');
          t.textContent = teksLangkah(st);
          li.appendChild(t);
          if (st.distance > 0) {
            var sm = document.createElement('small');
            sm.textContent = fmtJarak(st.distance);
            li.appendChild(sm);
          }
          li.addEventListener('click', function () {
            var lok = st.maneuver && st.maneuver.location;
            if (lok) m.setView([lok[1], lok[0]], 17);
          });
          langkah.appendChild(li);
        });
        langkah.hidden = false;

        aktif = true;
        asalRute = posisi.slice();
        waktuRute = Date.now();
        setStatus('Rute diperbarui otomatis kalau kamu bergerak jauh dari jalur. Waktu perkiraan belum menghitung kemacetan.');
      })
      .catch(function () {
        setStatus('Gagal mengambil rute. Periksa koneksi internet, atau coba tombol “Buka di Google Maps”.', true);
      })
      .then(function () {
        if (timer) clearTimeout(timer);
        memuat = false;
        btnRute.disabled = !posisi;
      });
  }

  function saatPosisi(p) {
    var pertama = !posisi;
    posisi = gambarPosisi(p);
    btnRute.disabled = memuat;
    gmaps.href = 'https://www.google.com/maps/dir/?api=1&origin=' + posisi[0] + ',' + posisi[1] +
      '&destination=' + titik[0] + ',' + titik[1] + '&travelmode=driving';

    if (pertama) {
      m.fitBounds([posisi, titik], { padding: [50, 50], maxZoom: 15 });
      setStatus('Lokasimu ditemukan (akurasi sekitar ' + Math.round(p.coords.accuracy || 0) + ' m). Klik “Rute ke bandara”.');
    }

    // ikut bergerak: hitung ulang rute bila sudah menyimpang >150 m (maks. tiap 15 detik)
    if (aktif && asalRute && Date.now() - waktuRute > 15000 &&
        L.latLng(posisi).distanceTo(L.latLng(asalRute)) > 150) {
      hitungRute(true);
    }
  }

  function saatGalat(e) {
    var pesan = {
      1: 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini di browser, lalu coba lagi.',
      2: 'Lokasimu belum bisa ditentukan. Pastikan GPS atau layanan lokasi menyala.',
      3: 'Pencarian lokasi terlalu lama. Coba lagi.'
    };
    setStatus((pesan[e.code] || 'Gagal membaca lokasi.') + ' (kode ' + e.code + (e.message ? ': ' + e.message : '') + '). Alternatif: klik “Pilih titik awal di peta” lalu klik posisimu di peta.', true);
    berhenti();
  }

  function berhenti() {
    if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
    if (penanda) { m.removeLayer(penanda); penanda = null; }
    if (lingkar) { m.removeLayer(lingkar); lingkar = null; }
    posisi = null;
    aktif = false;
    bersihkanRute();
    btnRute.disabled = true;
    btnLacak.textContent = 'Lacak lokasi saya';
  }

  btnLacak.addEventListener('click', function () {
    if (watchId !== null) {
      berhenti();
      setStatus('Pelacakan dihentikan.');
      return;
    }
    if (!('geolocation' in navigator)) {
      setStatus('Browser ini tidak mendukung pelacakan lokasi.', true);
      return;
    }
    if (window.isSecureContext === false) {
      setStatus('Pelacakan lokasi hanya jalan di HTTPS atau http://localhost. Buka situs lewat salah satunya.', true);
      return;
    }
    setStatus('Meminta izin lokasi...');
    btnLacak.textContent = 'Berhenti melacak';
    watchId = navigator.geolocation.watchPosition(saatPosisi, saatGalat, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
  });

  btnRute.addEventListener('click', function () { hitungRute(false); });

  // ---------- cadangan tanpa GPS: klik peta untuk menentukan titik awal ----------
  var btnPilih = $('btn-pilih'), memilih = false;

  function aturPilih(nyala) {
    memilih = nyala;
    kotak.style.cursor = nyala ? 'crosshair' : '';
    btnPilih.textContent = nyala ? 'Batal memilih' : 'Pilih titik awal di peta';
    if (nyala) setStatus('Klik di peta pada posisimu (atau titik awal perjalananmu).');
  }

  btnPilih.addEventListener('click', function () { aturPilih(!memilih); });

  m.on('click', function (e) {
    if (!memilih) return;
    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId);
      watchId = null;
      btnLacak.textContent = 'Lacak lokasi saya';
    }
    var ll = [e.latlng.lat, e.latlng.lng];
    posisi = gambarPosisi({ coords: { latitude: ll[0], longitude: ll[1], accuracy: 0 } });
    gmaps.href = 'https://www.google.com/maps/dir/?api=1&origin=' + ll[0] + ',' + ll[1] +
      '&destination=' + titik[0] + ',' + titik[1] + '&travelmode=driving';
    bersihkanRute();
    aktif = false;
    btnRute.disabled = false;
    aturPilih(false);
    setStatus('Titik awal dipilih. Klik “Rute ke bandara”.');
  });
})();
</script>
@endpush
@endif
