{{--
  Kotak cari kaca dengan glow, tombol hapus, dan kbd ⌘K / Ctrl K.
  Parameter: $q (nilai awal), $autofocus (bool), $live (bool, cari sambil mengetik).
  Perilaku ada di resources/js/app.js, dipasang lewat atribut data-*.
--}}
<form action="{{ route('search') }}" method="get" role="search" autocomplete="off"
      data-search-form @if(!empty($live)) data-live @endif>
  <div id="searchBox" class="bx-search" @if(!empty($q)) data-filled @endif>
    <div class="bx-search-inner">
      <svg class="bx-search-icon size-6 shrink-0" aria-hidden="true"><use href="#i-search"/></svg>
      <label for="q" class="sr-only">Kata kunci pencarian</label>
      <input id="q" name="q" type="search" value="{{ $q ?? '' }}" class="bx-input"
             placeholder="Cari bandara atau kode IATA" enterkeyhint="search" spellcheck="false"
             @if(!empty($autofocus)) autofocus @endif>
      <button id="clearBtn" type="button" class="bx-clear" aria-label="Hapus pencarian" @if(empty($q)) hidden @endif>
        <svg class="size-4" aria-hidden="true"><use href="#i-x"/></svg>
      </button>
      <kbd class="bx-kbd hidden sm:inline-block" data-kbd>Ctrl K</kbd>
      <button type="submit" class="bx-btn" aria-label="Cari">
        <svg class="size-[18px]" aria-hidden="true"><use href="#i-search"/></svg>
        <span class="hidden sm:inline">Cari</span>
      </button>
    </div>
  </div>
</form>
