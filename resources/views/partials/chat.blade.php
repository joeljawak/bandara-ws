{{-- chat box tanya jawab bandara, tampil di pojok kanan bawah semua halaman --}}
<style>
.chat-fab{position:fixed;right:22px;bottom:22px;z-index:60;display:flex;align-items:center;gap:10px;height:54px;padding:0 20px 0 16px;border:0;border-radius:999px;background:var(--blue);color:#fff;font:600 15px var(--font-body);cursor:pointer;box-shadow:0 6px 20px rgba(29,63,158,.35);transition:background .15s,transform .15s}
.chat-fab:hover{background:var(--blue-dark);transform:translateY(-1px)}
.chat-fab.is-open{display:none}
.chat-panel{position:fixed;right:22px;bottom:22px;z-index:61;width:372px;max-width:calc(100vw - 24px);height:540px;max-height:calc(100vh - 44px);display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 12px 40px rgba(31,36,51,.22);overflow:hidden}
.chat-panel[hidden]{display:none}
.chat-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px 12px;border-bottom:1px solid var(--line);position:relative}
.chat-head::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,var(--blue) 0 34%,var(--red) 34% 67%,var(--yellow) 67% 100%)}
.chat-head strong{display:block;font-size:15px;line-height:1.3}
.chat-head span{display:block;color:var(--muted);font-size:12px}
.chat-head-actions{display:flex;align-items:center;gap:4px}
.chat-refresh{width:32px;height:32px;border:0;border-radius:50%;background:transparent;color:var(--muted);font-size:16px;line-height:1;cursor:pointer;display:grid;place-items:center;transition:color .15s,background .15s,transform .25s ease}
.chat-refresh:hover{background:var(--soft);color:var(--blue);transform:rotate(90deg)}
.chat-refresh:active{transform:rotate(180deg)}
.chat-refresh svg{width:16px;height:16px}
.chat-close{width:32px;height:32px;border:0;border-radius:50%;background:transparent;color:var(--muted);font-size:22px;line-height:1;cursor:pointer;display:grid;place-items:center;transition:color .15s,background .15s}
.chat-close:hover{background:var(--soft);color:var(--ink)}
.chat-log{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#fff}
.msg{max-width:88%;padding:10px 14px;border-radius:16px;font-size:14.5px;line-height:1.55;word-wrap:break-word}
.msg-bot{align-self:flex-start;background:var(--soft);border-bottom-left-radius:5px;color:var(--ink)}
.msg-bot strong{font-weight:600;color:var(--ink)}
.msg-user{align-self:flex-end;background:var(--blue);color:#fff;border-bottom-right-radius:5px}
.msg.is-wait{color:var(--muted)}
.chat-items{display:flex;flex-direction:column;gap:6px;margin-top:10px}
.chat-item{display:block;background:#fff;border:1px solid var(--line);border-radius:10px;padding:8px 12px;transition:border-color .15s,background .15s;text-decoration:none}
.chat-item:hover{border-color:var(--blue);background:var(--blue-soft)}
.chat-item b{display:block;color:var(--blue);font-size:14px;line-height:1.35}
.chat-item small{display:block;color:var(--muted);font-size:12.5px}
.chat-chips{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start}
.chat-chip{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:999px;padding:5px 13px;font:500 13px var(--font-body);cursor:pointer;transition:border-color .15s,color .15s,background .15s}
.chat-chip:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-soft)}
.chat-form{display:flex;gap:8px;padding:12px;border-top:1px solid var(--line);background:#fff}
.chat-form input{flex:1;min-width:0;height:44px;border:1px solid #d5dae5;border-radius:999px;padding:0 16px;font:inherit;font-size:14.5px;color:var(--ink)}
.chat-form input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(29,63,158,.14)}
.chat-form button{flex:none;width:44px;height:44px;border:0;border-radius:50%;background:var(--blue);color:#fff;cursor:pointer;display:grid;place-items:center}
.chat-form button:hover{background:var(--blue-dark)}
@media(max-width:560px){
  .chat-form input{font-size:16px}
  .chat-fab{right:14px;bottom:14px}
  .chat-panel{right:12px;bottom:12px;height:72vh}
}
</style>

<div id="chat" data-url="{{ route('chat.ask') }}">
  <button type="button" class="chat-fab" aria-expanded="false" aria-controls="chat-panel">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>
    Tanya
  </button>

  <section class="chat-panel" id="chat-panel" role="dialog" aria-label="Tanya Bandara ASEAN" hidden>
    <div class="chat-head">
      <div><strong>Tanya Bandara ASEAN</strong><span>Didukung Gemini AI & Data Bandara</span></div>
      <div class="chat-head-actions">
        <button type="button" class="chat-refresh" aria-label="Mulai percakapan baru" title="Mulai percakapan baru (Reset Chat)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        </button>
        <button type="button" class="chat-close" aria-label="Tutup chat">&times;</button>
      </div>
    </div>
    <div class="chat-log" aria-live="polite">
      <div class="msg msg-bot">Halo! Aku asisten AI informasi bandara di Asia Tenggara. Coba salah satu pertanyaan ini, atau ketik langsung pertanyaanmu:</div>
      <div class="chat-chips">
        @foreach(\App\Services\ChatService::contoh() as $c)
          <button type="button" class="chat-chip">{{ $c }}</button>
        @endforeach
      </div>
    </div>
    <form class="chat-form" autocomplete="off">
      <input type="text" maxlength="200" placeholder="Tulis pertanyaan..." aria-label="Pertanyaan tentang bandara">
      <button type="submit" aria-label="Kirim">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
      </button>
    </form>
  </section>
</div>

<script>
(function () {
  var root = document.getElementById('chat');
  if (!root) return;
  var fab = root.querySelector('.chat-fab');
  var panel = root.querySelector('.chat-panel');
  var log = root.querySelector('.chat-log');
  var form = root.querySelector('.chat-form');
  var input = form.querySelector('input');
  var busy = false;

  // Riwayat percakapan untuk konteks Gemini multi-turn
  var history = [];
  var csrfToken = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfToken ? csrfToken.getAttribute('content') : '';

  var salamAwal = 'Halo! Aku asisten AI informasi bandara di Asia Tenggara. Coba salah satu pertanyaan ini, atau ketik langsung pertanyaanmu:';

  function toggle(show) {
    panel.hidden = !show;
    fab.classList.toggle('is-open', show);
    fab.setAttribute('aria-expanded', show ? 'true' : 'false');
    if (show) { input.focus(); down(); } else { fab.focus(); }
  }

  function resetChat() {
    history = [];
    log.innerHTML = '';
    bubble('bot', 'Obrolan telah di-reset. ' + salamAwal);
    var chipRow = document.createElement('div');
    chipRow.className = 'chat-chips';
    @foreach(\App\Services\ChatService::contoh() as $c)
    (function(t) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chat-chip';
      b.textContent = t;
      chipRow.appendChild(b);
    })({!! json_encode($c) !!});
    @endforeach
    log.appendChild(chipRow);
    down();
  }

  function down() { log.scrollTop = log.scrollHeight; }

  function escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatText(text) {
    var escaped = escapeHtml(text);
    // Bold **teks**
    escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // Italic *teks*
    escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');
    // Bullet lines: * item or - item
    escaped = escaped.replace(/(?:^|\n)[*-]\s+(.+)/g, '<br>&bull; $1');
    // Newlines
    escaped = escaped.replace(/\n/g, '<br>');
    return escaped;
  }

  function bubble(role, text) {
    var d = document.createElement('div');
    d.className = 'msg msg-' + role;
    if (role === 'bot') {
      d.innerHTML = formatText(text);
    } else {
      d.textContent = text;
    }
    log.appendChild(d);
    down();
    return d;
  }

  function items(parent, list) {
    if (!list.length) return;
    var box = document.createElement('div');
    box.className = 'chat-items';
    list.forEach(function (it) {
      var a = document.createElement('a');
      a.className = 'chat-item';
      a.href = it.url;
      var b = document.createElement('b');
      b.textContent = it.judul;
      var s = document.createElement('small');
      s.textContent = it.sub;
      a.appendChild(b);
      a.appendChild(s);
      box.appendChild(a);
    });
    parent.appendChild(box);
  }

  function chips(list) {
    var old = log.querySelectorAll('.chat-chips');
    old.forEach(function (o) { o.remove(); });
    if (!list.length) return;
    var row = document.createElement('div');
    row.className = 'chat-chips';
    list.forEach(function (t) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chat-chip';
      b.textContent = t;
      row.appendChild(b);
    });
    log.appendChild(row);
    down();
  }

  function ask(q) {
    q = q.trim();
    if (!q || busy) return;
    busy = true;
    log.querySelectorAll('.chat-chips').forEach(function (o) { o.remove(); });
    bubble('user', q);
    var wait = bubble('bot', 'Sedang mengetik...');
    wait.classList.add('is-wait');

    // Kirim via POST dengan riwayat percakapan untuk multi-turn context
    fetch(root.dataset.url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify({ q: q, history: history })
    })
      .then(function (r) {
        if (r.ok) return r.json();
        var err = new Error(r.status);
        err.status = r.status;
        return r.json().catch(function () { return {}; }).then(function (j) { err.pesan = j.message; throw err; });
      })
      .then(function (d) {
        wait.remove();
        var b = bubble('bot', d.text);
        items(b, d.items || []);
        chips(d.saran || []);

        // Simpan ke riwayat untuk konteks percakapan selanjutnya
        history.push({ role: 'user', text: q });
        history.push({ role: 'bot', text: d.text });

        // Simpan maksimal 12 giliran terakhir
        if (history.length > 12) {
          history = history.slice(-12);
        }
      })
      .catch(function (e) {
        wait.remove();
        var teks = 'Maaf, ada gangguan jaringan. Coba lagi sebentar ya.';
        if (e && e.status === 429) teks = 'Pertanyaannya terlalu cepat. Tunggu beberapa detik lalu coba lagi.';
        else if (e && e.status === 503) teks = e.pesan || 'Layanan data bandara sedang sibuk. Coba lagi sebentar.';
        bubble('bot', teks);
      })
      .then(function () { busy = false; down(); });
  }

  fab.addEventListener('click', function () { toggle(true); });
  root.querySelector('.chat-close').addEventListener('click', function () { toggle(false); });
  root.querySelector('.chat-refresh').addEventListener('click', function () { resetChat(); });
  panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var q = input.value;
    if (busy || !q.trim()) return;
    input.value = '';
    ask(q);
  });
  log.addEventListener('click', function (e) {
    var c = e.target.closest('.chat-chip');
    if (c) ask(c.textContent);
  });
})();
</script>
