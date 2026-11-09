# Melengkapi dataset sesuai syarat dosen

## Urutan kerja
1. Pasang alat: `pip install requests rdflib`
2. Taruh tiga berkas .py di folder ini dalam satu folder, lalu jalankan (Fuseki harus hidup):
   `python audit_dataset.py`
   Hasilnya tabel kelengkapan di layar dan `laporan_kelengkapan.csv` (per bandara).
3. Lengkapi dari Wikidata (internet aktif):
   `python lengkapi_dari_wikidata.py`
   Hasilnya `tambahan_wikidata.ttl`. Hanya data yang belum ada yang ditambahkan, data lama tidak diubah.
4. Buka http://localhost:3030, pilih dataset `bandara`, tab "upload files", pilih `tambahan_wikidata.ttl`,
   lalu upload. Jangan hapus data lama, kedua berkas otomatis bergabung.
5. Jalankan `python audit_dataset.py` lagi dan bandingkan angkanya dengan yang pertama.

Kalau Fuseki belum bisa dipakai, kedua skrip juga bisa membaca berkas RDF langsung:
`python audit_dataset.py bandara.ttl`

## Yang diisi oleh skrip dari Wikidata
kode ICAO, koordinat, pengelola, jumlah penumpang, tahun dibuka, website, dan owl:sameAs ke DBpedia.

## Yang tidak bisa diisi Wikidata (isi dari berkas data kalian sendiri)
- Landasan terpanjang: dari runways.csv
- Rute dan maskapai: dari rute.csv
- Status dan kategori bandara: dari data awal kalian

## Hubungan dengan syarat dosen
| Syarat di slide | Cara dipenuhi |
|---|---|
| Dataset buatan sendiri dengan SPARQL endpoint | Dataset RDF kalian di Fuseki |
| SPARQL endpoint eksternal | Halaman detail memanggil SPARQL endpoint DBpedia secara langsung (DbpediaService) |
| Integrasi dataset sendiri dengan dataset eksternal | owl:sameAs ke DBpedia dan Wikidata (hasil lengkapi_dari_wikidata.py) |
| File RDF | Berkas .ttl yang sama dilampirkan saat pengumpulan |
| Laman RDFa dan Open Graph | Halaman detail bandara |
| Integrasi layanan lain | OpenStreetMap, Wikipedia, Esri, AviationStack |
