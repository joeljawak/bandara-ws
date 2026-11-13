<?php

use App\Http\Controllers\Api\AirportDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:120,1')->group(function (): void {
    Route::get('/airports', [AirportDataController::class, 'airports'])->name('airports.index');
    Route::get('/airports/{airport:iata_code}', [AirportDataController::class, 'airport'])->name('airports.show');
    Route::get('/airlines', [AirportDataController::class, 'airlines'])->name('airlines.index');
    Route::get('/routes', [AirportDataController::class, 'routes'])->name('routes.index');
    Route::get('/stats', [AirportDataController::class, 'stats'])->name('stats');
});
