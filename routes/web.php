<?php

use App\Http\Controllers\AirportController;
use App\Http\Controllers\RouteAirportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BandaraController;
use App\Http\Controllers\ChatController;

Route::get('/bandara', [BandaraController::class, 'index'])->name('bandara.index');
Route::get('/bandara/{iata}', [BandaraController::class, 'show'])
    ->where('iata', '[A-Za-z]{3}')
    ->name('bandara.show');

Route::get('/', [AirportController::class, 'home'])->name('home');
Route::get('/cari', [AirportController::class, 'search'])->name('search');
Route::get('/rute', [RouteAirportController::class, 'index'])->name('route.index');

// chat box: tanya jawab bandara, maksimal 40 pertanyaan per menit per pengguna
Route::get('/chat', [ChatController::class, 'ask'])->middleware('throttle:40,1')->name('chat.ask');
