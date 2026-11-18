<?php

namespace App\Models\Concerns;

/**
 * Saat mode live aktif, data bandara dibaca dari salinan sementara yang dimuat
 * dari Apache Jena Fuseki pada setiap permintaan (lihat FusekiDataset).
 */
trait UsesFusekiConnection
{
    public function getConnectionName(): ?string
    {
        return config('sparql.live') ? 'fuseki' : parent::getConnectionName();
    }
}
