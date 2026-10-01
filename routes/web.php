<?php

use App\Http\Controllers\AirportController;
use App\Http\Controllers\RouteAirportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AirportController::class, 'home'])->name('home');
Route::get('/cari', [AirportController::class, 'search'])->name('search');
Route::get('/bandara/{iata}', [AirportController::class, 'show'])->name('airport.show');
Route::get('/rute', [RouteAirportController::class, 'index'])->name('route.index');
