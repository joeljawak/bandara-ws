# Lengkapi data bandara yang masih kosong memakai Wikidata (SPARQL endpoint eksternal):
# kode ICAO, koordinat, pengelola, jumlah penumpang, tahun dibuka, website, dan
# tautan owl:sameAs ke DBpedia (syarat dosen: integrasi dengan dataset eksternal).
#
# Hanya data yang BELUM ada di datasetmu yang ditambahkan, data lama tidak diubah.
#
# Cara pakai (Fuseki harus berjalan, internet aktif):
#   pip install requests
#   python lengkapi_dari_wikidata.py
# Atau dari berkas RDF:
#   pip install requests rdflib
#   python lengkapi_dari_wikidata.py bandara.ttl
#
# Hasil: tambahan_wikidata.ttl, muat ke Fuseki sebagai berkas tambahan.

import re
import sys
from urllib.parse import unquote

import requests

from sumber import DEFAULT, Sumber

WIKIDATA = "https://query.wikidata.org/sparql"
HEADERS = {
    "User-Agent": "BandaraASEAN-TugasKuliah/1.0 (ganti dengan email kamu)",
    "Accept": "application/sparql-results+json",
}

# properti yang dicek keberadaannya di dataset
PROPERTI = {
    "icao": "ex:hasICAOCode",
    "lat": "geo1:lat",
    "operator": "ex:operator",
    "penumpang": "ex:annualPassengers",
    "tahun": "ex:openedYear",
    "website": "ex:website",
    "sameas": "owl:sameAs",
}


def query_wikidata(daftar_iata):
    nilai = " ".join('"%s"' % k for k in daftar_iata)
    q = """
SELECT ?item ?iata ?icao ?operatorLabel ?inception ?website ?coord ?passengers ?passTime ?article WHERE {
  VALUES ?iata { %s }
  ?item wdt:P238 ?iata .
  OPTIONAL { ?item wdt:P239 ?icao }
  OPTIONAL { ?item wdt:P137 ?operator }
  OPTIONAL { ?item wdt:P571 ?inception }
  OPTIONAL { ?item wdt:P856 ?website }
  OPTIONAL { ?item wdt:P625 ?coord }
  OPTIONAL { ?item p:P3872 ?ps . ?ps ps:P3872 ?passengers . OPTIONAL { ?ps pq:P585 ?passTime } }
  OPTIONAL { ?article schema:about ?item ; schema:isPartOf <https://en.wikipedia.org/> }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en,id". }
}""" % nilai
    res = requests.get(WIKIDATA, params={"query": q}, headers=HEADERS, timeout=120)
    res.raise_for_status()
    return res.json()["results"]["bindings"]


def val(b, k):
    return b[k]["value"] if k in b else ""


def olah(bindings):
    """Gabungkan banyak baris hasil menjadi satu data per kode IATA."""
    data = {}
    for b in bindings:
        iata = val(b, "iata")
        d = data.setdefault(iata, {
            "item": val(b, "item"), "icao": "", "operator": set(), "tahun": "",
            "website": "", "lat": "", "lon": "", "penumpang": "", "tahun_penumpang": "",
            "dbpedia": "",
        })
        if not d["icao"]:
            d["icao"] = val(b, "icao")
        op = val(b, "operatorLabel")
        if op and not re.fullmatch(r"Q\d+", op):
            d["operator"].add(op)
        th = val(b, "inception")[:4]
        if th.isdigit() and (not d["tahun"] or th < d["tahun"]):
            d["tahun"] = th
        if not d["website"]:
            d["website"] = val(b, "website")
        m = re.fullmatch(r"Point\(([-\d.eE]+) ([-\d.eE]+)\)", val(b, "coord"))
        if m and not d["lat"]:
            d["lon"], d["lat"] = m.group(1), m.group(2)
        p, t = val(b, "passengers"), val(b, "passTime")[:4]
        if p and (not d["tahun_penumpang"] or t > d["tahun_penumpang"]):
            try:
                d["penumpang"] = str(int(float(p)))
                d["tahun_penumpang"] = t
            except ValueError:
                pass
        art = val(b, "article")
        if art and not d["dbpedia"] and "/wiki/" in art:
            # judul artikel Wikipedia sama dengan nama resource DBpedia
            d["dbpedia"] = "http://dbpedia.org/resource/" + unquote(art.split("/wiki/", 1)[1])
    return data


def esc(teks):
    return teks.replace("\\", "\\\\").replace('"', '\\"')


def iri_aman(url):
    url = url.strip().replace(" ", "%20")
    return url if re.match(r"^https?://", url) and not re.search(r'[<>"{}|^`\\]', url) else ""


def susun(data, iata_ke_uri, ada):
    """Buat triple hanya untuk data yang belum ada. Mengembalikan (teks ttl, jumlah per jenis)."""
    baris = [
        "@prefix ex: <http://example.org/asean-airport#> .",
        "@prefix geo1: <http://www.w3.org/2003/01/geo/wgs84_pos#> .",
        "@prefix owl: <http://www.w3.org/2002/07/owl#> .",
        "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .",
        "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .",
        "",
    ]
    hitung = {k: 0 for k in PROPERTI}
    for iata, d in sorted(data.items()):
        uri = iata_ke_uri.get(iata)
        if not uri:
            continue
        sudah = {k: uri in ada[k] for k in PROPERTI}
        po = []
        if d["icao"] and not sudah["icao"]:
            po.append('ex:hasICAOCode "%s"' % esc(d["icao"]))
            hitung["icao"] += 1
        if d["lat"] and not sudah["lat"]:
            po.append('geo1:lat "%s"^^xsd:decimal' % d["lat"])
            po.append('geo1:long "%s"^^xsd:decimal' % d["lon"])
            hitung["lat"] += 1
        if d["operator"] and not sudah["operator"]:
            po.append('ex:operator "%s"' % esc(", ".join(sorted(d["operator"]))))
            hitung["operator"] += 1
        if d["penumpang"] and not sudah["penumpang"]:
            po.append('ex:annualPassengers "%s"^^xsd:integer' % d["penumpang"])
            hitung["penumpang"] += 1
        if d["tahun"] and not sudah["tahun"]:
            po.append('ex:openedYear "%s"^^xsd:integer' % d["tahun"])
            hitung["tahun"] += 1
        web = iri_aman(d["website"]) if d["website"] else ""
        if web and not sudah["website"]:
            po.append("ex:website <%s>" % web)
            hitung["website"] += 1
        dbp = iri_aman(d["dbpedia"]) if d["dbpedia"] else ""
        if dbp and not sudah["sameas"]:
            po.append("owl:sameAs <%s>" % dbp)
            hitung["sameas"] += 1
        if d["item"] and not sudah["sameas"]:
            po.append("rdfs:seeAlso <%s>" % d["item"])
        if po:
            baris.append("<%s> %s ." % (uri, " ;\n    ".join(po)))
            baris.append("")
    return "\n".join(baris), hitung


def main():
    sumber = Sumber(sys.argv[1] if len(sys.argv) > 1 else DEFAULT)

    bandara = sumber.query(
        "SELECT ?a ?iata WHERE { ?a a ex:Airport ; ex:hasIATACode ?iata }"
    )
    iata_ke_uri = {}
    for b in bandara:
        iata_ke_uri.setdefault(b["iata"].upper(), b["a"])
    if not iata_ke_uri:
        print("Tidak ada bandara ditemukan. Cek alamat Fuseki atau nama dataset.")
        return

    ada = {}
    for kunci, prop in PROPERTI.items():
        ada[kunci] = {r["a"] for r in sumber.query("SELECT DISTINCT ?a WHERE { ?a %s ?v }" % prop)}

    print(f"Bandara di datasetmu: {len(iata_ke_uri)}. Mengambil data dari Wikidata...")
    kode = sorted(iata_ke_uri)
    bindings = []
    for i in range(0, len(kode), 80):
        bindings += query_wikidata(kode[i:i + 80])
        print(f"  {min(i + 80, len(kode))}/{len(kode)}")

    data = olah(bindings)
    teks, hitung = susun(data, iata_ke_uri, ada)
    with open("tambahan_wikidata.ttl", "w", encoding="utf-8") as f:
        f.write(teks)

    print(f"\nDitemukan di Wikidata: {len(data)} dari {len(iata_ke_uri)} bandara")
    nama = {"icao": "kode ICAO", "lat": "koordinat", "operator": "pengelola",
            "penumpang": "penumpang per tahun", "tahun": "tahun dibuka",
            "website": "website", "sameas": "tautan DBpedia (owl:sameAs)"}
    print("Data baru yang ditambahkan:")
    for k, n in hitung.items():
        print(f"  {n:4}  {nama[k]}")
    tidak = sorted(set(iata_ke_uri) - set(data))
    if tidak:
        print(f"\nTidak ada di Wikidata ({len(tidak)}): {', '.join(tidak[:25])}{' ...' if len(tidak) > 25 else ''}")
    print("\nSelesai: tambahan_wikidata.ttl. Upload ke Fuseki sebagai berkas tambahan, jangan hapus data lama.")


if __name__ == "__main__":
    main()
