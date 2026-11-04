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
    $adaFakta = $punyaPeta || collect(['icao', 'status', 'kategori', 'operator', 'penumpang', 'runway'])->contains(fn ($k) => isset($b[$k]));
@endphp
@extends('layouts.app')
@section('title', $b['nama'] . ' (' . $iata . ') - BANDARA ASEAN')

@section('head')
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $b['nama'] }} ({{ $iata }})">
<meta property="og:description" content="Informasi bandara {{ $b['nama'] }} di {{ $lokasi }}.">
<meta property="og:url" content="{{ url()->current() }}">
@if($foto)<meta property="og:image" content="{{ $foto['url'] }}">@endif
@endsection

@section('content')
<a class="back" href="{{ route('bandara.index') }}">&larr; Semua bandara</a>

<article vocab="https://schema.org/" typeof="Airport">
  <div class="entity">
    <span class="code" property="iataCode">{{ $iata }}</span>
    <h1 property="name">{{ $b['nama'] }}</h1>
    <p property="address" typeof="PostalAddress">
      @if(isset($b['wilayah']))<span property="addressLocality">{{ $b['wilayah'] }}</span>, @endif
      @if(isset($b['provinsi']))<span property="addressRegion">{{ $b['provinsi'] }}</span>, @endif
      <span property="addressCountry">{{ $b['negara'] }}</span>
    </p>
  </div>

  <div class="visual">
    @if($foto)
      <img src="{{ $foto['url'] }}" alt="Foto {{ $b['nama'] }}">
      <a class="credit" href="{{ $foto['page'] }}" target="_blank" rel="noopener">Foto: Wikipedia</a>
    @else
      @include('partials.art', ['a' => $art, 'mode' => 'hero'])
    @endif
  </div>

  @if($adaFakta)
  <div class="facts">
    @isset($b['icao'])<div class="fact"><div class="k">Kode ICAO</div><div class="v" property="icaoCode">{{ $b['icao'] }}</div></div>@endisset
    @isset($b['status'])<div class="fact"><div class="k">Status</div><div class="v">{{ $b['status'] }}</div></div>@endisset
    @isset($b['kategori'])<div class="fact"><div class="k">Kategori</div><div class="v">{{ $b['kategori'] }}</div></div>@endisset
    @isset($b['operator'])<div class="fact"><div class="k">Pengelola</div><div class="v">{{ $b['operator'] }}</div></div>@endisset
    @isset($b['penumpang'])<div class="fact"><div class="k">Penumpang per tahun</div><div class="v">{{ number_format((float) $b['penumpang'], 0, ',', '.') }}</div></div>@endisset
    @isset($b['runway'])<div class="fact"><div class="k">Landasan terpanjang</div><div class="v">{{ number_format((float) $b['runway'], 0, ',', '.') }} m</div></div>@endisset
    @if($punyaPeta)<div class="fact"><div class="k">Koordinat</div><div class="v">{{ number_format((float) $b['lat'], 4) }}, {{ number_format((float) $b['long'], 4) }}</div></div>@endif
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
    @isset($b['sameAs'])
      <link property="sameAs" href="{{ $b['sameAs'] }}">
      <a class="link" href="{{ $b['sameAs'] }}" target="_blank" rel="noopener">
        Lihat di {{ str_contains($b['sameAs'], 'wikidata') ? 'Wikidata' : 'DBpedia' }}
      </a>
    @endisset
  </div>

  @if($punyaPeta)
    @php($lat = (float) $b['lat'])
    @php($lon = (float) $b['long'])
    <div class="mapbox">
        <iframe title="Peta lokasi {{ $b['nama'] }}" loading="lazy"
          src="https://www.openstreetmap.org/export/embed.html?bbox={{ $lon - 0.05 }}%2C{{ $lat - 0.03 }}%2C{{ $lon + 0.05 }}%2C{{ $lat + 0.03 }}&layer=mapnik&marker={{ $lat }}%2C{{ $lon }}"></iframe>
    </div>
  @endif
</article>

<h2 class="section">Rute dari {{ $iata }}</h2>
@if ($rute->isEmpty())
  <div class="state">Data rute untuk bandara ini belum tersedia.</div>
@else
  <div class="scroll">
    <table>
      <thead><tr><th>Tujuan</th><th>Maskapai</th>@if($punyaPeta)<th></th>@endif</tr></thead>
      <tbody>
        @foreach ($rute as $kode => $items)
          <tr>
            <td><a class="link" href="{{ route('bandara.show', $kode) }}">{{ $items->first()['dst_nama'] }}</a> ({{ $kode }})</td>
            <td>{{ $items->pluck('maskapai')->unique()->implode(', ') }}</td>
            @if($punyaPeta)<td><a class="link" href="{{ route('route.index', ['dari' => $iata, 'ke' => $kode]) }}">Peta</a></td>@endif
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
  <div class="state">Jadwal belum tersedia. Isi <code>AVIATIONSTACK_KEY</code> di file .env, lalu muat ulang halaman.</div>
@endif
@endsection
