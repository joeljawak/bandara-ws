@extends('layouts.app')
@section('title', $airport['nama'] . ' (' . $airport['iata'] . ') — AeroSearch')

@section('head')
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $airport['nama'] }} ({{ $airport['iata'] }})">
<meta property="og:description" content="Informasi bandara {{ $airport['nama'] }} di {{ $airport['kota'] }}, Indonesia.">
<meta property="og:url" content="{{ url()->current() }}">
@isset($foto)<meta property="og:image" content="{{ $foto }}">@endisset
@endsection

@section('content')
<article vocab="https://schema.org/" typeof="Airport">

  <div class="hero-detail {{ isset($foto) ? 'has-foto' : '' }}">
    @isset($foto)
      <img src="{{ $foto }}" alt="Foto {{ $airport['nama'] }}" loading="lazy">
      <div class="hero-fade"></div>
    @endisset
    <div class="hero-text">
      <div class="hero-code"><span property="iataCode">{{ $airport['iata'] }}</span></div>
      <h1 property="name">{{ $airport['nama'] }}</h1>
      <div class="sub">
        <span property="address" typeof="PostalAddress">
          <span property="addressLocality">{{ $airport['kota'] }}</span>,
          <span property="addressCountry">Indonesia</span>
        </span>
        @isset($airport['icao']) &middot; ICAO <span property="icaoCode">{{ $airport['icao'] }}</span>@endisset
      </div>
    </div>
  </div>

  <div class="facts">
    <div class="fact"><div class="k">Kode IATA</div><div class="v">{{ $airport['iata'] }}</div></div>
    @isset($airport['icao'])<div class="fact"><div class="k">Kode ICAO</div><div class="v">{{ $airport['icao'] }}</div></div>@endisset
    <div class="fact"><div class="k">Kota</div><div class="v">{{ $airport['kota'] }}</div></div>
    @isset($airport['lat'])<div class="fact"><div class="k">Koordinat</div><div class="v">{{ number_format($airport['lat'],3) }}, {{ number_format($airport['lon'],3) }}</div></div>@endisset
  </div>

  <div class="linkrow">
    <a class="link" href="{{ route('route.index', ['dari' => $airport['iata']]) }}">Lihat rute dari {{ $airport['iata'] }}</a>
    @isset($airport['dbpedia'])
      <link property="sameAs" href="{{ $airport['dbpedia'] }}">
      <a class="link" href="{{ $airport['dbpedia'] }}" target="_blank" rel="noopener">Lihat di DBpedia</a>
    @endisset
  </div>

  @isset($airport['lat'])
    @php($lat = (float) $airport['lat'])
    @php($lon = (float) $airport['lon'])
    <h2 class="section">Lokasi</h2>
    <span property="geo" typeof="GeoCoordinates">
      <meta property="latitude" content="{{ $airport['lat'] }}">
      <meta property="longitude" content="{{ $airport['lon'] }}">
    </span>
    <div class="mapbox">
      <iframe title="Peta lokasi {{ $airport['nama'] }}" loading="lazy"
        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $lon - 0.05 }}%2C{{ $lat - 0.03 }}%2C{{ $lon + 0.05 }}%2C{{ $lat + 0.03 }}&layer=mapnik&marker={{ $lat }}%2C{{ $lon }}"></iframe>
    </div>
  @endisset
</article>

<h2 class="section">Maskapai</h2>
@if(count($airlines))
  <div class="maskapai-list">
    @foreach($airlines as $m)
      <span class="tag">{{ $m['maskapai'] }}</span>
    @endforeach
  </div>
@else
  <div class="state">Belum ada data maskapai untuk bandara ini.</div>
@endif

<h2 class="section">Jadwal keberangkatan</h2>
@if(count($flights))
  <div class="scroll"><table>
    <thead><tr><th>Penerbangan</th><th>Maskapai</th><th>Tujuan</th><th>Jadwal</th><th>Status</th></tr></thead>
    <tbody>
    @foreach($flights as $f)
      <tr>
        <td>{{ $f['flight']['iata'] ?? '-' }}</td>
        <td>{{ $f['airline']['name'] ?? '-' }}</td>
        <td>{{ $f['arrival']['airport'] ?? '-' }}</td>
        <td>{{ isset($f['departure']['scheduled']) ? \Carbon\Carbon::parse($f['departure']['scheduled'])->format('d M H:i') : '-' }}</td>
        <td><span class="status s-{{ $f['flight_status'] ?? 'scheduled' }}">{{ $f['flight_status'] ?? '-' }}</span></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
@else
  <div class="state">Jadwal belum tersedia. Isi AVIATIONSTACK_KEY di file .env, lalu muat ulang halaman.</div>
@endif
@endsection
