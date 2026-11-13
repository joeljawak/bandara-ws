@extends('layouts.app')
@section('bodyclass', 'is-home')
@section('title', 'BANDARA ASEAN — Cari Bandara, Maskapai, dan Rute Asia Tenggara')
@php
    $stats = $stats ?? ['airports' => 0, 'airlines' => 0, 'routes' => 0, 'countries' => 0];
    $seo = [
        'title' => 'BANDARA ASEAN — Cari Bandara, Maskapai, dan Rute Asia Tenggara',
        'description' => 'Cari 617 bandara Asia Tenggara lewat nama, kode IATA, kota, alias populer, atau pencarian di sekitar lokasi. Dilengkapi rute, maskapai, peta, dan data RDF.',
        'canonical' => route('home'),
        'jsonld' => [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'BANDARA ASEAN',
            'url' => route('home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('search').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ];
@endphp
@section('content')
<section class="hero" aria-labelledby="judul-beranda">
  <div class="hero-inner">
    <span class="hero-eyebrow">
      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/></svg>
      Jelajahi bandara Asia Tenggara
    </span>
    <div class="logo-dots" aria-hidden="true"><i></i><i></i><i></i></div>
    <h1 class="logo" id="judul-beranda"><span class="b1">BANDARA</span> <span class="b2">ASEAN</span></h1>
    <p class="tagline">Cari bandara, maskapai, dan rute penerbangan dalam satu tempat.</p>
    @include('partials.searchbox', ['autofocus' => true])
    <div class="chips">
      <span>Coba:</span>
      <a class="chip" href="{{ route('search', ['q' => 'Kualanamu']) }}">Kualanamu</a>
      <a class="chip" href="{{ route('search', ['q' => 'Changi']) }}">Changi</a>
      <a class="chip" href="{{ route('search', ['q' => 'CGK']) }}">CGK</a>
      <a class="chip" href="{{ route('search', ['q' => 'Garuda']) }}">Garuda</a>
      <a class="chip" href="{{ route('search', ['q' => 'Suvarnabhumi']) }}">Suvarnabhumi</a>
    </div>
    <div class="hero-stats">
      <span><strong>{{ number_format($stats['airports'], 0, ',', '.') }}</strong> bandara</span>
      <span><strong>{{ number_format($stats['airlines'], 0, ',', '.') }}</strong> maskapai</span>
      <span><strong>{{ number_format($stats['routes'], 0, ',', '.') }}</strong> rute</span>
      <span><strong>{{ number_format($stats['countries'], 0, ',', '.') }}</strong> negara</span>
    </div>
  </div>
</section>

@include('partials.tim')
@endsection
