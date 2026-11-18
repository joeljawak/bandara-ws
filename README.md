# BANDARA ASEAN

Aplikasi pencarian bandara, maskapai, dan rute di Asia Tenggara berbasis Web Semantik.
**Apache Jena Fuseki adalah database aplikasi ini.** Setiap permintaan membaca data langsung
dari Fuseki lewat SPARQL. Kalau Fuseki tidak berjalan, halaman menampilkan pesan dan tidak ada
pencarian yang bisa dilakukan.

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
- Chat tanya-jawab yang membaca indeks hasil sinkronisasi dari Fuseki.

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

Atur `SPARQL_ENDPOINT` di `.env`, jalankan Fuseki (lihat bagian di bawah), lalu:

```bash
php artisan migrate          # tabel bawaan Laravel (sesi, cache); data bandara tidak disimpan di sini
php artisan fuseki:load      # muat data ke Fuseki, cukup sekali
npm install
npm run build
php artisan serve
```

### Fuseki sebagai database (wajib)

1. Jalankan Apache Jena Fuseki, lalu buat dataset bernama `bandara` (tipe persistent TDB2).
   Dengan opsi `--mem` data hilang setiap Fuseki dimatikan.
2. Pastikan `.env` menunjuk ke dataset itu:

   ```dotenv
   SPARQL_ENDPOINT=http://localhost:3030/bandara/sparql
   # opsional, kalau alamat dataset tidak berakhiran /sparql
   # SPARQL_DATASET_URL=http://localhost:3030/bandara
   # detik hasil unduhan dipakai ulang (0 = baca ulang dari Fuseki di setiap permintaan)
   # FUSEKI_CACHE_SECONDS=10
   ```

   `FUSEKI_URL` masih dibaca sebagai fallback agar `.env` lama tetap berjalan.
3. Muat data ke Fuseki: `php artisan fuseki:load`
   (memuat `database/data/bandara.ttl` dan `tambahan_wikidata.ttl`; `--replace` mengganti isi dataset).
4. Cek koneksi kapan saja: `php artisan fuseki:status`

**Cara kerja.** Pada setiap permintaan aplikasi (1) mengecek Fuseki lewat query SPARQL, (2) mengunduh
seluruh graph lewat `CONSTRUCT { ?s ?p ?o } WHERE { ?s ?p ?o }`, lalu (3) memuatnya ke database
SQLite sementara di memori supaya peringkat, toleransi salah ketik, dan autocomplete bisa memakai
query relasional. Salinan itu hilang saat permintaan selesai, jadi tidak ada data yang tinggal di
luar Fuseki. Perubahan data di Fuseki langsung terlihat di web.

- Fuseki mati atau dataset kosong: semua halaman data (pencarian, jelajah, rute, API, chat)
  menampilkan pesan "Apache Jena Fuseki tidak tersedia" (HTTP 503). `/robots.txt` tetap terbuka.
- Hasil unduhan dipakai ulang selama `FUSEKI_CACHE_SECONDS` detik (bawaan 10) supaya klik beruntun
  tidak mengunduh ulang puluhan ribu triple. Fuseki tetap dicek di setiap permintaan, dan
  perubahan jumlah triple terdeteksi seketika.
- `import:airports-ttl` mengisi database lokal langsung dari berkas. Itu hanya dipakai bersama
  `FUSEKI_LIVE=false` (pengujian atau pengembangan tanpa Fuseki), bukan untuk penggunaan biasa.

| Perintah | Fungsi |
| --- | --- |
| `php artisan fuseki:load` | Muat berkas Turtle ke Fuseki (`--replace` untuk mengganti isi dataset) |
| `php artisan fuseki:status` | Cek koneksi Fuseki dan jumlah triple |

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
