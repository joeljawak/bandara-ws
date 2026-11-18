<?php

namespace App\Console\Commands;

use App\Services\SparqlService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fuseki:status')]
#[Description('Cek apakah Apache Jena Fuseki bisa dihubungi dan berapa triple yang tersimpan')]
class FusekiStatus extends Command
{
    public function handle(SparqlService $sparql): int
    {
        $this->line('Endpoint SPARQL : '.config('sparql.endpoint'));
        $this->line('Dataset         : '.$sparql->datasetUrl());
        $this->line('Mode            : '.(config('sparql.live') ? 'live (data dibaca dari Fuseki)' : 'lokal (FUSEKI_LIVE=false)'));

        $status = $sparql->status();

        if (! $status['ok']) {
            $this->error('Fuseki tidak bisa dihubungi: '.$status['error']);

            return self::FAILURE;
        }

        $this->info('Fuseki terhubung, berisi '.number_format((int) $status['triples'], 0, ',', '.').' triple.');

        if (($status['triples'] ?? 0) === 0) {
            $this->warn('Dataset kosong. Jalankan: php artisan fuseki:load');
        }

        return self::SUCCESS;
    }
}
