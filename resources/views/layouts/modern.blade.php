<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'BANDARA ASEAN')</title>
@yield('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('bodyclass')">
@include('partials.ui.icons')

{{-- latar: mesh gradient + glow blob + grid halus --}}
<div class="bx-bg" aria-hidden="true">
  <div class="bx-blob bx-blob-a"></div>
  <div class="bx-blob bx-blob-b"></div>
  <div class="bx-blob bx-blob-c"></div>
  <div class="bx-grid"></div>
</div>
<div id="bar" class="bx-progress" aria-hidden="true"></div>

<header class="bx-nav">
  <div class="bx-wrap flex h-16 items-center gap-4">
    <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-[15px] font-extrabold tracking-tight" aria-label="BANDARA ASEAN, beranda">
      <span class="bx-logo"><svg class="size-4 -rotate-45" aria-hidden="true"><use href="#i-plane"/></svg></span>
      <span>Bandara <span class="text-cyan-accent">ASEAN</span></span>
    </a>

    <nav class="ml-3 hidden items-center gap-1 md:flex" aria-label="Menu utama">
      <a class="bx-navlink" href="{{ route('home') }}" @if(request()->routeIs('home', 'search')) aria-current="page" @endif>Cari</a>
      <a class="bx-navlink" href="{{ route('bandara.index') }}" @if(request()->routeIs('bandara.*')) aria-current="page" @endif>Jelajahi</a>
      <a class="bx-navlink" href="{{ route('route.index') }}" @if(request()->routeIs('route.index')) aria-current="page" @endif>Rute</a>
      <a class="bx-navlink" href="{{ route('data.airports') }}" @if(request()->routeIs('data.*')) aria-current="page" @endif>Data</a>
    </nav>

    <div class="ml-auto flex items-center gap-2">
      @unless(request()->routeIs('home'))
        <a href="{{ route('home') }}" class="bx-chip hidden sm:inline-flex" data-focus-search>
          <svg class="size-4" aria-hidden="true"><use href="#i-search"/></svg>
          <span>Cari bandara</span>
          <kbd class="bx-kbd" data-kbd>Ctrl K</kbd>
        </a>
      @endunless
      <button id="menuBtn" type="button" class="bx-chip w-9 justify-center !px-0 md:hidden" aria-label="Buka menu" aria-expanded="false" aria-controls="mobileMenu">
        <svg class="size-[18px]" aria-hidden="true"><use href="#i-menu"/></svg>
      </button>
    </div>
  </div>

  <div id="mobileMenu" hidden class="border-t border-line md:hidden">
    <nav class="bx-wrap flex flex-col py-2" aria-label="Menu seluler">
      <a class="bx-navlink" href="{{ route('home') }}">Cari</a>
      <a class="bx-navlink" href="{{ route('bandara.index') }}">Jelajahi</a>
      <a class="bx-navlink" href="{{ route('route.index') }}">Rute</a>
      <a class="bx-navlink" href="{{ route('data.airports') }}">Data</a>
    </nav>
  </div>
</header>

<main>@yield('content')</main>

<footer class="border-t border-line">
  <div class="bx-wrap py-6 text-[13px] leading-relaxed text-faint">
    BANDARA ASEAN &middot; Tugas besar Web Semantik<br>
    Foto: Wikipedia dan Wikimedia Commons &middot; Peta: OpenStreetMap
  </div>
</footer>

<div class="bx-legacy">@include('partials.chat')</div>
@stack('scripts')
</body>
</html>
