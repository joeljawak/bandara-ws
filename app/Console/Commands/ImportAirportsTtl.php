<?php

namespace App\Console\Commands;

use App\Services\AirportTtlImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('import:airports-ttl {file? : File Turtle utama (default: database/data/bandara.ttl)} {--supplement=* : File Turtle tambahan, boleh diulang} {--no-supplement : Jangan memuat tambahan_wikidata.ttl otomatis} {--fresh : Hapus seluruh data relasional sebelum impor}')]
#[Description('Impor bandara, maskapai, dan rute dari RDF Turtle ke tabel relasional')]
class ImportAirportsTtl extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AirportTtlImporter $importer): int
    {
        $paths = $this->resolvePaths();

        if ($paths === []) {
            $this->error('Tidak ada file Turtle yang bisa dibaca.');

            return self::FAILURE;
        }

        $this->info('Mengimpor data dari:');

        foreach ($paths as $path) {
            $this->line('  - '.$path);
        }

        try {
            $result = $importer->import($paths, (bool) $this->option('fresh'));
        } catch (Throwable $exception) {
            $this->error('Impor gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Sumber', 'Jumlah'],
            collect($result['parsed'])
                ->map(fn (int $count, string $entity): array => ['Terbaca: '.$entity, $count])
                ->values()
                ->merge(collect($result['stored'])
                    ->map(fn (int $count, string $table): array => ['Tersimpan: '.$table, $count]))
                ->values()
                ->all()
        );

        $this->newLine();
        $this->line(sprintf(
            '%d triple, %d subjek, selesai dalam %s detik.',
            $result['triples'],
            $result['subjects'],
            number_format($result['duration'], 2, ',', '.')
        ));

        if ($result['skipped_routes'] !== []) {
            $this->warn(sprintf(
                '%d rute dilewati karena relasi tidak lengkap: %s',
                count($result['skipped_routes']),
                implode(', ', array_slice($result['skipped_routes'], 0, 10))
            ));
        }

        $this->info('Impor selesai.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolvePaths(): array
    {
        $main = $this->argument('file') ?: database_path('data/bandara.ttl');
        $paths = [$this->absolutePath($main)];

        $supplements = $this->option('supplement');

        if ($supplements === [] && ! $this->option('no-supplement')) {
            $defaultSupplement = base_path('tambahan_wikidata.ttl');

            if (is_file($defaultSupplement)) {
                $supplements = [$defaultSupplement];
            }
        }

        foreach ($supplements as $supplement) {
            $paths[] = $this->absolutePath($supplement);
        }

        return array_values(array_filter(
            array_unique($paths),
            fn (string $path): bool => is_file($path) && is_readable($path)
        ));
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
