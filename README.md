# BANDARA ASEAN

Aplikasi pencarian bandara, maskapai, dan rute di Asia Tenggara. Data utama disimpan di
tabel relasional MySQL/SQLite; Fuseki hanya dipakai sebagai endpoint SPARQL publik.

## Fitur

- Pencarian terpadu untuk **bandara, maskapai, dan rute** dari satu kotak pencarian.
- Peringkat relevansi: kode IATA/ICAO, nama persis, awalan nama, kata dalam nama, kota,
  negara/wilayah, operator, lalu popularitas (penumpang dan jumlah tujuan).
- Toleransi salah ketik 1–2 huruf (`Suvarnabumi` → Suvarnabhumi, `Kualanamo` → Kualanamu).
- Alias populer dan nama lokal: Cengkareng, Ngurah Rai, Halim, KLIA, Don Mueang, Medan, dll.
- Filter negara, kategori, status ASEAN, ketersediaan rute; urutkan relevansi/nama/penumpang.
- Pencarian radius `near=lat,lon&radius=km` dan tombol "Bandara dekat saya".
- Autocomplete aksesibel (`/cari/saran`) dengan navigasi keyboard dan ARIA.
- Halaman detail bandara lengkap: data utama, koordinat, maskapai hub, rute langsung,
  foto, peta, deskripsi DBpedia, serta fallback satu transit untuk `/rute`.
- SEO: sitemap dinamis, `robots.txt`, canonical, Open Graph/Twitter Card, JSON-LD, RDFa.
- Chat tanya-jawab yang membaca database relasional (tetap jalan tanpa Fuseki).

## Teknologi

- PHP 8.4, Laravel 13
- Blade + CSS/JS tanpa framework frontend berat
- MySQL (produksi) atau SQLite (lokal/pengujian)
- PHPUnit, Laravel Pint

## Struktur Data

Tabel utama: `countries`, `regions`, `airports`, `airlines`, `routes`, `airport_aliases`.

Vocabulary kanonik: `http://example.org/asean-airport#`.
Data lama `http://example.org/bandara#` tidak dipakai lagi oleh aplikasi; lihat bagian
[Menggabungkan vocabulary lama](#menggabungkan-vocabulary-lama).

## Instalasi

```bash
composer install
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
```

Atur koneksi database di `.env`, lalu:

```bash
php artisan migrate
php artisan import:airports-ttl storage/app/bandara.ttl   # sesuaikan lokasi berkas
php artisan db:seed --class=AirportAliasSeeder
npm install
npm run build
php artisan serve
```

Impor ulang bersifat idempoten. Gunakan `--fresh` bila ingin mengosongkan tabel hasil impor
lebih dulu:

```bash
php artisan import:airports-ttl storage/app/bandara.ttl --fresh
```

### Konfigurasi SPARQL (opsional)

Endpoint publik diatur di `config/sparql.php`:

```dotenv
SPARQL_ENDPOINT=http://localhost:3030/bandara/sparql
```

`FUSEKI_URL` masih dibaca sebagai fallback agar `.env` lama tetap berjalan. Aplikasi tidak
bergantung pada Fuseki untuk pencarian, daftar, detail, chat, maupun API.

## Pencarian

Semua jalur pencarian memakai satu service, `App\Services\AirportSearch`:

| Jalur | Keterangan |
| --- | --- |
| `GET /cari?q=` | Halaman hasil dengan filter, facet, tab, dan paginasi |
| `GET /cari/saran?q=` | Autocomplete JSON (maks. 8 saran) |
| `GET /bandara` | Jelajah seluruh bandara |
| `GET /api/v1/airports?q=` | API pencarian |
| `GET /chat?q=` | Chat tanya-jawab |

Normalisasi: huruf kecil, tanda baca menjadi spasi, kata umum seperti "bandara" dan "kota"
diabaikan. Kandidat diambil dengan `LIKE` dari tabel `airports`, lalu diberi skor di PHP.
Pendekatan ini cukup untuk ~617 bandara dan tidak memerlukan Meilisearch/Scout.

Hasil pencarian di-cache 90 detik, saran 60 detik, facet/statistik beranda 10 menit, dan
sitemap 6 jam.

## Halaman dan Endpoint

| URL | Fungsi |
| --- | --- |
| `/` | Beranda dan statistik dari database |
| `/cari` | Pencarian terpadu |
| `/bandara` | Jelajah bandara |
| `/bandara/{iata}` | Detail bandara (URL kanonik) |
| `/rute` | Pencarian rute langsung + satu transit |
| `/data/bandara`, `/data/maskapai`, `/data/rute`, `/data/peta` | Halaman data relasional |
| `/data/bandara/{iata}` | Dialihkan permanen (301) ke `/bandara/{iata}` |
| `/chat?q=` | Jawaban chat dalam JSON |
| `/sitemap.xml`, `/robots.txt` | SEO dinamis |
| `/api/v1/airports`, `/api/v1/airlines`, `/api/v1/routes`, `/api/v1/stats` | API JSON |

Parameter API pencarian mengikuti UI: `q`, `negara`, `kategori`, `asean`, `rute`, `urut`,
`page`, `per_page`, dan `near=lat,lon&radius=km`.

### Ketahanan dan keamanan

- Semua panggilan Wikipedia, Wikimedia, Wikidata, DBpedia, AviationStack, dan Fuseki
  dibungkus `try/catch` dengan timeout dan fallback; detail bandara tetap tampil saat
  layanan luar mati.
- Rate limit: `/cari` 90/menit, `/cari/saran` 120/menit, `/api/v1/*` 120/menit.
- Input dibatasi panjangnya, karakter kontrol dibersihkan, dan escaping SPARQL terpusat.
- Output Blade di-escape; JSON-LD memakai `JSON_HEX_TAG` agar tidak bisa dipakai
  menyisipkan `</script>`.
- Index database ditambahkan untuk kolom pencarian dan foreign key.

## Menggabungkan Vocabulary Lama

Bila memiliki `backup_data.nq` dengan namespace `bandara#`:

```bash
php artisan data:merge-vocab database/data/backup_data.nq
```

Perintah membaca N-Quads, memetakan subjek/predikat lama ke `asean-airport#` berdasarkan
IATA, **tidak menimpa** nilai kanonik, lalu menulis:

- `database/data/merged-bandara.nq`
- `database/data/merge-report.json` (jumlah kanonik/lama/gabungan, IATA yang hanya ada di
  salah satu vocabulary, dan daftar konflik nilai)

Berkas sumber asli tidak diubah. Lokasi keluaran bisa diatur dengan `--out` dan `--report`.

## Pengujian

```bash
php artisan test
vendor/bin/pint --dirty
```

Cakupan pengujian mencakup peringkat pencarian, typo, alias, paginasi, filter, pencarian
radius, autocomplete, gabungan hasil bandara/maskapai/rute, SEO, fallback saat layanan luar
mati, API, chat, impor RDF, dan perintah merge vocabulary.

## Catatan Pengembangan

- Jika perubahan CSS/JS tidak terlihat, jalankan `npm run build` atau `npm run dev`.
- Dataset uji memakai `tests/Fixtures/sample-bandara.ttl` dan `tests/Support/MembuatDataBandara.php`.
- Semua URL dibangun dengan named route agar canonical dan redirect tetap konsisten.
