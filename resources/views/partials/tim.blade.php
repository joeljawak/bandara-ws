{{-- daftar anggota tim, isinya diatur di config/tim.php --}}
<section class="tim" aria-labelledby="judul-tim">
  <h2 id="judul-tim">Tim pengembang</h2>
  <p class="tim-sub">Tugas besar Web Semantik, Universitas Sumatera Utara</p>
  <ul class="tim-list">
    @foreach(config('tim', []) as $i => $t)
      @php
        $ada = ! empty($t['foto']) && file_exists(public_path($t['foto']));
        $kata = preg_split('/\s+/u', trim($t['nama']), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $inisial = mb_strtoupper(mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : ''));
      @endphp
      <li class="tim-item">
        @if($ada)
          <img class="tim-foto" src="{{ asset($t['foto']) }}" alt="Foto {{ $t['nama'] }}" loading="lazy">
        @else
          <span class="tim-foto tim-inisial c{{ $i % 3 }}" aria-hidden="true">{{ $inisial }}</span>
        @endif
        <strong>{{ $t['nama'] }}</strong>
        @if(! empty($t['nim']))<span class="tim-nim">{{ $t['nim'] }}</span>@endif
        @if(! empty($t['peran']))<span class="tim-peran">{{ $t['peran'] }}</span>@endif
      </li>
    @endforeach
  </ul>
</section>
