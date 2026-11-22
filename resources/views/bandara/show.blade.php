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
    $adaRute = $rute->isNotEmpty() || $masuk->isNotEmpty();
    $adaKonektivitas = $adaRute || (int) ($b['tujuan'] ?? 0) > 0 || (int) ($b['jml_maskapai'] ?? 0) > 0;
    $kpi = collect([
        ['n' => ! empty($b['penumpang']) ? $angka($b['penumpang']) : null, 'l' => 'Penumpang per tahun'],
        ['n' => $ada('runway') ? $angka($b['runway']).' m' : null, 'l' => 'Landasan terpanjang'],
        ['n' => $ada('elevasi') ? $angka($b['elevasi']).' m' : null, 'l' => 'Elevasi (di atas laut)'],
        ['n' => (int) ($b['tujuan'] ?? 0) > 0 ? $angka($b['tujuan']) : null, 'l' => 'Kota tujuan'],
        ['n' => ! empty($b['tahun']) ? (string) $b['tahun'] : null, 'l' => 'Tahun dibuka'],
    ])->filter(fn ($k) => $k['n'] !== null)->take(4)->values();
    $terdekat = null;
    if (! empty($b['terdekat'])) {
        $terdekat = ! empty($b['terdekat']['iata'])
            ? new \Illuminate\Support\HtmlString('<a class="link" href="'.e(route('bandara.show', $b['terdekat']['iata'])).'">'.e($b['terdekat']['nama']).' ('.e($b['terdekat']['iata']).')</a>')
            : $b['terdekat']['nama'];
    }
    $kartu = [
        'Informasi umum' => [
            'Kode IATA' => $iata,
            'Kode ICAO' => $b['icao'] ?? null,
            'Status' => $b['status'] ?? null,
            'Kategori' => $b['kategori'] ?? null,
            'Pengelola' => $b['operator'] ?? null,
            'Tahun dibuka' => ! empty($b['tahun']) ? (string) $b['tahun'] : null,
            'Penumpang per tahun' => ! empty($b['penumpang']) ? $angka($b['penumpang']) : null,
            'Rekor penumpang per tahun' => ! empty($b['penumpang_puncak']) ? $angka($b['penumpang_puncak']) : null,
            'Koordinat' => $punyaPeta ? number_format((float) $b['lat'], 4).', '.number_format((float) $b['long'], 4) : null,
        ],
        'Landasan pacu' => [
            'Landasan terpanjang' => $ada('runway') ? $angka($b['runway']).' m' : null,
            'Lebar landasan' => $ada('landasan_lebar') ? $angka($b['landasan_lebar']).' m' : null,
            'Jumlah landasan' => $ada('landasan_jumlah') ? (string) $b['landasan_jumlah'] : null,
            'Penamaan landasan' => $ada('landasan_nama') ? $b['landasan_nama'] : null,
            'Permukaan' => $ada('permukaan') ? $b['permukaan'] : null,
        ],
        'Lokasi dan jarak' => [
            'Elevasi' => $ada('elevasi') ? $angka($b['elevasi']).' m di atas laut' : null,
            'Jarak ke pusat kota' => $ada('jarak_kota') ? $desimal($b['jarak_kota']).' km' : null,
            'Bandara terdekat' => $terdekat,
            'Jarak ke bandara terdekat' => $ada('jarak_terdekat') ? $desimal($b['jarak_terdekat']).' km' : null,
        ],
        'Konektivitas' => $adaKonektivitas ? [
            'Jumlah maskapai' => $ada('jml_maskapai') ? $angka($b['jml_maskapai']) : null,
            'Total tujuan' => $ada('tujuan') ? $angka($b['tujuan']) : null,
            'Tujuan di ASEAN' => $ada('tujuan_asean') ? $angka($b['tujuan_asean']) : null,
            'Tujuan di luar ASEAN' => $ada('tujuan_luar') ? $angka($b['tujuan_luar']) : null,
        ] : [],
    ];
    $kartu = collect($kartu)
        ->map(fn (array $baris): array => array_filter($baris, fn ($v) => $v !== null && $v !== ''))
        ->filter(fn (array $baris): bool => $baris !== []);
    $crumbs = collect([
        ['t' => 'Beranda', 'u' => route('home')],
        ['t' => 'Bandara', 'u' => route('bandara.index')],
        ! empty($b['negara']) ? ['t' => $b['negara'], 'u' => route('bandara.index', ['negara' => $b['negara']])] : null,
        ! empty($b['provinsi']) ? ['t' => $b['provinsi'], 'u' => route('search', ['q' => $b['provinsi']])] : null,
        ! empty($b['wilayah']) && ($b['wilayah'] ?? null) !== ($b['provinsi'] ?? null) ? ['t' => $b['wilayah'], 'u' => route('search', ['q' => $b['wilayah']])] : null,
        ['t' => $b['nama'].' ('.$iata.')', 'u' => null],
    ])->filter()->values();
    $crumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $crumbs->map(fn (array $c, int $i): array => array_filter([
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $c['t'],
            'item' => $c['u'],
        ]))->values()->all(),
    ];
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
<style>
.jump{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0 0}
.jump a{display:inline-flex;align-items:center;min-height:36px;padding:0 16px;border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--blue-dark);font-weight:700;font-size:14px;transition:border-color .15s,background .15s}
.jump a:hover{border-color:var(--blue);background:var(--blue-soft)}
.section[id]{scroll-margin-top:84px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
.kpi{padding:16px 18px;border:1px solid var(--line);border-radius:18px;background:linear-gradient(180deg,#fff,var(--blue-soft));box-shadow:var(--shadow)}
.kpi .n{font-size:clamp(22px,3vw,28px);font-weight:800;line-height:1.15;color:var(--blue-deep);letter-spacing:-.01em}
.kpi .l{margin-top:4px;color:var(--muted);font-size:13px}
.info-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px}
.info-card{padding:4px 20px 8px;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:var(--shadow)}
.info-card h3{margin:14px 0 4px;font-size:15px;font-weight:800;color:var(--blue-deep)}
.info-card dl{margin:0}
.info-card dl>div{display:flex;justify-content:space-between;align-items:baseline;gap:18px;padding:11px 0;border-bottom:1px solid var(--line)}
.info-card dl>div:last-child{border-bottom:0}
.info-card dt{color:var(--muted);font-size:13.5px}
.info-card dd{margin:0;font-weight:700;font-size:15px;text-align:right;word-break:break-word}
.subh{margin:22px 0 10px;font-size:16px;font-weight:800;color:var(--blue-deep)}
.crumbs{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;margin:0 0 16px;padding:0;list-style:none;font-size:13.5px;color:var(--muted)}
.crumbs li+li::before{content:"\203A";margin-right:8px;color:#9fb0c6}
.crumbs a{color:var(--blue-dark);font-weight:600}
.crumbs a:hover{text-decoration:underline}
.crumbs [aria-current]{color:var(--blue-deep);font-weight:700}
.wx{padding:18px 20px;border:1px solid var(--line);border-radius:18px;background:linear-gradient(180deg,#fff,var(--blue-soft));box-shadow:var(--shadow)}
.wx-top{display:grid;grid-template-columns:auto 1fr auto;gap:16px 30px;align-items:center}
.wx-temp{display:flex;align-items:center;gap:12px}
.wx-ico{font-size:46px;line-height:1}
.wx-deg{font-size:42px;font-weight:800;line-height:1;color:var(--blue-deep)}
.wx-desc{font-weight:700}
.wx-sub{color:var(--muted);font-size:13.5px}
.wx-meta{display:flex;flex-wrap:wrap;gap:8px 26px}
.wx-meta b{display:block;font-size:16px}
.wx-meta span{color:var(--muted);font-size:12.5px}
.wx-clock{text-align:right}
.wx-clock b{display:block;font-size:30px;font-weight:800;color:var(--blue-deep);font-variant-numeric:tabular-nums;line-height:1.1}
.wx-clock span{color:var(--muted);font-size:13px}
.wx-days{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid var(--line)}
.wx-day{padding:10px 8px;text-align:center;border:1px solid var(--line);border-radius:14px;background:#fff}
.wx-dn{font-size:13px;font-weight:700;color:var(--blue-deep)}
.wx-di{margin:4px 0;font-size:26px;line-height:1.2}
.wx-dt{font-size:14px}
.wx-dh{margin-top:2px;color:var(--muted);font-size:12px}
.wx-msg{color:var(--muted)}
.src-note{margin:8px 2px 0;color:var(--muted);font-size:12.5px}
.tabs{display:inline-flex;gap:4px;margin:0 0 14px;padding:4px;border:1px solid var(--line);border-radius:999px;background:#fff}
.tab{padding:8px 20px;border-radius:999px;font-size:14px;font-weight:700;color:var(--muted)}
.tab.on{background:var(--blue);color:#fff}
.tab:not(.on):hover{background:var(--blue-soft);color:var(--blue-dark)}
.mini-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px}
.mini{display:grid;gap:5px;padding:14px 16px;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:var(--shadow);transition:border-color .15s,transform .15s}
.mini:hover{border-color:#bfe3ff;transform:translateY(-1px)}
.mini .code{justify-self:start}
.mini .nama{font-weight:700;color:var(--blue-deep)}
.mini .sub{color:var(--muted);font-size:13px}
@media (max-width:640px){.wx-top{grid-template-columns:1fr}.wx-clock{text-align:left}}
@media (max-width:520px){.info-cols{grid-template-columns:1fr}.info-card dl>div{flex-direction:column;gap:2px}.info-card dd{text-align:left}}
</style>
<script type="application/ld+json">@json($crumbLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
@if($punyaPeta)
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
.navtools{margin-top:24px;padding:16px 18px;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:var(--shadow)}
.navbtns{display:flex;flex-wrap:wrap;gap:10px}
.navbtn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:42px;padding:0 18px;border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--blue-dark);font:700 14px var(--font-body);cursor:pointer;transition:border-color .15s,background .15s,color .15s}
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
<nav aria-label="Breadcrumb">
    <ol class="crumbs">
      @foreach($crumbs as $c)
        @if($c['u'])
          <li><a href="{{ $c['u'] }}">{{ $c['t'] }}</a></li>
        @else
          <li aria-current="page">{{ $c['t'] }}</li>
        @endif
      @endforeach
    </ol>
  </nav>

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
      @if($airport->departure_routes_count > 0)<span class="pill">{{ number_format($airport->departure_routes_count, 0, ',', '.') }} rute keluar</span>@endif
      @if($airport->arrival_routes_count > 0)<span class="pill">{{ number_format($airport->arrival_routes_count, 0, ',', '.') }} rute masuk</span>@endif
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

  <nav class="jump" aria-label="Navigasi halaman">
    @if($adaFakta)<a href="#ringkasan">Ringkasan</a>@endif
    @if($punyaPeta)<a href="#cuaca">Cuaca</a>@endif
    @if($kartu->isNotEmpty())<a href="#detail">Detail</a>@endif
    @if($punyaPeta)<a href="#peta-lokasi">Peta</a>@endif
    @if($adaRute)<a href="#rute">Rute</a>@endif
    <a href="#jadwal">Jadwal</a>
    @if($serupa['daftar']->isNotEmpty())<a href="#sekitar">Sekitar</a>@endif
  </nav>

  @if($adaFakta && $kpi->isNotEmpty())
  <h2 class="section" id="ringkasan">Ringkasan</h2>
  <div class="kpis">
    @foreach($kpi as $k)
      <div class="kpi"><div class="n">{{ $k['n'] }}</div><div class="l">{{ $k['l'] }}</div></div>
    @endforeach
  </div>
  @endif
  @if(!empty($b['icao']))<meta property="icaoCode" content="{{ $b['icao'] }}">@endif

  @if($punyaPeta)
  <h2 class="section" id="cuaca">Cuaca dan waktu setempat</h2>
  <div class="wx" id="wx" aria-live="polite" data-lat="{{ (float) $b['lat'] }}" data-lon="{{ (float) $b['long'] }}">
    <div class="wx-msg">Memuat cuaca&hellip;</div>
  </div>
  <p class="src-note">Data cuaca: <a class="link" href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo.com</a></p>
  @endif

  @if($kartu->isNotEmpty())
  <h2 class="section" id="detail">Detail bandara</h2>
  <div class="info-cols">
    @foreach($kartu as $judul => $baris)
      <section class="info-card">
        <h3>{{ $judul }}</h3>
        <dl>
          @foreach($baris as $label => $nilai)
            <div><dt>{{ $label }}</dt><dd>{{ $nilai }}</dd></div>
          @endforeach
        </dl>
      </section>
    @endforeach
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
    <h2 class="section" id="peta-lokasi">Peta dan navigasi</h2>
    <div class="navtools" style="margin-top:0">
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
        <a class="link" href="{{ route('search', ['q' => $maskapai->label]) }}">{{ $maskapai->label }}</a>
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
        <a class="link" href="{{ route('search', ['q' => $maskapai->label]) }}">{{ $maskapai->label }}</a>
      @endforeach
    </div>
  @endif
</article>

@if($adaRute)
<h2 class="section" id="rute">Rute langsung</h2>
@if($rute->isNotEmpty())
  <h3 class="subh">Dari {{ $iata }}</h3>
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
@if($masuk->isNotEmpty())
  <h3 class="subh">Ke {{ $iata }}</h3>
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
@endif

<h2 class="section" id="jadwal">Jadwal penerbangan</h2>
@php
  $kedatangan = $arahJadwal === 'kedatangan';
  $sisi = $kedatangan ? 'arrival' : 'departure';
  $lawan = $kedatangan ? 'departure' : 'arrival';
@endphp
<div class="tabs" role="tablist" aria-label="Jenis jadwal">
  <a class="tab {{ $kedatangan ? '' : 'on' }}" role="tab" aria-selected="{{ $kedatangan ? 'false' : 'true' }}" href="{{ route('bandara.show', ['iata' => $iata]) }}#jadwal">Keberangkatan</a>
  <a class="tab {{ $kedatangan ? 'on' : '' }}" role="tab" aria-selected="{{ $kedatangan ? 'true' : 'false' }}" href="{{ route('bandara.show', ['iata' => $iata, 'jadwal' => 'kedatangan']) }}#jadwal">Kedatangan</a>
</div>
@if(count($jadwal))
  <div class="scroll">
    <table>
      <thead><tr><th>Penerbangan</th><th>Maskapai</th><th>{{ $kedatangan ? 'Asal' : 'Tujuan' }}</th><th>Jadwal</th><th>Terminal / Gerbang</th><th>Status</th></tr></thead>
      <tbody>
        @foreach($jadwal as $f)
          @php
            $tm = collect([
                ! empty($f[$sisi]['terminal']) ? 'T'.$f[$sisi]['terminal'] : null,
                ! empty($f[$sisi]['gate']) ? 'Gerbang '.$f[$sisi]['gate'] : null,
                $kedatangan && ! empty($f['arrival']['baggage']) ? 'Bagasi '.$f['arrival']['baggage'] : null,
            ])->filter()->implode(' · ');
            $tunda = (int) ($f[$sisi]['delay'] ?? 0);
          @endphp
          <tr>
            <td>{{ $f['flight']['iata'] ?? '-' }}</td>
            <td>{{ $f['airline']['name'] ?? '-' }}</td>
            <td>{{ $f[$lawan]['airport'] ?? '-' }}</td>
            <td>{{ isset($f[$sisi]['scheduled']) ? \Carbon\Carbon::parse($f[$sisi]['scheduled'])->format('d M H:i') : '-' }}</td>
            <td>{{ $tm !== '' ? $tm : '-' }}</td>
            <td>
              <span class="status s-{{ $f['flight_status'] ?? 'scheduled' }}">{{ $f['flight_status'] ?? '-' }}</span>
              @if($tunda > 0)<br><small style="color:var(--muted)">terlambat {{ $tunda }} mnt</small>@endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@else
  <div class="state">
    Jadwal {{ $kedatangan ? 'kedatangan' : 'keberangkatan' }} belum tersedia untuk bandara ini saat ini.
    @if(app()->environment('local'))<br><small>Catatan pengembang: isi <code>AVIATIONSTACK_KEY</code> di file .env lalu muat ulang halaman.</small>@endif
  </div>
@endif

@if($serupa['daftar']->isNotEmpty())
<h2 class="section" id="sekitar">{{ $serupa['judul'] }}</h2>
<div class="mini-grid">
  @foreach($serupa['daftar'] as $s)
    <a class="mini" href="{{ route('bandara.show', $s['iata']) }}">
      <span class="code">{{ $s['iata'] }}</span>
      <span class="nama">{{ $s['nama'] }}</span>
      <span class="sub">{{ collect([$s['kategori'], $s['jarak'] !== null ? '± '.$desimal($s['jarak']).' km dari '.$iata : null])->filter()->implode(' · ') }}</span>
    </a>
  @endforeach
</div>
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

@if($punyaPeta)
@push('scripts')
<script>
(function () {
  var el = document.getElementById('wx');
  if (!el) return;
  var lat = parseFloat(el.getAttribute('data-lat'));
  var lon = parseFloat(el.getAttribute('data-lon'));
  var KODE = {
    0: ['Cerah', '\u2600\uFE0F'], 1: ['Cerah berawan', '\uD83C\uDF24\uFE0F'], 2: ['Berawan sebagian', '\u26C5'], 3: ['Berawan', '\u2601\uFE0F'],
    45: ['Berkabut', '\uD83C\uDF2B\uFE0F'], 48: ['Kabut tebal', '\uD83C\uDF2B\uFE0F'],
    51: ['Gerimis ringan', '\uD83C\uDF26\uFE0F'], 53: ['Gerimis', '\uD83C\uDF26\uFE0F'], 55: ['Gerimis lebat', '\uD83C\uDF27\uFE0F'],
    56: ['Gerimis beku', '\uD83C\uDF27\uFE0F'], 57: ['Gerimis beku lebat', '\uD83C\uDF27\uFE0F'],
    61: ['Hujan ringan', '\uD83C\uDF26\uFE0F'], 63: ['Hujan sedang', '\uD83C\uDF27\uFE0F'], 65: ['Hujan lebat', '\uD83C\uDF27\uFE0F'],
    66: ['Hujan beku', '\uD83C\uDF27\uFE0F'], 67: ['Hujan beku lebat', '\uD83C\uDF27\uFE0F'],
    71: ['Salju ringan', '\uD83C\uDF28\uFE0F'], 73: ['Salju', '\uD83C\uDF28\uFE0F'], 75: ['Salju lebat', '\u2744\uFE0F'], 77: ['Butiran salju', '\uD83C\uDF28\uFE0F'],
    80: ['Hujan lokal ringan', '\uD83C\uDF26\uFE0F'], 81: ['Hujan lokal', '\uD83C\uDF27\uFE0F'], 82: ['Hujan lokal lebat', '\u26C8\uFE0F'],
    85: ['Salju lokal', '\uD83C\uDF28\uFE0F'], 86: ['Salju lokal lebat', '\u2744\uFE0F'],
    95: ['Badai petir', '\u26C8\uFE0F'], 96: ['Badai petir, es ringan', '\u26C8\uFE0F'], 99: ['Badai petir, es lebat', '\u26C8\uFE0F']
  };
  var ZONA = {'Asia/Jakarta': 'WIB', 'Asia/Pontianak': 'WIB', 'Asia/Makassar': 'WITA', 'Asia/Jayapura': 'WIT'};

  function info(c) { return KODE[c] || ['Tidak diketahui', '\uD83C\uDF21\uFE0F']; }
  function bulat(n) { return Math.round(Number(n)); }
  function labelZona(tz, offDetik) {
    if (ZONA[tz]) return ZONA[tz];
    var j = Number(offDetik) / 3600;
    var abs = Math.abs(j);
    return 'UTC' + (j < 0 ? '-' : '+') + (abs % 1 ? abs.toFixed(1) : abs);
  }
  function jalankanJam(tz) {
    var jam = el.querySelector('[data-jam]');
    var tgl = el.querySelector('[data-tanggal]');
    if (!jam || !tgl) return;
    try {
      var fj = new Intl.DateTimeFormat('id-ID', {timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'});
      var ft = new Intl.DateTimeFormat('id-ID', {timeZone: tz, weekday: 'long', day: 'numeric', month: 'long'});
      var tik = function () {
        var d = new Date();
        jam.textContent = fj.format(d).replace(/\./g, ':');
        tgl.textContent = ft.format(d);
      };
      tik();
      setInterval(tik, 1000);
    } catch (e) { jam.textContent = '-'; }
  }
  function namaHari(iso, i) {
    if (i === 0) return 'Hari ini';
    if (i === 1) return 'Besok';
    try {
      return new Intl.DateTimeFormat('id-ID', {weekday: 'long', timeZone: 'UTC'}).format(new Date(iso + 'T00:00:00Z'));
    } catch (e) { return iso; }
  }
  function render(d) {
    var c = d.current, dy = d.daily, inf = info(c.weather_code), hari = '';
    for (var i = 0; i < dy.time.length; i++) {
      var di = info(dy.weather_code[i]);
      var ph = dy.precipitation_probability_max ? dy.precipitation_probability_max[i] : null;
      hari += '<div class="wx-day"><div class="wx-dn">' + namaHari(dy.time[i], i) + '</div>'
        + '<div class="wx-di" title="' + di[0] + '">' + di[1] + '</div>'
        + '<div class="wx-dt"><b>' + bulat(dy.temperature_2m_max[i]) + '\u00B0</b> / ' + bulat(dy.temperature_2m_min[i]) + '\u00B0</div>'
        + '<div class="wx-dh">' + (ph !== null && ph !== undefined ? 'Hujan ' + bulat(ph) + '%' : di[0]) + '</div></div>';
    }
    el.innerHTML =
      '<div class="wx-top">'
      + '<div class="wx-temp"><span class="wx-ico">' + inf[1] + '</span><div><div class="wx-deg">' + bulat(c.temperature_2m) + '\u00B0C</div>'
      + '<div class="wx-desc">' + inf[0] + '</div><div class="wx-sub">Terasa ' + bulat(c.apparent_temperature) + '\u00B0C</div></div></div>'
      + '<div class="wx-meta">'
      + '<div><b>' + bulat(c.relative_humidity_2m) + '%</b><span>Kelembapan</span></div>'
      + '<div><b>' + bulat(c.wind_speed_10m) + ' km/j</b><span>Angin</span></div>'
      + '<div><b>' + Number(c.precipitation).toFixed(1) + ' mm</b><span>Curah hujan</span></div></div>'
      + '<div class="wx-clock"><b data-jam>--:--:--</b><span data-tanggal></span><br><span>Waktu setempat (' + labelZona(d.timezone, d.utc_offset_seconds) + ')</span></div>'
      + '</div><div class="wx-days">' + hari + '</div>';
    jalankanJam(d.timezone);
  }
  function gagal() {
    el.innerHTML = '<div class="wx-msg">Data cuaca belum bisa dimuat. Coba muat ulang halaman beberapa saat lagi.</div>';
  }

  var url = 'https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lon
    + '&current=temperature_2m,apparent_temperature,relative_humidity_2m,precipitation,weather_code,wind_speed_10m'
    + '&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max'
    + '&timezone=auto&forecast_days=4';
  var ctl = ('AbortController' in window) ? new AbortController() : null;
  var timer = setTimeout(function () { if (ctl) ctl.abort(); }, 9000);
  fetch(url, ctl ? {signal: ctl.signal} : {})
    .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
    .then(function (d) { clearTimeout(timer); if (!d || !d.current || !d.daily) throw new Error('data'); render(d); })
    .catch(function () { clearTimeout(timer); gagal(); });
})();
</script>
@endpush
@endif
