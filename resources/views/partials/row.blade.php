{{-- satu hasil: nama, lokasi, kode IATA, lalu foto asli. Urutan gambar: foto, citra satelit, gambar buatan --}}
@php
    $foto = $fotos[$r['nama']] ?? null;
    $sat = (isset($r['lat'], $r['lon']) && is_numeric($r['lat']) && is_numeric($r['lon']))
        ? \App\Support\Satellite::thumb((float) $r['lat'], (float) $r['lon'])
        : null;
    $token = $token ?? [];
    $namaTampil = $token ? app(\App\Services\AirportSearch::class)->highlight($r['nama'], $token) : e($r['nama']);
    $subTampil = $sub ?? collect([$r['kota'] ?? null, $r['negara'] ?? null])->filter()->implode(', ');
    $jarak = $r['jarak_km'] ?? null;
@endphp
<a class="res" href="{{ route('bandara.show', ['iata' => $r['iata']]) }}">
  <span>
    <div class="res-top">
      <span class="code">{{ $r['iata'] }}</span>
      @if(!empty($r['kategori']))<span>{{ $r['kategori'] }}</span>@endif
      @if(!empty($r['negara']))<span>{{ $r['negara'] }}</span>@endif
    </div>
    <div class="res-name">{!! $namaTampil !!}</div>
    <div class="res-sub">{{ $subTampil }}</div>
    <div class="res-meta">
      @if($jarak !== null)<span class="pill dist">± {{ number_format($jarak, 0, ',', '.') }} km</span>@endif
      @if(!empty($r['rute_count']))<span class="pill">{{ number_format((int) $r['rute_count'], 0, ',', '.') }} rute</span>@endif
      @if(!empty($r['penumpang']))<span class="pill">{{ number_format((int) $r['penumpang'], 0, ',', '.') }} penumpang/tahun</span>@endif
      @if(!empty($r['in_asean']))
        <span class="pill asean">ASEAN</span>
      @endif
    </div>
  </span>
  <span class="thumb" aria-hidden="true">
    @if($foto)
      <img src="{{ $foto['url'] }}" alt="" loading="lazy">
    @elseif($sat)
      <img class="sat" src="{{ $sat['url'] }}" alt="" loading="lazy"
           style="width:{{ $sat['ukuran'] }}px;height:{{ $sat['ukuran'] }}px;margin:-{{ $sat['atas'] }}px 0 0 -{{ $sat['kiri'] }}px">
    @else
      @include('partials.art', ['a' => [
          'iata' => $r['iata'],
          'nama' => $r['nama'],
          'lat' => $r['lat'] ?? null,
          'lon' => $r['lon'] ?? null,
          'runway' => $r['runway'] ?? null,
          'penumpang' => $r['penumpang'] ?? null,
      ], 'mode' => 'thumb'])
    @endif
  </span>
</a>
