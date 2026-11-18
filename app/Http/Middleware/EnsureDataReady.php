<?php

namespace App\Http\Middleware;

use App\Models\Airport;
use App\Services\FusekiDataset;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Semua halaman yang membaca data bandara bergantung pada Apache Jena Fuseki.
 * Kalau Fuseki tidak berjalan atau datasetnya kosong, tidak ada yang bisa dicari.
 */
class EnsureDataReady
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('sparql.live')) {
            try {
                app(FusekiDataset::class)->load();
            } catch (Throwable $e) {
                report($e);

                return $this->tidakTersedia($request, $e->getMessage());
            }

            return $next($request);
        }

        // mode lokal (pengujian): pakai database yang sudah diisi lewat import:airports-ttl
        if ($this->indeksTerisi()) {
            return $next($request);
        }

        return $this->tidakTersedia($request, 'Database lokal masih kosong.');
    }

    private function tidakTersedia(Request $request, string $alasan): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Apache Jena Fuseki tidak tersedia, data tidak bisa diambil.',
                'detail' => $alasan,
            ], 503);
        }

        return response()->view('fuseki-down', [
            'endpoint' => (string) config('sparql.endpoint'),
            'alasan' => $alasan,
            'live' => (bool) config('sparql.live'),
        ], 503);
    }

    private function indeksTerisi(): bool
    {
        try {
            return Schema::hasTable('airports') && Airport::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
