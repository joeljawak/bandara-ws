@extends('layouts.app')
@section('title', 'Data maskapai - BANDARA ASEAN')

@section('content')
<div class="page-head">
  <h1>Data maskapai</h1>
  <span class="count">{{ number_format($total, 0, ',', '.') }} maskapai di database relasional</span>
</div>

<form class="filter wide" method="get" action="{{ route('data.airlines') }}">
  <div class="field">
    <label for="q">Cari maskapai</label>
    <input id="q" type="text" name="q" value="{{ $search }}" placeholder="Nama atau kode maskapai">
  </div>
  <button class="btn" type="submit">Cari</button>
</form>

@if ($airlines->isEmpty())
  <div class="state">Tidak ada maskapai yang cocok.</div>
@else
  <div class="scroll" style="margin-top:20px">
    <table>
      <thead>
        <tr>
          <th>Maskapai</th>
          <th>Kode</th>
          <th>Hub utama</th>
          <th>Bandara dilayani</th>
          <th>Rute</th>
          <th>Tautan</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($airlines as $airline)
          <tr>
            <td>{{ $airline->label }}</td>
            <td>{{ $airline->airline_code ?? '-' }}</td>
            <td>
              @if ($airline->hubAirport)
                @if ($airline->hubAirport->iata_code)
                  <a class="link" href="{{ route('data.airport', $airline->hubAirport->iata_code) }}">{{ $airline->hubAirport->label }}</a>
                  ({{ $airline->hubAirport->iata_code }})
                @else
                  {{ $airline->hubAirport->label }}
                @endif
              @else
                -
              @endif
            </td>
            <td>{{ $airline->airports_count }}</td>
            <td>{{ $airline->routes_count }}</td>
            <td>
              <a class="link" href="{{ route('data.routes', ['airline' => $airline->id]) }}">Rute</a>
              @if ($airline->website)
                &middot; <a class="link" href="{{ $airline->website }}" target="_blank" rel="noopener">Website</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @include('data.partials.pager', ['paginator' => $airlines])
@endif
@endsection
