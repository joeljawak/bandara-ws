# Bantuan membaca data dari Fuseki (alamat http) atau dari berkas RDF (.ttl, .rdf).
# Dipakai oleh audit_dataset.py dan lengkapi_dari_wikidata.py

PREFIX = """PREFIX ex: <http://example.org/asean-airport#>
PREFIX geo1: <http://www.w3.org/2003/01/geo/wgs84_pos#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX owl: <http://www.w3.org/2002/07/owl#>
"""

DEFAULT = "http://localhost:3030/bandara/sparql"


class Sumber:
    def __init__(self, alamat):
        self.alamat = alamat
        self.graph = None
        if not alamat.lower().startswith("http"):
            import rdflib  # hanya dibutuhkan kalau membaca berkas
            self.graph = rdflib.Graph()
            self.graph.parse(alamat)

    def query(self, sparql):
        """Hasilnya daftar dict, nilai berupa teks."""
        if self.graph is not None:
            hasil = self.graph.query(PREFIX + sparql)
            return [
                {str(k): str(v) for k, v in zip(hasil.vars, baris) if v is not None}
                for baris in hasil
            ]
        import requests
        res = requests.post(
            self.alamat,
            data={"query": PREFIX + sparql},
            headers={"Accept": "application/sparql-results+json"},
            timeout=60,
        )
        res.raise_for_status()
        return [
            {k: v["value"] for k, v in b.items()}
            for b in res.json()["results"]["bindings"]
        ]
