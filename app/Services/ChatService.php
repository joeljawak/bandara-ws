<?php

namespace App\Services;

/**
 * Menjawab pertanyaan tentang bandara.
 *
 * Pertanyaan dikenali dengan aturan kata kunci (tanpa AI eksternal), lalu diubah
 * menjadi query SPARQL ke Fuseki. Jawabannya berupa teks dan daftar tautan.
 */
class ChatService
{
    // nama negara yang mungkin diketik -> potongan teks di label negara pada data
    private const NEGARA = [
        'indonesia' => ['indonesia'],
        'malaysia' => ['malaysia'],
        'thailand' => ['thailand'],
        'thai' => ['thailand'],
        'singapura' => ['singapore'],
        'singapore' => ['singapore'],
        'filipina' => ['philippines'],
        'philippines' => ['philippines'],
        'vietnam' => ['viet'],
        'viet nam' => ['viet'],
        'myanmar' => ['myanmar', 'burma'],
        'burma' => ['myanmar', 'burma'],
        'kamboja' => ['cambodia'],
        'cambodia' => ['cambodia'],
        'laos' => ['lao'],
        'brunei' => ['brunei'],
        'timor leste' => ['timor'],
        'timor-leste' => ['timor'],
        'east timor' => ['timor'],
    ];

    // kata yang dibuang saat mencari nama bandara dari kalimat
    private const KATA_UMUM = [
        'info', 'informasi', 'tentang', 'mengenai', 'dimana', 'mana', 'di', 'ke', 'dari', 'apa', 'itu',
        'saja', 'yang', 'ada', 'dong', 'ya', 'nih', 'tolong', 'mohon', 'cari', 'carikan', 'lihat',
        'tampilkan', 'daftar', 'list', 'semua', 'sebutkan', 'rute', 'penerbangan', 'terbang', 'pesawat',
        'maskapai', 'kode', 'iata', 'icao', 'lokasi', 'detail', 'profil', 'jumlah', 'berapa', 'banyak',
        'paling', 'terbanyak', 'terpanjang', 'landasan', 'runway', 'penumpang', 'ramai', 'sibuk',
        'terbesar', 'bandara', 'bandar', 'udara', 'airport', 'international', 'internasional',
        'domestik', 'untuk', 'adalah', 'dengan', 'dan', 'atau', 'yg', 'kah', 'bisa', 'kasih', 'tau',
        'tahu', 'apakah', 'siapa', 'kapan', 'bagaimana', 'gimana', 'menuju', 'to', 'from', 'the',
        'asean', 'tenggara', 'asia', 'se', 'seluruh', 'negara', 'di',
    ];

    private const BUKAN_IATA = ['ADA', 'APA', 'DAN', 'YANG', 'ATAU'];

    public function __construct(private SparqlService $sparql)
    {
    }

    // ---------- membaca maksud pertanyaan (tanpa jaringan, mudah diuji) ----------

    public static function parse(string $pesan): array
    {
        $asli = trim($pesan);
        $t = mb_strtolower($asli);
        $t = trim(preg_replace('/\s+/u', ' ', preg_replace('/[?!.,;:]+/u', ' ', $t)));

        $negara = self::negara($t);
        $hasil = [
            'intent' => 'info',
            'negara' => $negara ? $negara[1] : null,
            'negara_nama' => $negara ? $negara[0] : null,
            'term' => null,
            'dari' => null,
            'ke' => null,
        ];

        if ($t === '') {
            return ['intent' => 'bantuan'] + $hasil;
        }

        $kataRute = '/\b(rute|penerbangan|terbang|pesawat|jalur)\b/u';
        $kodeIata = self::kodeIata($asli);

        if (preg_match('/^(halo|hai|hi|hello|hey|selamat (pagi|siang|sore|malam)|assalamualaikum|permisi)\b/u', $t) && mb_strlen($t) < 28) {
            return ['intent' => 'sapaan'] + $hasil;
        }

        if (preg_match('/\bdari\s+(.+?)\s+(?:ke|menuju|to)\s+(.+)$/u', $t, $m) && preg_match($kataRute, $t)) {
            $hasil['dari'] = self::bersihkan($m[1]);
            $hasil['ke'] = self::bersihkan($m[2]);
            if ($hasil['dari'] !== '' && $hasil['ke'] !== '') {
                return ['intent' => 'rute_ab'] + $hasil;
            }
        }

        if (preg_match('/\b(berapa|jumlah|banyak)\b/u', $t) && preg_match('/\bbandara\b/u', $t)) {
            return ['intent' => 'hitung'] + $hasil;
        }

        if (preg_match('/\b(ramai|sibuk|terbesar|busiest)\b|penumpang (terbanyak|paling banyak)|terbanyak penumpang|paling banyak penumpang/u', $t)) {
            return ['intent' => 'ramai'] + $hasil;
        }

        if (preg_match('/\bterpanjang\b|landasan.*panjang|runway.*(panjang|longest)/u', $t)) {
            return ['intent' => 'landasan'] + $hasil;
        }

        $term = $kodeIata ?? self::bersihkan($t);
        $hasil['term'] = $term !== '' ? $term : null;

        if (preg_match('/\bmaskapai\b/u', $t)) {
            return ['intent' => 'maskapai'] + $hasil;
        }

        if (preg_match($kataRute, $t)) {
            return ['intent' => 'rute'] + $hasil;
        }

        if (preg_match('/\b(bantuan|help|bisa apa|cara pakai|fitur)\b/u', $t)) {
            return ['intent' => 'bantuan'] + $hasil;
        }

        if ($hasil['term'] === null && $negara) {
            return ['intent' => 'daftar'] + $hasil;
        }

        if (preg_match('/\b(daftar|list|sebutkan|tampilkan|semua)\b/u', $t) && $hasil['term'] === null) {
            return ['intent' => 'daftar'] + $hasil;
        }

        return ['intent' => 'info'] + $hasil;
    }

    // cari nama negara di kalimat, yang terpanjang dulu supaya "timor leste" menang atas "timor"
    private static function negara(string $t): ?array
    {
        $kunci = array_keys(self::NEGARA);
        usort($kunci, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($kunci as $k) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($k, '/') . '(?![\p{L}])/u', $t)) {
                return [$k, self::NEGARA[$k]];
            }
        }

        return null;
    }

    // kode IATA ditulis huruf besar semua, misalnya KNO
    private static function kodeIata(string $asli): ?string
    {
        if (preg_match_all('/(?<![A-Za-z])[A-Z]{3}(?![A-Za-z])/u', $asli, $m)) {
            foreach ($m[0] as $kode) {
                if (! in_array($kode, self::BUKAN_IATA, true)) {
                    return $kode;
                }
            }
        }

        return null;
    }

    // sisakan nama bandara saja dari potongan kalimat
    private static function bersihkan(string $teks): string
    {
        $teks = mb_strtolower($teks);
        foreach (array_keys(self::NEGARA) as $n) {
            $teks = preg_replace('/(?<![\p{L}])' . preg_quote($n, '/') . '(?![\p{L}])/u', ' ', $teks);
        }
        $kata = preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\p{N}\- ]+/u', ' ', $teks)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kata = array_filter($kata, fn ($k) => ! in_array($k, self::KATA_UMUM, true));

        return trim(implode(' ', $kata));
    }

    // ---------- menjawab ----------

    public function answer(string $pesan): array
    {
        $p = self::parse($pesan);

        try {
            return match ($p['intent']) {
                'sapaan' => $this->sapaan(),
                'bantuan' => $this->bantuan(),
                'hitung' => $this->hitung($p),
                'ramai' => $this->peringkat($p, 'penumpang'),
                'landasan' => $this->peringkat($p, 'landasan'),
                'daftar' => $this->daftar($p),
                'rute' => $this->rute($p),
                'rute_ab' => $this->ruteAB($p),
                'maskapai' => $this->maskapai($p),
                default => $this->info($p),
            };
        } catch (\Throwable $e) {
            return $this->jawab('Data bandara belum bisa diambil. Pastikan Fuseki sudah berjalan.');
        }
    }

    private function jawab(string $text, array $items = [], bool $saran = false): array
    {
        return ['text' => $text, 'items' => $items, 'saran' => $saran ? self::contoh() : []];
    }

    public static function contoh(): array
    {
        return [
            'Berapa jumlah bandara di Indonesia?',
            'Bandara paling ramai di Thailand',
            'Landasan terpanjang di Malaysia',
            'Info Kualanamu',
            'Rute dari KNO',
        ];
    }

    private function sapaan(): array
    {
        return $this->jawab('Halo! Aku bisa membantu mencari informasi bandara di Asia Tenggara. Coba salah satu pertanyaan ini:', [], true);
    }

    private function bantuan(): array
    {
        return $this->jawab(
            'Aku bisa menjawab jumlah bandara, bandara paling ramai, landasan terpanjang, daftar bandara per negara, info satu bandara, rute, dan maskapai. Contohnya:',
            [],
            true
        );
    }

    private function filterNegara(?array $tokens, string $var = '?negara'): string
    {
        if (! $tokens) {
            return '';
        }
        $bagian = array_map(fn ($t) => 'CONTAINS(LCASE(STR(' . $var . ')), "' . $t . '")', $tokens);

        return 'FILTER(' . implode(' || ', $bagian) . ')';
    }

    private function angka(string|float|int $n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }

    private function link(string $iata): string
    {
        return route('bandara.show', ['iata' => $iata]);
    }

    private function namaNegara(array $p): string
    {
        return $p['negara_nama'] ? ucwords($p['negara_nama']) : 'Asia Tenggara';
    }

    private function hitung(array $p): array
    {
        $f = $this->filterNegara($p['negara']);
        $rows = $this->sparql->query(
            'SELECT (COUNT(DISTINCT ?a) AS ?n) WHERE {
                ?a a ex:Airport .
                ' . ($f ? '?a ex:locatedInCountry ?c . ?c rdfs:label ?negara . ' . $f : '') . '
            }'
        );
        $n = (int) ($rows[0]['n'] ?? 0);

        if ($n === 0) {
            return $this->jawab('Tidak ada bandara untuk ' . $this->namaNegara($p) . ' di data.');
        }

        return $this->jawab('Ada ' . $this->angka($n) . ' bandara di ' . $this->namaNegara($p) . ' menurut data yang tersimpan.');
    }

    private function peringkat(array $p, string $jenis): array
    {
        $prop = $jenis === 'penumpang' ? 'ex:annualPassengers' : 'ex:longestRunwayLength';
        $f = $this->filterNegara($p['negara']);

        $rows = $this->sparql->query(
            'SELECT ?iata ?nama ?negara ?nilai WHERE {
                ?a a ex:Airport ; rdfs:label ?nama ; ex:hasIATACode ?iata ; ' . $prop . ' ?nilai ;
                   ex:locatedInCountry ?c .
                ?c rdfs:label ?negara .
                ' . $f . '
            } ORDER BY DESC(<http://www.w3.org/2001/XMLSchema#decimal>(STR(?nilai))) LIMIT 5'
        );

        if (! $rows) {
            return $this->jawab('Belum ada data ' . ($jenis === 'penumpang' ? 'jumlah penumpang' : 'panjang landasan') . ' untuk ' . $this->namaNegara($p) . '.');
        }

        $items = array_map(fn ($r) => [
            'judul' => $r['nama'] . ' (' . $r['iata'] . ')',
            'sub' => $this->angka($r['nilai']) . ($jenis === 'penumpang' ? ' penumpang per tahun' : ' meter') . ', ' . $r['negara'],
            'url' => $this->link($r['iata']),
        ], $rows);

        $judul = $jenis === 'penumpang'
            ? 'Bandara dengan penumpang terbanyak di ' . $this->namaNegara($p) . ' menurut data:'
            : 'Bandara dengan landasan terpanjang di ' . $this->namaNegara($p) . ' menurut data:';

        return $this->jawab($judul, $items);
    }

    private function daftar(array $p): array
    {
        $f = $this->filterNegara($p['negara']);
        $rows = $this->sparql->query(
            'SELECT ?iata ?nama ?negara WHERE {
                ?a a ex:Airport ; rdfs:label ?nama ; ex:hasIATACode ?iata ; ex:locatedInCountry ?c .
                ?c rdfs:label ?negara .
                ' . $f . '
            } ORDER BY ?nama LIMIT 10'
        );

        if (! $rows) {
            return $this->jawab('Aku tidak menemukan bandara untuk ' . $this->namaNegara($p) . '.');
        }

        $items = array_map(fn ($r) => [
            'judul' => $r['nama'] . ' (' . $r['iata'] . ')',
            'sub' => $r['negara'],
            'url' => $this->link($r['iata']),
        ], $rows);

        $items[] = [
            'judul' => 'Lihat semua bandara' . ($p['negara'] ? ' di ' . $rows[0]['negara'] : ''),
            'sub' => 'Buka halaman Jelajahi',
            'url' => route('bandara.index', $p['negara'] ? ['negara' => $rows[0]['negara']] : []),
        ];

        return $this->jawab('Ini beberapa bandara di ' . $this->namaNegara($p) . ' (urut abjad):', $items);
    }

    // cari satu bandara dari teks, kode IATA persis diutamakan
    private function cari(?string $term): ?array
    {
        if ($term === null || trim($term) === '') {
            return null;
        }
        $hasil = $this->sparql->search($term);
        foreach ($hasil as $h) {
            if (strtoupper($h['iata']) === strtoupper(trim($term))) {
                return $h;
            }
        }

        return $hasil[0] ?? null;
    }

    private function info(array $p): array
    {
        if ($p['term'] === null) {
            return $this->jawab('Bandara mana yang ingin kamu ketahui? Coba ketik nama atau kode IATA-nya, misalnya "Info Kualanamu".', [], true);
        }

        $hasil = $this->sparql->search($p['term']);
        if (! $hasil) {
            return $this->jawab('Aku belum menemukan bandara untuk "' . $p['term'] . '". Coba nama lain, kode IATA, atau nama kotanya.', [], true);
        }

        // satu hasil, atau kode IATA yang persis sama: tampilkan datanya
        $utama = null;
        foreach ($hasil as $h) {
            if (strtoupper($h['iata']) === strtoupper($p['term'])) {
                $utama = $h;
            }
        }
        $utama ??= count($hasil) === 1 ? $hasil[0] : null;

        if ($utama) {
            return $this->kartu($utama['iata']);
        }

        $items = array_map(fn ($r) => [
            'judul' => $r['nama'] . ' (' . $r['iata'] . ')',
            'sub' => collect([$r['kota'] ?? null, $r['negara'] ?? null])->filter()->implode(', '),
            'url' => $this->link($r['iata']),
        ], array_slice($hasil, 0, 6));

        return $this->jawab('Aku menemukan ' . count($hasil) . ' bandara yang cocok. Pilih salah satu:', $items);
    }

    // ringkasan satu bandara dalam kalimat
    private function kartu(string $iata): array
    {
        $iata = $this->sparql->esc(strtoupper($iata));
        $rows = $this->sparql->query(
            'SELECT ?nama ?icao ?negara ?wilayah ?provinsi ?operator ?penumpang ?runway WHERE {
                ?a ex:hasIATACode "' . $iata . '" ; rdfs:label ?nama ; ex:locatedInCountry ?c .
                ?c rdfs:label ?negara .
                OPTIONAL { ?a ex:hasICAOCode ?icao }
                OPTIONAL { ?a ex:operator ?operator }
                OPTIONAL { ?a ex:annualPassengers ?penumpang }
                OPTIONAL { ?a ex:longestRunwayLength ?runway }
                OPTIONAL {
                    ?a ex:locatedInRegion ?r . ?r rdfs:label ?wilayah .
                    OPTIONAL { ?r ex:partOfRegion ?pr . ?pr rdfs:label ?provinsi }
                }
            } LIMIT 1'
        );

        if (! $rows) {
            return $this->jawab('Data bandara ' . $iata . ' tidak ditemukan.');
        }

        $b = $rows[0];
        $lokasi = collect([$b['wilayah'] ?? null, $b['provinsi'] ?? null, $b['negara']])->filter()->implode(', ');
        $kalimat = [$b['nama'] . ' (' . $iata . (isset($b['icao']) ? ' / ' . $b['icao'] : '') . ') berada di ' . $lokasi . '.'];
        if (isset($b['operator'])) {
            $kalimat[] = 'Dikelola oleh ' . $b['operator'] . '.';
        }
        if (isset($b['runway'])) {
            $kalimat[] = 'Landasan terpanjangnya ' . $this->angka($b['runway']) . ' meter.';
        }
        if (isset($b['penumpang'])) {
            $kalimat[] = 'Penumpangnya sekitar ' . $this->angka($b['penumpang']) . ' per tahun.';
        }

        return $this->jawab(implode(' ', $kalimat), [[
            'judul' => 'Buka halaman ' . $b['nama'],
            'sub' => 'Foto, peta, rute, dan jadwal',
            'url' => $this->link($iata),
        ]]);
    }

    private function rute(array $p): array
    {
        $b = $this->cari($p['term']);
        if (! $b) {
            return $this->jawab('Dari bandara mana? Coba tulis seperti "Rute dari KNO" atau "Penerbangan dari Changi".', [], true);
        }

        $iata = $this->sparql->esc(strtoupper($b['iata']));
        $rows = $this->sparql->query(
            'SELECT ?dst_iata ?dst_nama (COUNT(DISTINCT ?m) AS ?jml) WHERE {
                ?a ex:hasIATACode "' . $iata . '" .
                ?r a ex:Route ; ex:origin ?a ; ex:destination ?d ; ex:operatedBy ?m .
                ?d ex:hasIATACode ?dst_iata ; rdfs:label ?dst_nama .
            } GROUP BY ?dst_iata ?dst_nama ORDER BY DESC(?jml) ?dst_nama LIMIT 10'
        );

        if (! $rows) {
            return $this->jawab('Data rute dari ' . $b['nama'] . ' belum tersedia.', [[
                'judul' => $b['nama'],
                'sub' => 'Buka halaman bandara',
                'url' => $this->link($b['iata']),
            ]]);
        }

        $items = array_map(fn ($r) => [
            'judul' => $r['dst_nama'] . ' (' . $r['dst_iata'] . ')',
            'sub' => $r['jml'] . ' maskapai',
            'url' => route('route.index', ['dari' => $b['iata'], 'ke' => $r['dst_iata']]),
        ], $rows);

        return $this->jawab('Rute dari ' . $b['nama'] . ' (' . $b['iata'] . '), tujuan dengan maskapai terbanyak lebih dulu. Pilih untuk melihatnya di peta:', $items);
    }

    private function ruteAB(array $p): array
    {
        $a = $this->cari($p['dari']);
        $b = $this->cari($p['ke']);
        if (! $a || ! $b) {
            $yang = ! $a ? $p['dari'] : $p['ke'];

            return $this->jawab('Aku belum menemukan bandara "' . $yang . '". Coba nama lain atau kode IATA.', [], true);
        }

        $rows = $this->sparql->query(
            'SELECT DISTINCT ?maskapai WHERE {
                ?o ex:hasIATACode "' . $this->sparql->esc(strtoupper($a['iata'])) . '" .
                ?d ex:hasIATACode "' . $this->sparql->esc(strtoupper($b['iata'])) . '" .
                ?r a ex:Route ; ex:origin ?o ; ex:destination ?d ; ex:operatedBy ?m .
                ?m rdfs:label ?maskapai
            } ORDER BY ?maskapai'
        );

        $peta = [[
            'judul' => 'Lihat ' . $a['iata'] . ' ke ' . $b['iata'] . ' di peta',
            'sub' => 'Jalur dan jarak garis lurus',
            'url' => route('route.index', ['dari' => $a['iata'], 'ke' => $b['iata']]),
        ]];

        if (! $rows) {
            return $this->jawab('Di data tidak ada rute langsung dari ' . $a['nama'] . ' ke ' . $b['nama'] . '.', $peta);
        }

        $nama = implode(', ', array_column($rows, 'maskapai'));

        return $this->jawab('Rute langsung ' . $a['nama'] . ' ke ' . $b['nama'] . ' dilayani oleh: ' . $nama . '.', $peta);
    }

    private function maskapai(array $p): array
    {
        $b = $this->cari($p['term']);
        if (! $b) {
            return $this->jawab('Maskapai di bandara mana? Coba tulis seperti "Maskapai di CGK".', [], true);
        }

        $rows = $this->sparql->query(
            'SELECT DISTINCT ?maskapai WHERE {
                ?a ex:hasIATACode "' . $this->sparql->esc(strtoupper($b['iata'])) . '" .
                ?r a ex:Route ; ex:origin ?a ; ex:operatedBy ?m .
                ?m rdfs:label ?maskapai
            } ORDER BY ?maskapai LIMIT 20'
        );

        if (! $rows) {
            return $this->jawab('Data maskapai untuk ' . $b['nama'] . ' belum tersedia.');
        }

        return $this->jawab(
            'Maskapai yang melayani penerbangan dari ' . $b['nama'] . ': ' . implode(', ', array_column($rows, 'maskapai')) . '.',
            [[
                'judul' => 'Buka halaman ' . $b['nama'],
                'sub' => 'Foto, peta, rute, dan jadwal',
                'url' => $this->link($b['iata']),
            ]]
        );
    }
}
