{{-- satu hasil: nama, lokasi, kode IATA, dan foto asli (atau gambar buatan kalau tidak ada foto) --}}
@php($foto = $fotos[$r['nama']] ?? null)
<a class="res" href="{{ route('bandara.show', ['iata' => $r['iata']]) }}">
  <span>
    <div class="res-top"><span class="code">{{ $r['iata'] }}</span></div>
    <div class="res-name">{{ $r['nama'] }}</div>
    <div class="res-sub">{{ $sub }}</div>
  </span>
  <span class="thumb" aria-hidden="true">
    @if($foto)
      <img src="{{ $foto['url'] }}" alt="" loading="lazy">
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
