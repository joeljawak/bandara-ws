<?php

use App\Http\Controllers\AirportController;
use App\Http\Controllers\AirportDataController;
use App\Http\Controllers\BandaraController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\RouteAirportController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/bandara', [BandaraController::class, 'index'])->name('bandara.index');
Route::get('/bandara/{iata}', [BandaraController::class, 'show'])
    ->where('iata', '[A-Za-z]{3}')
    ->name('bandara.show');

Route::get('/', [AirportController::class, 'home'])->name('home');
Route::get('/cari', [AirportController::class, 'search'])
    ->middleware('throttle:90,1')
    ->name('search');
Route::get('/cari/saran', [AirportController::class, 'suggestions'])
    ->middleware('throttle:120,1')
    ->name('search.suggestions');

Route::get('/rute', [RouteAirportController::class, 'index'])->name('route.index');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// chat box: tanya jawab bandara, maksimal 40 pertanyaan per menit per pengguna
Route::get('/chat', [ChatController::class, 'ask'])->middleware('throttle:40,1')->name('chat.ask');

// Data relasional hasil impor RDF Turtle (bandara.ttl).
Route::prefix('data')->name('data.')->group(function (): void {
    Route::get('/bandara', [AirportDataController::class, 'airports'])->name('airports');
    Route::get('/bandara/{airport:iata_code}', [AirportDataController::class, 'airport'])->name('airport');
    Route::get('/maskapai', [AirportDataController::class, 'airlines'])->name('airlines');
    Route::get('/rute', [AirportDataController::class, 'routes'])->name('routes');
    Route::get('/peta', [AirportDataController::class, 'map'])->name('map');
});
