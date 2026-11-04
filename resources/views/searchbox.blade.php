{{-- kotak cari berbentuk pil, dipakai di beranda dan hasil pencarian --}}
<form class="search" action="{{ route('search') }}" method="get" role="search">
  <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Cari nama bandara, kota, atau kode IATA" aria-label="Kata kunci pencarian" required @if(!empty($autofocus)) autofocus @endif>
  <button type="submit" aria-label="Cari">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
  </button>
</form>
