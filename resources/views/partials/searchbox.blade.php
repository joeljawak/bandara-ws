{{-- kotak cari berbentuk pil dengan dropdown saran aksesibel (tanpa library) --}}
<form class="search" action="{{ route('search') }}" method="get" role="search"
      data-autocomplete data-saran-url="{{ route('search.suggestions') }}">
  <div class="ac-wrap">
    <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Cari nama bandara, kota, kode IATA, atau maskapai"
           aria-label="Kata kunci pencarian" autocomplete="off" role="combobox"
           aria-expanded="false" aria-autocomplete="list" aria-controls="saran-pencarian"
           @if(!empty($autofocus)) autofocus @endif>
    <ul class="ac-list" id="saran-pencarian" role="listbox" aria-label="Saran pencarian" hidden></ul>
  </div>
  <button type="submit" aria-label="Cari">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
  </button>
</form>

@push('scripts')
<script>
(function () {
  var form = document.querySelector('form[data-autocomplete]');
  if (!form) { return; }

  var input = form.querySelector('input[name="q"]');
  var list = form.querySelector('.ac-list');
  var url = form.dataset.saranUrl;
  var items = [];
  var aktif = -1;
  var timer = null;
  var urutan = 0;

  function tutup() {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    aktif = -1;
  }

  function pilih(i) {
    if (!items[i]) { return; }
    window.location.href = items[i].url;
  }

  function gambar(data) {
    list.innerHTML = '';
    items = data;
    aktif = -1;

    if (!data.length) {
      tutup();
      return;
    }

    data.forEach(function (item, i) {
      var li = document.createElement('li');
      li.setAttribute('role', 'presentation');

      var tombol = document.createElement('button');
      tombol.type = 'button';
      tombol.className = 'ac-item';
      tombol.id = 'saran-' + i;
      tombol.setAttribute('role', 'option');
      tombol.setAttribute('aria-selected', 'false');

      var judul = document.createElement('span');
      judul.className = 'judul';
      judul.textContent = item.judul;
      var sub = document.createElement('span');
      sub.className = 'sub';
      sub.textContent = item.sub || '';

      tombol.appendChild(judul);
      if (item.sub) { tombol.appendChild(sub); }
      tombol.addEventListener('mousedown', function (e) { e.preventDefault(); pilih(i); });
      tombol.addEventListener('click', function () { pilih(i); });

      li.appendChild(tombol);
      list.appendChild(li);
    });

    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  }

  function sorot(i) {
    var opsi = list.querySelectorAll('.ac-item');
    if (!opsi.length) { return; }
    if (i < 0) { i = opsi.length - 1; }
    if (i >= opsi.length) { i = 0; }
    opsi.forEach(function (el) { el.setAttribute('aria-selected', 'false'); });
    opsi[i].setAttribute('aria-selected', 'true');
    opsi[i].scrollIntoView({ block: 'nearest' });
    aktif = i;
    input.setAttribute('aria-activedescendant', opsi[i].id);
  }

  function cari() {
    var q = input.value.trim();
    if (q.length < 2) { tutup(); return; }

    var nomor = ++urutan;
    var kontrol = window.AbortController ? new AbortController() : null;
    var opsi = kontrol ? { signal: kontrol.signal } : undefined;

    fetch(url + '?q=' + encodeURIComponent(q), opsi)
      .then(function (res) { return res.ok ? res.json() : { data: [] }; })
      .then(function (json) {
        if (nomor !== urutan) { return; }
        gambar(json.data || []);
      })
      .catch(function () {
        if (nomor === urutan) { tutup(); }
      });
  }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(cari, 220);
  });

  input.addEventListener('keydown', function (e) {
    if (list.hidden) { return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); sorot(aktif + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); sorot(aktif - 1); }
    else if (e.key === 'Enter' && aktif >= 0) { e.preventDefault(); pilih(aktif); }
    else if (e.key === 'Escape') { tutup(); }
  });

  input.addEventListener('blur', function () { setTimeout(tutup, 120); });
})();
</script>
@endpush
