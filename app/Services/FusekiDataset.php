<?php

namespace App\Services;

use App\Support\TurtleParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menjadikan Apache Jena Fuseki sebagai database aplikasi.
 *
 * Pada setiap permintaan, seluruh triple dibaca dari Fuseki lewat SPARQL lalu
 * dimuat ke basis data SQLite sementara di memori (koneksi "fuseki") agar
 * pencarian, peringkat, dan halaman tetap bisa memakai query relasional.
 * Salinan itu hilang saat permintaan selesai, jadi tidak ada data yang tersimpan
 * di luar Fuseki. Kalau Fuseki tidak bisa dihubungi, load() melempar exception.
 */
class FusekiDataset
{
    private bool $loaded = false;

    public function __construct(
        private SparqlService $sparql,
        private AirportTtlImporter $importer,
        private TurtleParser $parser,
    ) {}

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->naikkanBatasMemori();

        // Fuseki dicek di setiap permintaan, walau hasil unduhan dipakai ulang.
        $status = $this->sparql->status();

        if (! $status['ok']) {
            throw new \RuntimeException('Fuseki tidak bisa dihubungi: '.$status['error']);
        }

        if (($status['triples'] ?? 0) === 0) {
            throw new \RuntimeException('Dataset Fuseki kosong. Muat datanya dulu: php artisan fuseki:load');
        }

        $salinan = $this->bacaSalinan((int) $status['triples']);

        if ($salinan === null) {
            $data = $this->sparql->constructAll();
            $triples = $this->parser->parse($data['body']);
            $versi = $data['version'];
            unset($data);

            $this->simpanSalinan((int) $status['triples'], $versi, $triples);
        } else {
            ['triples' => $triples, 'version' => $versi] = $salinan;
        }

        $this->siapkanDatabaseSementara();

        $sebelumnya = DB::getDefaultConnection();
        DB::setDefaultConnection('fuseki');

        try {
            $this->importer->importTriples($triples);

            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\AirportAliasSeeder',
                '--database' => 'fuseki',
                '--force' => true,
            ]);
        } finally {
            DB::setDefaultConnection($sebelumnya);
        }

        unset($triples);
        $this->segarkanCache($versi);
        $this->loaded = true;
    }

    private function berkasSalinan(): string
    {
        return storage_path('framework/cache/fuseki-triples.bin');
    }

    /**
     * @return array{triples: list<array<string, mixed>>, version: string}|null
     */
    private function bacaSalinan(int $jumlahTriple): ?array
    {
        $ttl = (int) config('sparql.cache_seconds', 0);
        $berkas = $this->berkasSalinan();

        if ($ttl <= 0 || ! is_file($berkas) || (time() - (int) filemtime($berkas)) > $ttl) {
            return null;
        }

        $isi = @unserialize((string) file_get_contents($berkas), ['allowed_classes' => false]);

        if (! is_array($isi) || ($isi['jumlah'] ?? null) !== $jumlahTriple || ! isset($isi['triples'], $isi['version'])) {
            return null;
        }

        return ['triples' => $isi['triples'], 'version' => $isi['version']];
    }

    /**
     * @param  list<array<string, mixed>>  $triples
     */
    private function simpanSalinan(int $jumlahTriple, string $versi, array $triples): void
    {
        if ((int) config('sparql.cache_seconds', 0) <= 0) {
            return;
        }

        $berkas = $this->berkasSalinan();

        if (! is_dir(dirname($berkas))) {
            @mkdir(dirname($berkas), 0775, true);
        }

        @file_put_contents($berkas, serialize(['jumlah' => $jumlahTriple, 'version' => $versi, 'triples' => $triples]), LOCK_EX);
    }

    /**
     * Puluhan ribu triple dibaca sekaligus; batas 128 MB bawaan PHP terlalu sempit.
     */
    private function naikkanBatasMemori(): void
    {
        $batas = ini_get('memory_limit');

        if ($batas === false || $batas === '-1') {
            return;
        }

        $byte = (int) $batas * match (strtoupper(substr($batas, -1))) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        if ($byte < 256 * 1024 ** 2) {
            ini_set('memory_limit', '256M');
        }
    }

    private function siapkanDatabaseSementara(): void
    {
        config(['database.connections.fuseki' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge('fuseki');

        $migrasi = glob(database_path('migrations/2026_*.php')) ?: [];
        sort($migrasi);

        Artisan::call('migrate', [
            '--database' => 'fuseki',
            '--path' => array_map(fn (string $path): string => 'database/migrations/'.basename($path), $migrasi),
            '--force' => true,
        ]);
    }

    /**
     * Hasil yang di-cache (statistik, pencarian, sitemap) harus ikut berubah
     * kalau isi Fuseki berubah, jadi cache dibuang saat sidik jari data berbeda.
     */
    private function segarkanCache(string $versi): void
    {
        try {
            if (Cache::get('fuseki.versi') !== $versi) {
                Cache::flush();
                Cache::forever('fuseki.versi', $versi);
            }
        } catch (\Throwable) {
            // cache belum siap (php artisan migrate belum dijalankan); data tetap dari Fuseki
        }
    }
}
