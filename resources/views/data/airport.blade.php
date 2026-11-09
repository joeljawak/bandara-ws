@extends('layouts.app')
@section('title', $airport->label.' ('.$airport->iata_code.') - BANDARA ASEAN')

@if ($airport->latitude !== null && $airport->longitude !== null)
  @section('head')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  @endsection
@endif

@section('content')
<a class="back" href="{{ route('data.airports') }}">&larr; Semua bandara</a>

<article vocab="https://schema.org/" typeof="Airport">
  <div class="entity">
    <span class="code" property="iataCode">{{ $airport->iata_code ?? '—' }}</span>
    @if ($airport->in_asean)
      <span class="badge asean">ASEAN</span>
    @endif
    <h1 property="name">{{ $airport->label }}</h1>
    <p property="address" typeof="PostalAddress">
      @if ($airport->region)
        <span property="addressRegion">{{ $airport->region->label }}</span>,
      @endif
      <span property="addressCountry">{{ $airport->country?->label ?? 'Negara belum tersedia' }}</span>
    </p>
  </div>

  <div class="facts">
    <div class="fact"><div class="k">Kode ICAO</div><div class="v" property="icaoCode">{{ $airport->icao_code ?? '-' }}</div></div>
    <div class="fact"><div class="k">Kategori</div><div class="v">{{ $airport->category ?? '-' }}</div></div>
    <div class="fact"><div class="k">Status</div><div class="v">{{ $airport->status ?? '-' }}</div></div>
    @if ($airport->latitude !== null && $airport->longitude !== null)
      <div class="fact"><div class="k">Koordinat</div><div class="v">{{ number_format((float) $airport->latitude, 4) }}, {{ number_format((float) $airport->longitude, 4) }}</div></div>
    @endif
    @if ($airport->elevation !== null)
      <div class="fact"><div class="k">Elevasi</div><div class="v">{{ number_format((float) $airport->elevation, 0, ',', '.') }} m</div></div>
    @endif
    @if ($airport->operator !== null)
      <div class="fact"><div class="k">Pengelola</div><div class="v">{{ $airport->operator }}</div></div>
    @endif
    @if ($airport->opened_year !== null)
      <div class="fact"><div class="k">Tahun dibuka</div><div class="v">{{ $airport->opened_year }}</div></div>
    @endif
    @if ($airport->annual_passengers !== null)
      <div class="fact"><div class="k">Penumpang per tahun</div><div class="v">{{ number_format($airport->annual_passengers, 0, ',', '.') }}</div></div>
    @endif
    @if ($airport->peak_annual_passengers !== null)
      <div class="fact"><div class="k">Puncak penumpang</div><div class="v">{{ number_format($airport->peak_annual_passengers, 0, ',', '.') }}</div></div>
    @endif
    @if ($airport->longest_runway_length !== null)
      <div class="fact"><div class="k">Landasan terpanjang</div><div class="v">{{ number_format((float) $airport->longest_runway_length, 0, ',', '.') }} m</div></div>
    @endif
    @if ($airport->longest_runway_width !== null)
      <div class="fact"><div class="k">Lebar landasan</div><div class="v">{{ number_format((float) $airport->longest_runway_width, 0, ',', '.') }} m</div></div>
    @endif
    @if ($airport->runway_count !== null)
      <div class="fact"><div class="k">Jumlah landasan</div><div class="v">{{ $airport->runway_count }}</div></div>
    @endif
    @if ($airport->runway_designation !== null)
      <div class="fact"><div class="k">Penamaan landasan</div><div class="v">{{ $airport->runway_designation }}</div></div>
    @endif
    @if ($airport->surface_material !== null)
      <div class="fact"><div class="k">Permukaan</div><div class="v">{{ $airport->surface_material }}</div></div>
    @endif
    @if ($airport->local_name !== null)
      <div class="fact"><div class="k">Nama lokal</div><div class="v">{{ $airport->local_name }}</div></div>
    @endif
    @if ($airport->distance_to_city_center_km !== null)
      <div class="fact"><div class="k">Jarak ke pusat kota</div><div class="v">{{ number_format((float) $airport->distance_to_city_center_km, 1, ',', '.') }} km</div></div>
    @endif
    @if ($airport->nearestAirport)
      <div class="fact">
        <div class="k">Bandara terdekat</div>
        <div class="v">
          @if ($airport->nearestAirport->iata_code)
            <a class="link" href="{{ route('data.airport', $airport->nearestAirport->iata_code) }}">{{ $airport->nearestAirport->label }}</a>
          @else
            {{ $airport->nearestAirport->label }}
          @endif
          @if ($airport->distance_to_nearest_airport_km !== null)
            ({{ number_format((float) $airport->distance_to_nearest_airport_km, 1, ',', '.') }} km)
          @endif
        </div>
      </div>
    @endif
    @if ($airport->number_of_destinations !== null)
      <div class="fact"><div class="k">Jumlah tujuan</div><div class="v">{{ $airport->number_of_destinations }} ({{ $airport->number_of_destinations_asean ?? 0 }} ASEAN, {{ $airport->number_of_destinations_outside_asean ?? 0 }} luar ASEAN)</div></div>
    @endif
  </div>

  <div class="linkrow">
    @if ($airport->website)
      <a class="link" href="{{ $airport->website }}" target="_blank" rel="noopener">Website resmi</a>
    @endif
    @if ($airport->dbpedia_url)
      <a class="link" href="{{ $airport->dbpedia_url }}" target="_blank" rel="noopener">DBpedia</a>
    @endif
    @if ($airport->wikidata_url)
      <a class="link" href="{{ $airport->wikidata_url }}" target="_blank" rel="noopener">Wikidata</a>
    @endif
    <a class="link" href="{{ route('data.routes', ['origin' => $airport->id]) }}">Semua rute keberangkatan</a>
  </div>

  @if ($airport->latitude !== null && $airport->longitude !== null)
    <div class="mapbox"><div id="peta" role="region" aria-label="Peta lokasi {{ $airport->label }}"></div></div>
  @endif
</article>

<h2 class="section">Maskapai yang melayani</h2>
@if ($airport->airlines->isEmpty() && $airport->hubAirlines->isEmpty())
  <div class="state">Belum ada data maskapai untuk bandara ini.</div>
@else
  <div class="scroll">
    <table>
      <thead><tr><th>Maskapai</th><th>Kode</th><th>Status di bandara</th></tr></thead>
      <tbody>
        @foreach ($airport->airlines as $airline)
          <tr>
            <td>{{ $airline->label }}</td>
            <td>{{ $airline->airline_code ?? '-' }}</td>
            <td>Beroperasi</td>
          </tr>
        @endforeach
        @foreach ($airport->hubAirlines as $airline)
          <tr>
            <td>{{ $airline->label }}</td>
            <td>{{ $airline->airline_code ?? '-' }}</td>
            <td><span class="badge asean">Hub</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<h2 class="section">Rute keberangkatan ({{ $departures->count() }})</h2>
@if ($departures->isEmpty())
  <div class="state">Belum ada data rute keberangkatan.</div>
@else
  <div class="scroll">
    <table>
      <thead><tr><th>Tujuan</th><th>Negara</th><th>Maskapai</th></tr></thead>
      <tbody>
        @foreach ($departures as $route)
          <tr>
            <td>
              @if ($route->destinationAirport->iata_code)
                <a class="link" href="{{ route('data.airport', $route->destinationAirport->iata_code) }}">{{ $route->destinationAirport->label }}</a>
                ({{ $route->destinationAirport->iata_code }})
              @else
                {{ $route->destinationAirport->label }}
              @endif
            </td>
            <td>{{ $route->destinationAirport->country?->label ?? '-' }}</td>
            <td>{{ $route->airline?->label ?? '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<h2 class="section">Rute kedatangan ({{ $arrivals->count() }})</h2>
@if ($arrivals->isEmpty())
  <div class="state">Belum ada data rute kedatangan.</div>
@else
  <div class="scroll">
    <table>
      <thead><tr><th>Asal</th><th>Negara</th><th>Maskapai</th></tr></thead>
      <tbody>
        @foreach ($arrivals as $route)
          <tr>
            <td>
              @if ($route->originAirport->iata_code)
                <a class="link" href="{{ route('data.airport', $route->originAirport->iata_code) }}">{{ $route->originAirport->label }}</a>
                ({{ $route->originAirport->iata_code }})
              @else
                {{ $route->originAirport->label }}
              @endif
            </td>
            <td>{{ $route->originAirport->country?->label ?? '-' }}</td>
            <td>{{ $route->airline?->label ?? '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection

@if ($airport->latitude !== null && $airport->longitude !== null)
  @push('scripts')
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <script>
  (function () {
    var titik = [{{ (float) $airport->latitude }}, {{ (float) $airport->longitude }}];
    var peta = L.map('peta').setView(titik, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(peta);
    L.marker(titik).addTo(peta).bindPopup(@json($airport->label));
  })();
  </script>
  @endpush
@endif
