@extends('layouts.app')
@section('bodyclass', 'is-home')
@section('content')
<section class="home">
  <div class="logo-dots" aria-hidden="true"><i></i><i></i><i></i></div>
  <h1 class="logo"><span class="b1">BANDARA</span> <span class="b2">ASEAN</span></h1>
  <p class="tagline">Cari bandara di Asia Tenggara</p>
  @include('partials.searchbox', ['autofocus' => true])
  <div class="chips">
    <span>Coba:</span>
    <a class="chip" href="{{ route('search', ['q' => 'Kualanamu']) }}">Kualanamu</a>
    <a class="chip" href="{{ route('search', ['q' => 'Soekarno-Hatta']) }}">Soekarno-Hatta</a>
    <a class="chip" href="{{ route('search', ['q' => 'Changi']) }}">Changi</a>
    <a class="chip" href="{{ route('search', ['q' => 'Suvarnabhumi']) }}">Suvarnabhumi</a>
  </div>
</section>
@endsection
