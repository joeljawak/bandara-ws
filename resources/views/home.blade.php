@extends('layouts.app')
@section('content')
<section class="intro">
  <h1>Cari bandara di Indonesia</h1>
  <p class="lead">Ketik nama bandara, kota, atau kode IATA untuk melihat informasi dan jadwal penerbangannya.</p>
  <form class="searchrow" action="{{ route('search') }}" method="get">
    <input type="search" name="q" placeholder="mis. Kualanamu, Medan, KNO" aria-label="Kata kunci pencarian" required autofocus>
    <button type="submit">Cari</button>
  </form>
  <div class="chips">
    <span>Coba:</span>
    <a class="chip" href="{{ route('search', ['q' => 'Kualanamu']) }}">Kualanamu</a>
    <a class="chip" href="{{ route('search', ['q' => 'Soekarno-Hatta']) }}">Soekarno-Hatta</a>
    <a class="chip" href="{{ route('search', ['q' => 'Denpasar']) }}">Denpasar</a>
    <a class="chip" href="{{ route('search', ['q' => 'Makassar']) }}">Makassar</a>
  </div>
</section>
@endsection
