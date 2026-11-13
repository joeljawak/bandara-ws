{{--
  Keadaan kosong. Parameter: $title, $text, $actions (opsional, HTML), $id (opsional)
  Dipakai untuk: tidak ada hasil, kata kunci kosong, galat Fuseki, dan filter tanpa hasil.
--}}
<div @isset($id) id="{{ $id }}" @endisset @if(!empty($hidden)) hidden @endif
     class="bx-glass flex flex-col items-center gap-4 rounded-3xl px-6 py-14 text-center">
  <svg viewBox="0 0 160 120" class="h-28 w-40" fill="none" aria-hidden="true">
    <circle cx="80" cy="62" r="52" stroke="var(--line)" stroke-width="1.5"/>
    <circle cx="80" cy="62" r="36" stroke="var(--glass-line)" stroke-width="1.5" stroke-dasharray="3 5"/>
    <circle cx="80" cy="62" r="20" stroke="var(--brand)" stroke-opacity=".55" stroke-width="1.5"/>
    <path d="M18 92c26-6 40-34 62-30s38 22 64-6" stroke="var(--cyan)" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="2 6"/>
    <circle cx="80" cy="62" r="4" fill="var(--cyan)"/>
    <g transform="translate(100 24) rotate(40)"><use href="#i-plane" width="22" height="22" fill="var(--brand)"/></g>
  </svg>
  <h3 class="text-lg font-bold tracking-tight">{{ $title }}</h3>
  <p class="max-w-md text-pretty text-sm leading-relaxed text-mute">{{ $text }}</p>
  @isset($actions)
    <div class="flex flex-wrap items-center justify-center gap-2">{!! $actions !!}</div>
  @endisset
</div>
