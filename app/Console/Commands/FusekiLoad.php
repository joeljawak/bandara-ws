<?php

namespace App\Console\Commands;

use App\Services\SparqlService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('fuseki:load {file? : Berkas Turtle utama (default: database/data/bandara.ttl)} {--supplement=* : Berkas Turtle tambahan, boleh diulang} {--no-supplement : Jangan memuat tambahan_wikidata.ttl} {--replace : Ganti seluruh isi dataset, bukan menambahkan}')]
#[Description('Muat berkas RDF Turtle ke dataset Apache Jena Fuseki')]
class FusekiLoad extends Command
{
    public function handle(SparqlService $sparql): int
    {
        $paths = $this->paths();

        if ($paths === []) {
            $this->error('Tidak ada berkas Turtle yang bisa dibaca.');

            return self::FAILURE;
        }

        $this->info('Dataset Fuseki: '.$sparql->datasetUrl());

        foreach ($paths as $i => $path) {
            $this->line('  memuat '.$path);

            try {
                // berkas pertama boleh mengganti isi dataset, sisanya selalu menambahkan
                $sparql->loadFile($path, $i === 0 && (bool) $this->option('replace'));
            } catch (Throwable $e) {
                $this->error('Gagal memuat ke Fuseki: '.$e->getMessage());
                $this->line('Pastikan Fuseki berjalan dan dataset sudah dibuat (UI Fuseki: manage > new dataset).');

                return self::FAILURE;
            }
        }

        $status = $sparql->status();
        $this->info($status['ok']
            ? 'Selesai. Dataset sekarang berisi '.number_format((int) $status['triples'], 0, ',', '.').' triple.'
            : 'Berkas terkirim, tetapi hitungan triple gagal dibaca: '.$status['error']);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        $paths = [$this->absolute($this->argument('file') ?: database_path('data/bandara.ttl'))];
        $tambahan = $this->option('supplement');

        if ($tambahan === [] && ! $this->option('no-supplement') && is_file(base_path('tambahan_wikidata.ttl'))) {
            $tambahan = [base_path('tambahan_wikidata.ttl')];
        }

        foreach ($tambahan as $path) {
            $paths[] = $this->absolute($path);
        }

        return array_values(array_filter(
            array_unique($paths),
            fn (string $path): bool => is_file($path) && is_readable($path)
        ));
    }

    private function absolute(string $path): string
    {
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
