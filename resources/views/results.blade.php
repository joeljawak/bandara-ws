@extends('layouts.app')
@section('title', $q !== '' ? $q.' — Hasil pencarian BANDARA ASEAN' : 'Cari bandara — BANDARA ASEAN')
@php
    $token = app(\App\Services\AirportSearch::class)->token($q);
    $judulHalaman = $q !== '' ? 'Hasil untuk "'.$q.'"' : 'Semua bandara';
    $adaFilter = $negara !== '' || $kategori !== '' || $asean !== '' || $ruteFilter !== '';
    $seo = [
        'description' => $q !== ''
            ? 'Hasil pencarian bandara untuk "'.$q.'" di BANDARA ASEAN: nama, kode IATA, kota, negara, dan rute.'
            : 'Jelajahi seluruh bandara Asia Tenggara di BANDARA ASEAN.',
        'canonical' => $q === '' && ! $adaFilter ? route('search') : route('search', ['q' => $q]),
        'robots' => ($q !== '' || $near !== '') ? 'noindex,follow' : 'index,follow',
        'jsonld' => $q === '' ? null : [
            '@context' => 'https://schema.org',
            '@type' => 'SearchResultsPage',
            'name' => 'Hasil pencarian '.$q,
            'url' => url()->current(),
        ],
    ];
@endphp

@section('content')
<div class="top-search">@include('partials.searchbox')</div>

<div class="tabs" role="tablist" aria-label="Jenis hasil">
  @foreach(['semua' => 'Semua', 'bandara' => 'Bandara', 'maskapai' => 'Maskapai', 'rute' => 'Rute'] as $nilai => $label)
    <a class="tab {{ $jenis === $nilai ? 'active' : '' }}" role="tab" aria-selected="{{ $jenis === $nilai ? 'true' : 'false' }}"
       href="{{ route('search', array_filter(array_merge(request()->query(), ['jenis' => $nilai, 'page' => null]))) }}">
      {{ $label }}
      @if($nilai === 'bandara')<span class="n">{{ number_format($results->total(), 0, ',', '.') }}</span>@endif
      @if($nilai === 'maskapai' && isset($maskapai))<span class="n">{{ $maskapai->count() }}</span>@endif
      @if($nilai === 'rute' && isset($rute))<span class="n">{{ $rute->count() }}</span>@endif
    </a>
  @endforeach
</div>

<form class="facets" method="get" action="{{ route('search') }}" aria-label="Filter pencarian">
  @if($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
  <input type="hidden" name="jenis" value="{{ $jenis }}">
  @if($near !== '')<input type="hidden" name="near" value="{{ $near }}">@endif

  <div class="field">
    <label for="f-negara">Negara</label>
    <select id="f-negara" name="negara">
      <option value="">Semua negara</option>
      @foreach($facets['countries'] as $c)
        <option value="{{ $c['label'] }}" @selected($negara === $c['label'])>{{ $c['label'] }} ({{ $c['airports_count'] }})</option>
      @endforeach
    </select>
  </div>

  <div class="field">
    <label for="f-kategori">Kategori</label>
    <select id="f-kategori" name="kategori">
      <option value="">Semua kategori</option>
      @foreach($facets['categories'] as $kat)
        <option value="{{ $kat }}" @selected($kategori === $kat)>{{ $kat }}</option>
      @endforeach
    </select>
  </div>

  <div class="field">
    <label for="f-urut">Urutkan</label>
    <select id="f-urut" name="urut">
      <option value="relevan" @selected($urut === 'relevan')>Paling relevan</option>
      <option value="nama" @selected($urut === 'nama')>Nama A-Z</option>
      <option value="penumpang" @selected($urut === 'penumpang')>Penumpang terbanyak</option>
    </select>
  </div>

  <div class="field">
    <label>Filter</label>
    <label class="check"><input type="checkbox" name="asean" value="1" @checked($asean === '1')> Hanya ASEAN</label>
    <label class="check"><input type="checkbox" name="rute" value="1" @checked($ruteFilter === '1')> Punya rute</label>
  </div>

  <div class="aksi">
    <button class="btn" type="submit">Terapkan</button>
    <a class="btn ghost" href="{{ route('search', ['q' => $q]) }}">Reset</a>
  </div>
</form>

@if($q === '' && $near === '')
  <div class="hint">Ketik nama bandara, kota, kode IATA, alias populer (mis. <strong>Cengkareng</strong>, <strong>Ngurah Rai</strong>), atau nama maskapai. Bisa juga cari rute dengan dua kode, mis. <strong>CGK DPS</strong>.</div>
@endif

<div class="page-head">
  <h1>{{ $judulHalaman }}</h1>
  <span class="count">
    @if($near !== '')
      Bandara dalam radius {{ $radius }} km
    @endif
    @if($q !== '' || $near !== '')
      · {{ number_format($results->total(), 0, ',', '.') }} bandara
    @endif
  </span>
</div>

@if($q !== '' && count($saran) > 0 && $results->total() < 3 && $jenis === 'semua')
  <div class="hint">
    Maksud Anda:
    @foreach($saran as $s)
      <a class="chip" href="{{ route('search', ['q' => $s['judul']]) }}">{{ $s['judul'] }}</a>
    @endforeach
  </div>
@endif

@if($jenis === 'semua' || $jenis === 'bandara')
  @if($results->isEmpty())
    <div class="state">
      Tidak ada bandara untuk &quot;{{ $q }}&quot;.
      <ul class="empty-help">
        <li>Periksa ejaan nama atau kode IATA (3 huruf, mis. CGK).</li>
        <li>Coba nama kota atau alias populer seperti Cengkareng, Changi, atau KLIA.</li>
        <li>Longgarkan filter negara/kategori, atau <a class="link" href="{{ route('search', ['q' => $q]) }}">reset filter</a>.</li>
      </ul>
    </div>
  @else
    <div class="list">
      @foreach($results as $r)
        @include('partials.row', [
            'r' => $r,
            'token' => $token,
            'sub' => collect([$r['kota'] ?? null, $r['negara'] ?? null])->filter()->implode(', '),
        ])
      @endforeach
    </div>
    @include('partials.pager', ['paginator' => $results])
  @endif
@endif

@if(($jenis === 'semua' || $jenis === 'maskapai') && $maskapai->isNotEmpty())
  <h2 class="group-title">Maskapai <span class="count">{{ $maskapai->count() }} hasil</span></h2>
  <div class="list">
    @foreach($maskapai as $a)
      <a class="entity-card" href="{{ route('data.airlines', ['q' => $a->label]) }}">
        <span class="nama">{{ $a->label }} @if($a->airline_code)<span class="badge">{{ $a->airline_code }}</span>@endif</span>
        <span class="sub">
          {{ number_format($a->routes_count, 0, ',', '.') }} rute
          @if($a->hubAirport) · hub {{ $a->hubAirport->label }} ({{ $a->hubAirport->iata_code }})@endif
        </span>
      </a>
    @endforeach
  </div>
@endif

@if(($jenis === 'semua' || $jenis === 'rute') && $rute->isNotEmpty())
  <h2 class="group-title">Rute <span class="count">{{ $rute->count() }} hasil</span></h2>
  <div class="routelist">
    @foreach($rute as $rt)
      <div class="routecard">
        <div class="r-head">
          <a class="link" href="{{ route('bandara.show', $rt->originAirport->iata_code) }}">{{ $rt->originAirport->iata_code }}</a>
          <span class="arrow" aria-hidden="true">&rarr;</span>
          <a class="link" href="{{ route('bandara.show', $rt->destinationAirport->iata_code) }}">{{ $rt->destinationAirport->iata_code }}</a>
          @if($rt->airline)<span class="badge">{{ $rt->airline->label }}</span>@endif
        </div>
        <div class="r-sub">
          {{ $rt->originAirport->label }} &rarr; {{ $rt->destinationAirport->label }}
          · <a class="link" href="{{ route('route.index', ['dari' => $rt->originAirport->iata_code, 'ke' => $rt->destinationAirport->iata_code]) }}">lihat di peta</a>
        </div>
      </div>
    @endforeach
  </div>
@endif

@if($jenis === 'semua' && $results->isEmpty() && $maskapai->isEmpty() && $rute->isEmpty() && $q !== '')
  <div class="state">Tidak ada hasil untuk &quot;{{ $q }}&quot; di bandara, maskapai, maupun rute.</div>
@endif

<div class="hint" id="geo-hint">
  <strong>Bandara dekat saya.</strong>
  <button type="button" class="btn ghost" id="geo-btn">Gunakan lokasi saya</button>
  <span id="geo-status" role="status" aria-live="polite"></span>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var tombol = document.getElementById('geo-btn');
  var status = document.getElementById('geo-status');
  if (!tombol || !navigator.geolocation) { return; }

  tombol.addEventListener('click', function () {
    status.textContent = 'Mencari lokasi...';
    navigator.geolocation.getCurrentPosition(function (pos) {
      var url = new URL(window.location.href);
      url.searchParams.set('near', pos.coords.latitude.toFixed(4) + ',' + pos.coords.longitude.toFixed(4));
      url.searchParams.set('radius', url.searchParams.get('radius') || '100');
      url.searchParams.delete('page');
      window.location.href = url.toString();
    }, function () {
      status.textContent = 'Lokasi tidak diizinkan. Coba masukkan koordinat manual di URL (near=lat,lon).';
    }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
  });
})();
</script>
@endpush
