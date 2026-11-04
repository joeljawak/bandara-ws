<?php

use App\Http\Controllers\AirportController;
use App\Http\Controllers\RouteAirportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BandaraController;

Route::get('/bandara', [BandaraController::class, 'index'])->name('bandara.index');
Route::get('/bandara/{iata}', [BandaraController::class, 'show'])
    ->where('iata', '[A-Za-z]{3}')
    ->name('bandara.show');

Route::get('/', [AirportController::class, 'home'])->name('home');
Route::get('/cari', [AirportController::class, 'search'])->name('search');
Route::get('/rute', [RouteAirportController::class, 'index'])->name('route.index');
