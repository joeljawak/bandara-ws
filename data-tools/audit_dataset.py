# Cek kelengkapan dataset bandara: data apa yang masih kosong dan di bandara mana.
#
# Cara pakai (Fuseki harus berjalan):
#   pip install requests
#   python audit_dataset.py
# Atau dari berkas RDF:
#   pip install rdflib
#   python audit_dataset.py bandara.ttl
#
# Hasil: ringkasan di layar dan berkas laporan_kelengkapan.csv

import csv
import sys

from sumber import DEFAULT, Sumber

# (nama di laporan, pola triple yang harus ada, penting untuk syarat dosen?)
BIDANG = [
    ("kode ICAO", "?a ex:hasICAOCode ?v", False),
    ("negara", "?a ex:locatedInCountry ?v", True),
    ("wilayah/kota", "?a ex:locatedInRegion ?v", False),
    ("latitude", "?a geo1:lat ?v", True),
    ("longitude", "?a geo1:long ?v", True),
    ("status", "?a ex:airportStatus ?v", False),
    ("pengelola", "?a ex:operator ?v", False),
    ("penumpang per tahun", "?a ex:annualPassengers ?v", False),
    ("landasan terpanjang", "?a ex:longestRunwayLength ?v", False),
    ("tautan eksternal (owl:sameAs)", "?a owl:sameAs ?v", True),
    ("punya rute keluar", "?r a ex:Route ; ex:origin ?a", False),
]


def main():
    sumber = Sumber(sys.argv[1] if len(sys.argv) > 1 else DEFAULT)

    semua = sumber.query(
        "SELECT ?iata ?nama WHERE { ?a a ex:Airport ; ex:hasIATACode ?iata ; rdfs:label ?nama }"
    )
    total = len(semua)
    if total == 0:
        print("Tidak ada bandara ditemukan. Cek alamat Fuseki atau nama dataset.")
        return

    kosong = {}  # iata -> daftar bidang yang kosong
    print(f"Total bandara: {total}\n")
    print(f"{'Data':32} {'terisi':>8} {'kosong':>8}  {'%':>6}")
    print("-" * 60)
    for nama, pola, penting in BIDANG:
        hasil = sumber.query(
            "SELECT ?iata WHERE { ?a a ex:Airport ; ex:hasIATACode ?iata . "
            f"FILTER NOT EXISTS {{ {pola} }} }}"
        )
        iata_kosong = {h["iata"] for h in hasil}
        for i in iata_kosong:
            kosong.setdefault(i, []).append(nama)
        isi = total - len(iata_kosong)
        tanda = "  <- syarat dosen" if penting and len(iata_kosong) else ""
        print(f"{nama:32} {isi:>8} {len(iata_kosong):>8}  {100 * isi / total:>5.0f}%{tanda}")

    with open("laporan_kelengkapan.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["iata", "nama", "jumlah_kosong", "data_yang_kosong"])
        for b in sorted(semua, key=lambda x: -len(kosong.get(x["iata"], []))):
            k = kosong.get(b["iata"], [])
            w.writerow([b["iata"], b["nama"], len(k), "; ".join(k)])

    lengkap = sum(1 for b in semua if not kosong.get(b["iata"]))
    print(f"\nBandara dengan semua data terisi: {lengkap} dari {total}")
    print("Laporan per bandara: laporan_kelengkapan.csv")


if __name__ == "__main__":
    main()
