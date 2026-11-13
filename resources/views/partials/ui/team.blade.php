{{-- daftar anggota tim, isinya diatur di config/tim.php (versi Tailwind dari partials/tim) --}}
<section class="bx-wrap border-t border-line pb-16 pt-14 text-center" aria-labelledby="judul-tim">
  <h2 id="judul-tim" class="text-xl font-bold tracking-tight">Tim pengembang</h2>
  <p class="mt-1 text-sm text-mute">Tugas besar Web Semantik, Universitas Sumatera Utara</p>
  <ul class="mt-8 flex flex-wrap justify-center gap-x-9 gap-y-8">
    @foreach(config('tim', []) as $i => $t)
      @php
        $ada = ! empty($t['foto']) && file_exists(public_path($t['foto']));
        $kata = preg_split('/\s+/u', trim($t['nama']), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $inisial = mb_strtoupper(mb_substr($kata[0], 0, 1).(isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
      @endphp
      <li class="flex w-32 flex-col items-center gap-0.5">
        @if($ada)
          <img src="{{ asset($t['foto']) }}" alt="Foto {{ $t['nama'] }}" loading="lazy"
               class="mb-2.5 size-[88px] rounded-full border border-glass-line object-cover p-0.5">
        @else
          <span class="bx-iata mb-2.5 !size-[88px] !rounded-full !text-2xl !tracking-normal" aria-hidden="true">{{ $inisial }}</span>
        @endif
        <strong class="text-[15px] leading-snug">{{ $t['nama'] }}</strong>
        @if(! empty($t['nim']))<span class="font-mono text-xs text-faint">{{ $t['nim'] }}</span>@endif
        @if(! empty($t['peran']))<span class="bx-tag mt-1.5">{{ $t['peran'] }}</span>@endif
      </li>
    @endforeach
  </ul>
</section>
