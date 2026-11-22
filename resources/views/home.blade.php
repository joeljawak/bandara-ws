@extends('layouts.app')
@section('bodyclass', 'is-home')
@section('title', 'BANDARA ASEAN — Cari Bandara, Maskapai, dan Rute Asia Tenggara')
@php
    $stats = $stats ?? ['airports' => 0];
    $populer = $populer ?? [];
    $negaraList = $negaraList ?? [];
    $fotos = $fotos ?? [];
    $angka = fn ($n): string => number_format((int) $n, 0, ',', '.');
    $kodeNegara = [
        'Indonesia' => 'ID', 'Malaysia' => 'MY', 'Singapura' => 'SG', 'Thailand' => 'TH', 'Vietnam' => 'VN',
        'Filipina' => 'PH', 'Myanmar' => 'MM', 'Kamboja' => 'KH', 'Laos' => 'LA', 'Brunei' => 'BN', 'Timor Leste' => 'TL',
    ];
    $pesawat = 'M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z';
    $contoh = $populer[0]['iata'] ?? 'CGK';
    $penumpang = function (int $n): string {
        if ($n >= 1000000) {
            return number_format($n / 1000000, 1, ',', '.').' juta';
        }

        return number_format($n / 1000, 0, ',', '.').' ribu';
    };
    $seo = [
        'title' => 'BANDARA ASEAN — Cari Bandara, Maskapai, dan Rute Asia Tenggara',
        'description' => 'Cari '.$angka($stats['airports']).' bandara Asia Tenggara lewat nama, kode IATA, kota, alias populer, atau pencarian di sekitar lokasi. Dilengkapi rute, maskapai, peta, dan data RDF.',
        'canonical' => route('home'),
        'jsonld' => [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'BANDARA ASEAN',
            'url' => route('home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('search').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ];
@endphp
@section('head')
<style>
.is-home{overflow-x:hidden}
.is-home .hero{margin:0 calc(50% - 50vw);width:auto;min-height:calc(100vh - 66px);min-height:calc(100svh - 66px);border-radius:0;box-shadow:none;padding:72px 24px 110px;
  background:#0b2e59 url('https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=2400&q=80') center/cover no-repeat}
.is-home .hero-inner{max-width:780px}
.is-home .hero h1{font-size:clamp(42px,8vw,84px);letter-spacing:.01em}
.is-home .hero .tagline{font-size:clamp(16px,2.4vw,21px);margin:18px 0 34px}
.is-home .hero .search{max-width:720px}
.hm-scroll{position:absolute;left:50%;bottom:26px;transform:translateX(-50%);display:grid;place-items:center;width:46px;height:46px;border:1px solid rgba(255,255,255,.55);border-radius:50%;color:#fff;background:rgba(255,255,255,.12);backdrop-filter:blur(6px);animation:hm-bob 2.2s ease-in-out infinite}
.hm-scroll:hover{background:rgba(255,255,255,.28)}
.hm-scroll svg{width:22px;height:22px}
@keyframes hm-bob{0%,100%{transform:translate(-50%,0)}50%{transform:translate(-50%,7px)}}
.hm-sec{margin-top:52px;animation:hm-up .5s ease both}
.hm-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:6px 16px;margin-bottom:18px}
.hm-head h2{margin:0;font-size:clamp(21px,3vw,27px);font-weight:800;letter-spacing:-.01em;color:var(--blue-deep)}
.hm-head p{margin:4px 0 0;color:var(--muted);font-size:14.5px}
.hm-head a{font-weight:700;font-size:14px;color:var(--blue-dark)}
.hm-head a:hover{text-decoration:underline}
.hm-feat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px}
.hm-feat{display:flex;flex-direction:column;gap:8px;padding:22px 20px 18px;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:var(--shadow);transition:transform .18s,box-shadow .18s,border-color .18s}
.hm-feat:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);border-color:#bfe3ff}
.hm-ico{width:48px;height:48px;border-radius:14px;display:grid;place-items:center}
.hm-ico svg{width:24px;height:24px}
.hm-ico.b{background:var(--blue-soft);color:var(--blue-dark)}
.hm-ico.o{background:var(--accent-soft);color:var(--accent)}
.hm-ico.y{background:var(--yellow-soft);color:var(--warn)}
.hm-ico.g{background:#e3f6ee;color:var(--ok)}
.hm-feat h3{margin:8px 0 0;font-size:17px;font-weight:800;color:var(--blue-deep)}
.hm-feat p{margin:0;color:var(--muted);font-size:14px;line-height:1.55}
.hm-feat .go{margin-top:auto;padding-top:12px;font-size:14px;font-weight:700;color:var(--blue-dark)}
.hm-apt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(235px,1fr));gap:16px}
.hm-apt{display:flex;flex-direction:column;overflow:hidden;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:var(--shadow);transition:transform .18s,box-shadow .18s}
.hm-apt:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
.hm-ph{position:relative;aspect-ratio:16/10;background:linear-gradient(135deg,var(--blue-deep),var(--blue));display:grid;place-items:center;color:rgba(255,255,255,.4)}
.hm-ph img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
.hm-ph svg{width:46px;height:46px}
.hm-ph .code{position:absolute;left:12px;top:12px;z-index:1;box-shadow:0 4px 12px rgba(4,28,58,.3)}
.hm-bd{display:grid;gap:3px;padding:14px 16px 16px}
.hm-nm{font-weight:800;line-height:1.3;color:var(--blue-deep)}
.hm-mt{color:var(--muted);font-size:13px}
.hm-pax{margin-top:8px;font-size:14px;font-weight:700;color:var(--accent)}
.hm-pax small{font-weight:500;color:var(--muted)}
.hm-cty-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.hm-cty{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:var(--shadow);transition:border-color .15s,transform .15s}
.hm-cty:hover{border-color:#bfe3ff;transform:translateY(-2px)}
.hm-cc{flex:none;width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:14px;font-weight:800;letter-spacing:.04em;background:var(--blue-soft);color:var(--blue-dark)}
.hm-cty:nth-child(3n+2) .hm-cc{background:var(--accent-soft);color:var(--accent)}
.hm-cty:nth-child(3n) .hm-cc{background:var(--yellow-soft);color:var(--warn)}
.hm-cty b{display:block;color:var(--blue-deep)}
.hm-cty span{color:var(--muted);font-size:13px}
.hm-cta{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:18px;margin:52px 0 8px;padding:34px 32px;border-radius:24px;color:#fff;background:linear-gradient(120deg,var(--blue-deep),var(--blue-dark) 55%,var(--blue));box-shadow:var(--shadow-lg)}
.hm-cta h2{margin:0;font-size:clamp(20px,3vw,27px);font-weight:800}
.hm-cta p{margin:6px 0 0;max-width:520px;color:rgba(255,255,255,.86)}
.hm-btn{display:inline-flex;align-items:center;gap:8px;min-height:46px;padding:0 26px;border-radius:999px;background:var(--accent);color:#fff;font-weight:800;box-shadow:0 10px 22px rgba(255,94,31,.35)}
.hm-btn:hover{filter:brightness(1.06)}
@keyframes hm-up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.hm-sec,.hm-scroll{animation:none}.hm-feat,.hm-apt,.hm-cty{transition:none}}
@media (max-width:720px){
  .hm-cta{padding:26px 22px}
}
</style>
@endsection
@section('content')
<section class="hero" aria-labelledby="judul-beranda">
  <div class="hero-inner">
    <span class="hero-eyebrow">
      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $pesawat }}"/></svg>
      Jelajahi bandara Asia Tenggara
    </span>
    <div class="logo-dots" aria-hidden="true"><i></i><i></i><i></i></div>
    <h1 class="logo" id="judul-beranda"><span class="b1">BANDARA</span> <span class="b2">ASEAN</span></h1>
    <p class="tagline">Cari bandara, maskapai, dan rute penerbangan dalam satu tempat.</p>
    @include('partials.searchbox', ['autofocus' => true])
    <div class="chips">
      <span>Coba:</span>
      <a class="chip" href="{{ route('search', ['q' => 'Kualanamu']) }}">Kualanamu</a>
      <a class="chip" href="{{ route('search', ['q' => 'Changi']) }}">Changi</a>
      <a class="chip" href="{{ route('search', ['q' => 'CGK']) }}">CGK</a>
      <a class="chip" href="{{ route('search', ['q' => 'Garuda']) }}">Garuda</a>
      <a class="chip" href="{{ route('search', ['q' => 'Suvarnabhumi']) }}">Suvarnabhumi</a>
    </div>
  </div>
  <a class="hm-scroll" href="#h-fitur" aria-label="Gulir ke bawah">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
  </a>
</section>

<section class="hm-sec" aria-labelledby="h-fitur">
  <div class="hm-head">
    <div><h2 id="h-fitur">Yang bisa Anda lakukan</h2><p>Cari bandara, cek rutenya, dan buka detail lengkap tiap bandara.</p></div>
  </div>
  <div class="hm-feat-grid">
    <a class="hm-feat" href="{{ route('bandara.index') }}">
      <span class="hm-ico b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span>
      <h3>Jelajahi bandara</h3>
      <p>Saring berdasarkan negara, kategori, dan ketersediaan rute, lalu urutkan menurut jumlah penumpang.</p>
      <span class="go">Buka daftar bandara &rarr;</span>
    </a>
    <a class="hm-feat" href="{{ route('route.index') }}">
      <span class="hm-ico o"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14"/></svg></span>
      <h3>Peta rute</h3>
      <p>Cari penerbangan langsung antar dua bandara dan lihat kemungkinan transitnya di peta.</p>
      <span class="go">Buka peta rute &rarr;</span>
    </a>
    <a class="hm-feat" href="{{ route('bandara.show', $contoh) }}">
      <span class="hm-ico y"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h6"/></svg></span>
      <h3>Detail tiap bandara</h3>
      <p>Cuaca dan jam setempat, jadwal penerbangan, data landasan, peta, dan citra satelit.</p>
      <span class="go">Lihat contoh {{ $contoh }} &rarr;</span>
    </a>
  </div>
</section>

@if($populer !== [])
<section class="hm-sec" aria-labelledby="h-populer">
  <div class="hm-head">
    <div><h2 id="h-populer">Bandara tersibuk</h2><p>Berdasarkan jumlah penumpang per tahun.</p></div>
    <a href="{{ route('bandara.index', ['urut' => 'penumpang']) }}">Lihat semua bandara &rarr;</a>
  </div>
  <div class="hm-apt-grid">
    @foreach($populer as $r)
      @php $foto = $fotos[$r['nama']] ?? null; @endphp
      <a class="hm-apt" href="{{ route('bandara.show', $r['iata']) }}">
        <div class="hm-ph">
          @if($foto)
            <img src="{{ $foto['url'] }}" alt="" loading="lazy">
          @else
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $pesawat }}"/></svg>
          @endif
          <span class="code">{{ $r['iata'] }}</span>
        </div>
        <div class="hm-bd">
          <div class="hm-nm">{{ $r['nama'] }}</div>
          <div class="hm-mt">{{ collect([$r['negara'], $r['kategori']])->filter()->implode(' · ') }}</div>
          <div class="hm-pax">{{ $penumpang($r['penumpang']) }} <small>penumpang per tahun</small></div>
        </div>
      </a>
    @endforeach
  </div>
</section>
@endif

@if($negaraList !== [])
<section class="hm-sec" aria-labelledby="h-negara">
  <div class="hm-head">
    <div><h2 id="h-negara">Telusuri per negara</h2><p>Pilih negara untuk melihat daftar bandaranya.</p></div>
  </div>
  <div class="hm-cty-grid">
    @foreach($negaraList as $n)
      <a class="hm-cty" href="{{ route('bandara.index', ['negara' => $n['label']]) }}">
        <span class="hm-cc">{{ $kodeNegara[$n['label']] ?? strtoupper(substr($n['label'], 0, 2)) }}</span>
        <div><b>{{ $n['label'] }}</b><span>{{ $angka($n['jumlah']) }} bandara</span></div>
      </a>
    @endforeach
  </div>
</section>
@endif

<section class="hm-cta" aria-label="Ajakan">
  <div>
    <h2>Mau terbang dari mana ke mana?</h2>
    <p>Pilih bandara asal dan tujuan, lihat apakah ada penerbangan langsung atau harus transit.</p>
  </div>
  <a class="hm-btn" href="{{ route('route.index') }}">Cari rute sekarang &rarr;</a>
</section>
@endsection
