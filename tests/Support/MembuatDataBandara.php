<?php

namespace Tests\Support;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\AirportAlias;
use App\Models\Country;
use App\Models\Region;
use App\Models\Route;

/**
 * Data kecil yang meniru sebagian dataset bandara ASEAN untuk pengujian.
 */
trait MembuatDataBandara
{
    /**
     * @return array{
     *     negara: array<string, Country>,
     *     wilayah: array<string, Region>,
     *     bandara: array<string, Airport>,
     *     maskapai: array<string, Airline>
     * }
     */
    protected function buatDuniaBandara(): array
    {
        $negara = [];

        foreach ([
            'Indonesia' => true,
            'Malaysia' => true,
            'Thailand' => true,
            'Singapura' => true,
            'Vietnam' => true,
            'Australia' => false,
        ] as $label => $asean) {
            $negara[$label] = Country::create([
                'external_id' => 'country-'.strtolower($label),
                'label' => $label,
                'member_of_asean' => $asean,
            ]);
        }

        $wilayah = [];

        foreach ([
            'DKI Jakarta' => 'Indonesia',
            'Sumatera Utara' => 'Indonesia',
            'Bali' => 'Indonesia',
            'Selangor' => 'Malaysia',
            'Bangkok' => 'Thailand',
            'Singapore' => 'Singapura',
            'Hanoi' => 'Vietnam',
        ] as $label => $namaNegara) {
            $wilayah[$label] = Region::create([
                'external_id' => 'region-'.strtolower(str_replace(' ', '-', $label)),
                'label' => $label,
                'country_id' => $negara[$namaNegara]->id,
            ]);
        }

        $bandara = [];

        $daftar = [
            ['CGK', 'Soekarno-Hatta International Airport', 'Indonesia', 'DKI Jakarta', -6.1256, 106.6558, 54000000, 120, 'International', 'PT Angkasa Pura II', ['Cengkareng', 'Soekarno Hatta', 'Jakarta']],
            ['KNO', 'Kualanamu International Airport', 'Indonesia', 'Sumatera Utara', 3.6422, 98.8853, 8000000, 30, 'International', 'PT Angkasa Pura II', ['Kualanamu', 'Medan']],
            ['DPS', 'I Gusti Ngurah Rai International Airport', 'Indonesia', 'Bali', -8.7482, 115.1672, 20000000, 45, 'International', 'PT Angkasa Pura I', ['Ngurah Rai', 'Bali', 'Denpasar']],
            ['SIN', 'Singapore Changi Airport', 'Singapura', 'Singapore', 1.3644, 103.9915, 68000000, 150, 'International', 'Changi Airport Group', ['Changi', 'Changi Airport']],
            ['KUL', 'Kuala Lumpur International Airport', 'Malaysia', 'Selangor', 2.7456, 101.7099, 60000000, 130, 'International', 'Malaysia Airports', ['KLIA', 'Kuala Lumpur']],
            ['BKK', 'Suvarnabhumi Airport', 'Thailand', 'Bangkok', 13.6900, 100.7501, 65000000, 140, 'International', 'Airports of Thailand', ['Suvarnabhumi', 'Bangkok']],
            ['HAN', 'Noi Bai International Airport', 'Vietnam', 'Hanoi', 21.2212, 105.8072, 29000000, 70, 'International', 'Airports Corporation of Vietnam', ['Noi Bai', 'Hanoi']],
            ['SYD', 'Sydney Kingsford Smith Airport', 'Australia', null, -33.9399, 151.1753, 44000000, 100, 'International', 'Sydney Airport', ['Kingsford Smith']],
            ['AEG', 'Aek Godang Airport', 'Indonesia', 'Sumatera Utara', 1.4001, 99.4305, null, 2, 'Domestic', 'PT Angkasa Pura II', ['Aek Godang']],
        ];

        foreach ($daftar as [$iata, $label, $namaNegara, $namaWilayah, $lat, $lon, $pax, $tujuan, $kategori, $operator, $alias]) {
            $airport = Airport::create([
                'external_id' => 'airport-'.$iata,
                'label' => $label,
                'iata_code' => $iata,
                'icao_code' => $iata === 'CGK' ? 'WIII' : null,
                'category' => $kategori,
                'status' => 'Active',
                'latitude' => $lat,
                'longitude' => $lon,
                'country_id' => $negara[$namaNegara]->id,
                'region_id' => $namaWilayah ? $wilayah[$namaWilayah]->id : null,
                'in_asean' => $negara[$namaNegara]->member_of_asean,
                'annual_passengers' => $pax,
                'number_of_destinations' => $tujuan,
                'operator' => $operator,
            ]);

            foreach ($alias as $nilai) {
                AirportAlias::create(['airport_id' => $airport->id, 'alias' => $nilai]);
            }

            $bandara[$iata] = $airport;
        }

        for ($i = 1; $i <= 20; $i++) {
            $iata = 'T'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            Airport::create([
                'external_id' => 'airport-'.$iata,
                'label' => 'Bandara Uji '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'iata_code' => $iata,
                'category' => 'Domestic',
                'status' => 'Active',
                'country_id' => $negara['Indonesia']->id,
                'region_id' => $wilayah['DKI Jakarta']->id,
                'in_asean' => true,
            ]);
        }

        $maskapai = [];

        foreach ([
            'Garuda Indonesia' => 'GA',
            'AirAsia' => 'AK',
            'Singapore Airlines' => 'SQ',
        ] as $label => $kode) {
            $maskapai[$kode] = Airline::create([
                'external_id' => 'airline-'.$kode,
                'label' => $label,
                'airline_code' => $kode,
            ]);
        }

        Route::create([
            'external_id' => 'route-ga-cgk-kno',
            'origin_airport_id' => $bandara['CGK']->id,
            'destination_airport_id' => $bandara['KNO']->id,
            'airline_id' => $maskapai['GA']->id,
        ]);
        Route::create([
            'external_id' => 'route-ga-cgk-dps',
            'origin_airport_id' => $bandara['CGK']->id,
            'destination_airport_id' => $bandara['DPS']->id,
            'airline_id' => $maskapai['GA']->id,
        ]);
        Route::create([
            'external_id' => 'route-ak-cgk-dps',
            'origin_airport_id' => $bandara['CGK']->id,
            'destination_airport_id' => $bandara['DPS']->id,
            'airline_id' => $maskapai['AK']->id,
        ]);
        Route::create([
            'external_id' => 'route-sq-sin-cgk',
            'origin_airport_id' => $bandara['SIN']->id,
            'destination_airport_id' => $bandara['CGK']->id,
            'airline_id' => $maskapai['SQ']->id,
        ]);

        return compact('negara', 'wilayah', 'bandara', 'maskapai');
    }
}
