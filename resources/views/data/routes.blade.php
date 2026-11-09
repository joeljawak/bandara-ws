@extends('layouts.app')
@section('title', 'Data rute - BANDARA ASEAN')

@section('content')
<div class="page-head">
  <h1>Data rute</h1>
  <span class="count">{{ number_format($total, 0, ',', '.') }} rute di database relasional</span>
</div>

<form class="filter wide" method="get" action="{{ route('data.routes') }}">
  <div class="field">
    <label for="q">Cari</label>
    <input id="q" type="text" name="q" value="{{ $search }}" placeholder="Bandara atau maskapai">
  </div>
  <div class="field">
    <label for="origin">Asal</label>
    <select id="origin" name="origin">
      <option value="">Semua bandara</option>
      @foreach ($airports as $airport)
        <option value="{{ $airport->id }}" @selected($originId === $airport->id)>{{ $airport->iata_code }} - {{ $airport->label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="destination">Tujuan</label>
    <select id="destination" name="destination">
      <option value="">Semua bandara</option>
      @foreach ($airports as $airport)
        <option value="{{ $airport->id }}" @selected($destinationId === $airport->id)>{{ $airport->iata_code }} - {{ $airport->label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="airline">Maskapai</label>
    <select id="airline" name="airline">
      <option value="">Semua maskapai</option>
      @foreach ($airlines as $airline)
        <option value="{{ $airline->id }}" @selected($airlineId === $airline->id)>{{ $airline->label }}</option>
      @endforeach
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
</form>

@if ($routes->isEmpty())
  <div class="state">Tidak ada rute yang cocok.</div>
@else
  <div class="scroll" style="margin-top:20px">
    <table>
      <thead>
        <tr>
          <th>Asal</th>
          <th>Tujuan</th>
          <th>Maskapai</th>
          <th>Negara asal</th>
          <th>Negara tujuan</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($routes as $route)
          <tr>
            <td>
              @if ($route->originAirport->iata_code)
                <a class="link" href="{{ route('data.airport', $route->originAirport->iata_code) }}">{{ $route->originAirport->iata_code }}</a>
              @endif
              - {{ $route->originAirport->label }}
            </td>
            <td>
              @if ($route->destinationAirport->iata_code)
                <a class="link" href="{{ route('data.airport', $route->destinationAirport->iata_code) }}">{{ $route->destinationAirport->iata_code }}</a>
              @endif
              - {{ $route->destinationAirport->label }}
            </td>
            <td>{{ $route->airline?->label ?? '-' }}</td>
            <td>{{ $route->originAirport->country?->label ?? '-' }}</td>
            <td>{{ $route->destinationAirport->country?->label ?? '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @include('data.partials.pager', ['paginator' => $routes])
@endif
@endsection
