{{--
  Satu kartu hasil. Parameter:
    $r   baris dari SparqlService::search() (iata, nama, kota, negara, icao, lat, lon, kategori)
    $i   urutan, dipakai untuk animasi muncul bertahap
    $hl  closure penyorot kata kunci (opsional)
    $foto (opsional) ['url' => ...] dari AirportImageService
  Kolom tambahan seperti $r['destinasi'] atau $r['maskapai'] ikut tampil kalau Anda
  menambahkannya ke query SPARQL.
--}}
@php
    $hl ??= fn ($t) => e((string) $t);
    $kategori = $r['kategori'] ?? null;
    $isIntl = $kategori && str_contains(mb_strtolower($kategori), 'internasional');
    $koordinat = (isset($r['lat'], $r['lon']) && is_numeric($r['lat']) && is_numeric($r['lon']))
        ? number_format(abs((float) $r['lat']), 2).'°'.((float) $r['lat'] >= 0 ? 'N' : 'S').' '.number_format(abs((float) $r['lon']), 2).'°'.((float) $r['lon'] >= 0 ? 'E' : 'W')
        : null;
@endphp
<a href="{{ route('bandara.show', ['iata' => $r['iata']]) }}"
   class="bx-card group"
   style="--i: {{ $i ?? 0 }}"
   data-negara="{{ $r['negara'] ?? '' }}"
   data-kategori="{{ mb_strtolower($kategori ?? '') }}">
  <div class="flex items-start gap-4">
    <span class="bx-iata">{!! $hl($r['iata']) !!}</span>
    <div class="min-w-0 flex-1">
      <h3 class="text-[15px] font-semibold leading-snug text-ink">{!! $hl($r['nama']) !!}</h3>
      <p class="mt-1 flex items-center gap-1.5 text-sm text-mute">
        <svg class="size-3.5 shrink-0 text-faint" aria-hidden="true"><use href="#i-pin"/></svg>
        <span class="min-w-0 truncate">
          @if(!empty($r['kota'])){!! $hl($r['kota']) !!} &middot; @endif{!! $hl($r['negara'] ?? '') !!}
        </span>
      </p>
    </div>
    @if(!empty($foto['url']))
      <img src="{{ $foto['url'] }}" alt="" loading="lazy" class="hidden h-12 w-[4.5rem] shrink-0 rounded-lg border border-line object-cover sm:block">
    @else
      <svg class="size-4 shrink-0 text-faint transition duration-300 group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-cyan-accent" aria-hidden="true"><use href="#i-arrow"/></svg>
    @endif
  </div>

  <div class="bx-tags">
    @if($kategori)
      <span class="bx-tag {{ $isIntl ? 'bx-tag-intl' : '' }}">{{ ucfirst($kategori) }}</span>
    @endif
    @if(!empty($r['icao']))
      <span class="bx-tag font-mono">{{ $r['icao'] }}</span>
    @endif
    @if($koordinat)
      <span class="bx-tag font-mono">{{ $koordinat }}</span>
    @endif
    @if(!empty($r['destinasi']))
      <span class="bx-tag"><svg class="size-3" aria-hidden="true"><use href="#i-plane"/></svg>{{ $r['destinasi'] }} tujuan</span>
    @endif
    @if(!empty($r['maskapai']))
      <span class="bx-tag"><svg class="size-3" aria-hidden="true"><use href="#i-layers"/></svg>{{ $r['maskapai'] }} maskapai</span>
    @endif
  </div>
</a>
