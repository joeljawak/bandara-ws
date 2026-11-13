<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('data:merge-vocab {file? : Berkas .nq sumber (default: database/data/backup_data.nq)} {--out=database/data/merged-bandara.nq : Berkas .nq hasil gabungan} {--report=database/data/merge-report.json : Berkas laporan JSON}')]
#[Description('Gabungkan vocabulary bandara# ke asean-airport# tanpa menimpa data kanonik')]
class MergeVocabulary extends Command
{
    private const KANONIK = 'http://example.org/asean-airport#';

    private const LAMA = 'http://example.org/bandara#';

    private const RDF_TYPE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

    private const RDFS_LABEL = 'http://www.w3.org/2000/01/rdf-schema#label';

    /** @var array<string, string> */
    private const PETA_JENIS = [
        'Bandara' => 'Airport',
        'Maskapai' => 'Airline',
        'Rute' => 'Route',
        'Negara' => 'Country',
        'Wilayah' => 'Region',
    ];

    /** @var array<string, string> */
    private const PETA_PREDIKAT = [
        'nama' => self::RDFS_LABEL,
        'namaBandara' => self::RDFS_LABEL,
        'iata' => 'hasIATACode',
        'kodeIATA' => 'hasIATACode',
        'icao' => 'hasICAOCode',
        'kodeICAO' => 'hasICAOCode',
        'kategori' => 'airportCategory',
        'status' => 'airportStatus',
        'negara' => 'locatedInCountry',
        'kota' => 'locatedInRegion',
        'provinsi' => 'locatedInRegion',
        'terletakDi' => 'locatedInRegion',
        'maskapai' => 'operatedBy',
        'dilayaniOleh' => 'operatesAt',
        'asal' => 'origin',
        'tujuan' => 'destination',
        'jumlahPenumpang' => 'annualPassengers',
        'penumpang' => 'annualPassengers',
        'pengelola' => 'operator',
        'jumlahLandasan' => 'runwayCount',
        'panjangLandasan' => 'longestRunwayLength',
    ];

    public function handle(): int
    {
        $sumber = $this->absolute((string) ($this->argument('file') ?: 'database/data/backup_data.nq'));

        if (! is_file($sumber)) {
            $this->error('Berkas tidak ditemukan: '.$sumber);
            $this->line('Letakkan backup_data.nq di database/data/ atau jalankan: php artisan data:merge-vocab path/ke/file.nq');

            return self::FAILURE;
        }

        $triples = $this->bacaNquads($sumber);
        $this->info(sprintf('Membaca %s triple dari %s', number_format(count($triples), 0, ',', '.'), $sumber));

        $hasil = $this->gabung($triples);

        $out = $this->absolute((string) $this->option('out'));
        $report = $this->absolute((string) $this->option('report'));

        $this->pastikanDirektori($out);
        $this->pastikanDirektori($report);

        file_put_contents($out, $this->tulisNquads($hasil['triples']));
        file_put_contents($report, json_encode($hasil['laporan'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->table(['Keterangan', 'Jumlah'], [
            ['Bandara di asean-airport#', $hasil['laporan']['kanonik']],
            ['Bandara di bandara#', $hasil['laporan']['lama']],
            ['Bandara hasil gabungan', $hasil['laporan']['gabungan']],
            ['Hanya ada di bandara#', $hasil['laporan']['hanya_lama']],
            ['Hanya ada di asean-airport#', $hasil['laporan']['hanya_kanonik']],
            ['Konflik nilai', count($hasil['laporan']['konflik'])],
            ['Triple keluaran', number_format(count($hasil['triples']), 0, ',', '.')],
        ]);

        if ($hasil['laporan']['iata_hanya_lama'] !== []) {
            $this->warn('IATA hanya di bandara#: '.implode(', ', $hasil['laporan']['iata_hanya_lama']));
        }

        $this->newLine();
        $this->info('Hasil gabungan: '.$out);
        $this->info('Laporan: '.$report);

        return self::SUCCESS;
    }

    /**
     * @param  list<array{s: string, p: string, o: string, t: 'iri'|'literal'|'bnode', d: string|null, l: string|null}>  $triples
     * @return array{triples: list<array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}>, laporan: array<string, mixed>}
     */
    private function gabung(array $triples): array
    {
        $kanonik = [];
        $lama = [];
        $petaSubjek = [];

        foreach ($triples as $triple) {
            $namespace = $this->namespaceDari($triple['s']);

            if ($namespace === null || ! in_array($namespace, [self::KANONIK, self::LAMA], true)) {
                continue;
            }

            $jenis = $this->jenisEntitas($triple['s'], $triples);

            if ($namespace === self::LAMA && $jenis !== null) {
                $petaSubjek[$triple['s']] = $this->subjekKanonik($triple['s'], $jenis, $triples);
            }
        }

        foreach ($triples as $triple) {
            $asal = $this->namespaceDari($triple['s']);
            $kanon = $this->namespaceDari($triple['s']) === self::KANONIK;

            if (! in_array($asal, [self::KANONIK, self::LAMA], true)) {
                continue;
            }

            $subjek = $petaSubjek[$triple['s']] ?? $triple['s'];
            $predikat = $this->predikatKanonik($triple['p']);
            $objek = $this->objekKanonik($triple, $petaSubjek);

            if ($predikat === null) {
                continue;
            }

            $baris = ['s' => $subjek, 'p' => $predikat, 'o' => $objek['o'], 't' => $objek['t'], 'd' => $objek['d'], 'l' => $objek['l']];
            $kunci = $this->kunci($baris);

            if ($kanon) {
                $kanonik[$kunci] = $baris;
            } else {
                $lama[$kunci] = $baris;
            }
        }

        $laporan = [
            'kanonik' => 0,
            'lama' => 0,
            'gabungan' => 0,
            'hanya_lama' => 0,
            'hanya_kanonik' => 0,
            'iata_hanya_lama' => [],
            'iata_hanya_kanonik' => [],
            'konflik' => [],
        ];

        $iataKanonik = $this->petaIata($kanonik);
        $iataLama = $this->petaIata($lama);
        $laporan['kanonik'] = count($iataKanonik);
        $laporan['lama'] = count($iataLama);
        $laporan['iata_hanya_lama'] = array_values(array_diff(array_keys($iataLama), array_keys($iataKanonik)));
        $laporan['iata_hanya_kanonik'] = array_values(array_diff(array_keys($iataKanonik), array_keys($iataLama)));
        $laporan['hanya_lama'] = count($laporan['iata_hanya_lama']);
        $laporan['hanya_kanonik'] = count($laporan['iata_hanya_kanonik']);

        $konflik = [];
        $gabung = $kanonik;

        foreach ($lama as $kunci => $baris) {
            if (isset($kanonik[$kunci])) {
                continue;
            }

            $sama = $this->cariPredikatSama($kanonik, $baris);

            if ($sama !== null && $sama['o'] !== $baris['o']) {
                $konflik[] = [
                    'subjek' => $baris['s'],
                    'predikat' => $baris['p'],
                    'kanonik' => $sama['o'],
                    'lama' => $baris['o'],
                ];

                continue;
            }

            $gabung[$kunci] = $baris;
        }

        $laporan['konflik'] = $konflik;
        $laporan['gabungan'] = count($this->petaIata($gabung));

        return ['triples' => array_values($gabung), 'laporan' => $laporan];
    }

    /**
     * @param  array<string, array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}>  $data
     * @return array<string, string>
     */
    private function petaIata(array $data): array
    {
        $hasil = [];

        foreach ($data as $baris) {
            if ($this->local($baris['p']) !== 'hasIATACode' || $baris['t'] !== 'literal') {
                continue;
            }

            $hasil[strtoupper($baris['o'])] = $baris['s'];
        }

        return $hasil;
    }

    /**
     * @param  array<string, array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}>  $data
     * @return array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}|null
     */
    private function cariPredikatSama(array $data, array $baris): ?array
    {
        foreach ($data as $kanonik) {
            if ($kanonik['s'] === $baris['s'] && $kanonik['p'] === $baris['p']) {
                return $kanonik;
            }
        }

        return null;
    }

    /**
     * @param  list<array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}>  $triples
     */
    private function jenisEntitas(string $subjek, array $triples): ?string
    {
        foreach ($triples as $triple) {
            if ($triple['s'] !== $subjek || $triple['p'] !== self::RDF_TYPE || $triple['t'] !== 'iri') {
                continue;
            }

            $local = $this->local($triple['o']);
            $local = self::PETA_JENIS[$local] ?? $local;

            if (in_array($local, ['Airport', 'Airline', 'Route', 'Country', 'Region'], true)) {
                return $local;
            }
        }

        return null;
    }

    /**
     * @param  list<array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}>  $triples
     */
    private function subjekKanonik(string $subjek, string $jenis, array $triples): string
    {
        $local = $this->local($subjek);

        if ($jenis === 'Airport') {
            $iata = null;

            foreach ($triples as $triple) {
                if ($triple['s'] === $subjek && in_array($this->local($triple['p']), ['kodeIATA', 'hasIATACode'], true)) {
                    $iata = strtoupper($triple['o']);
                    break;
                }
            }

            return self::KANONIK.'airport_'.($iata ?: preg_replace('/^airport_/i', '', $local));
        }

        $prefix = strtolower($jenis);

        if (str_starts_with(mb_strtolower($local), $prefix.'_')) {
            return self::KANONIK.$local;
        }

        return self::KANONIK.$prefix.'_'.preg_replace('/^'.preg_quote($prefix, '/').'_/i', '', $local);
    }

    private function predikatKanonik(string $predikat): ?string
    {
        $local = $this->local($predikat);
        $local = self::PETA_PREDIKAT[$local] ?? $local;

        if ($predikat === self::RDF_TYPE || str_starts_with($predikat, 'http://www.w3.org/')) {
            return $predikat;
        }

        return self::KANONIK.$local;
    }

    /**
     * @param  array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}  $triple
     * @param  array<string, string>  $petaSubjek
     * @return array{o: string, t: string, d: string|null, l: string|null}
     */
    private function objekKanonik(array $triple, array $petaSubjek): array
    {
        if ($triple['t'] === 'iri' && isset($petaSubjek[$triple['o']])) {
            return ['o' => $petaSubjek[$triple['o']], 't' => 'iri', 'd' => null, 'l' => null];
        }

        if ($triple['t'] === 'iri' && $triple['p'] === self::RDF_TYPE) {
            $local = $this->local($triple['o']);
            $local = self::PETA_JENIS[$local] ?? $local;

            return ['o' => self::KANONIK.$local, 't' => 'iri', 'd' => null, 'l' => null];
        }

        if ($triple['t'] === 'iri' && str_starts_with($triple['o'], self::LAMA)) {
            return ['o' => self::KANONIK.substr($triple['o'], strlen(self::LAMA)), 't' => 'iri', 'd' => null, 'l' => null];
        }

        return ['o' => $triple['o'], 't' => $triple['t'], 'd' => $triple['d'], 'l' => $triple['l']];
    }

    /**
     * @param  array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}  $baris
     */
    private function kunci(array $baris): string
    {
        return implode('|', [$baris['s'], $baris['p'], $baris['t'], $baris['d'] ?? '', $baris['l'] ?? '', $baris['o']]);
    }

    private function namespaceDari(string $iri): ?string
    {
        $posisi = strrpos($iri, '#');

        return $posisi === false ? null : substr($iri, 0, $posisi + 1);
    }

    private function local(string $iri): string
    {
        $hash = strrpos($iri, '#');
        $slash = strrpos($iri, '/');
        $posisi = max($hash === false ? -1 : $hash, $slash === false ? -1 : $slash);

        return $posisi === -1 ? $iri : substr($iri, $posisi + 1);
    }

    /**
     * @return list<array{s: string, p: string, o: string, t: 'iri'|'literal'|'bnode', d: string|null, l: string|null}>
     */
    private function bacaNquads(string $path): array
    {
        $isi = file_get_contents($path);

        if ($isi === false) {
            return [];
        }

        $hasil = [];

        foreach (preg_split('/\R/u', $isi) ?: [] as $baris) {
            $baris = trim($baris);

            if ($baris === '' || str_starts_with($baris, '#') || ! str_ends_with($baris, '.')) {
                continue;
            }

            $terms = $this->tokenTerm(substr($baris, 0, -1));

            if (count($terms) < 3) {
                continue;
            }

            [$s, $p, $o] = array_slice($terms, 0, 3);

            if ($s['t'] === 'bnode' || $p['t'] !== 'iri') {
                continue;
            }

            $hasil[] = [
                's' => $s['o'],
                'p' => $p['o'],
                'o' => $o['o'],
                't' => $o['t'],
                'd' => $o['d'],
                'l' => $o['l'],
            ];
        }

        return $hasil;
    }

    /**
     * @return list<array{o: string, t: 'iri'|'literal'|'bnode', d: string|null, l: string|null}>
     */
    private function tokenTerm(string $baris): array
    {
        $hasil = [];
        $panjang = strlen($baris);
        $i = 0;

        while ($i < $panjang) {
            while ($i < $panjang && ctype_space($baris[$i])) {
                $i++;
            }

            if ($i >= $panjang) {
                break;
            }

            if ($baris[$i] === '<') {
                $akhir = strpos($baris, '>', $i);

                if ($akhir === false) {
                    break;
                }

                $hasil[] = ['o' => substr($baris, $i + 1, $akhir - $i - 1), 't' => 'iri', 'd' => null, 'l' => null];
                $i = $akhir + 1;

                continue;
            }

            if ($baris[$i] === '"' || $baris[$i] === "'") {
                $kutip = $baris[$i];
                $i++;
                $nilai = '';

                while ($i < $panjang) {
                    if ($baris[$i] === '\\' && $i + 1 < $panjang) {
                        $nilai .= $baris[$i].$baris[$i + 1];
                        $i += 2;

                        continue;
                    }

                    if ($baris[$i] === $kutip) {
                        $i++;

                        break;
                    }

                    $nilai .= $baris[$i];
                    $i++;
                }

                $nilai = stripcslashes($nilai);
                $datatype = null;
                $language = null;

                while ($i < $panjang && ctype_space($baris[$i])) {
                    $i++;
                }

                if ($i < $panjang && $baris[$i] === '@') {
                    $i++;
                    $awal = $i;

                    while ($i < $panjang && (ctype_alnum($baris[$i]) || $baris[$i] === '-')) {
                        $i++;
                    }

                    $language = substr($baris, $awal, $i - $awal);
                } elseif (substr($baris, $i, 2) === '^^') {
                    $i += 2;

                    if ($i < $panjang && $baris[$i] === '<') {
                        $akhir = strpos($baris, '>', $i);
                        $datatype = $akhir === false ? null : substr($baris, $i + 1, $akhir - $i - 1);
                        $i = $akhir === false ? $panjang : $akhir + 1;
                    }
                }

                $hasil[] = ['o' => $nilai, 't' => 'literal', 'd' => $datatype, 'l' => $language];

                continue;
            }

            $awal = $i;

            while ($i < $panjang && ! ctype_space($baris[$i])) {
                $i++;
            }

            $token = substr($baris, $awal, $i - $awal);
            $hasil[] = ['o' => $token, 't' => str_starts_with($token, '_:') ? 'bnode' : 'literal', 'd' => null, 'l' => null];
        }

        return $hasil;
    }

    /**
     * @param  list<array{s: string, p: string, o: string, t: string, d: string|null, l: string|null}>  $triples
     */
    private function tulisNquads(array $triples): string
    {
        $baris = [];

        foreach ($triples as $triple) {
            $objek = match ($triple['t']) {
                'iri' => '<'.$triple['o'].'>',
                'bnode' => $triple['o'],
                default => '"'.$this->escapeLiteral($triple['o']).'"'
                    .($triple['l'] ? '@'.$triple['l'] : '')
                    .($triple['d'] ? '^^<'.$triple['d'].'>' : ''),
            };

            $baris[] = '<'.$triple['s'].'> <'.$triple['p'].'> '.$objek.' <urn:bandara:merged> .';
        }

        sort($baris);

        return implode("\n", $baris).($baris === [] ? '' : "\n");
    }

    private function escapeLiteral(string $nilai): string
    {
        return str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $nilai
        );
    }

    private function absolute(string $path): string
    {
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    private function pastikanDirektori(string $path): void
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
