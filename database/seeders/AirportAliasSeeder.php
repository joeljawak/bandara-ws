<?php

namespace Database\Seeders;

use App\Models\Airport;
use App\Models\AirportAlias;
use Illuminate\Database\Seeder;

class AirportAliasSeeder extends Seeder
{
    /**
     * Nama populer yang sering diketik pengguna tetapi tidak selalu ada di label resmi.
     *
     * @var array<string, list<string>>
     */
    private const ALIAS = [
        'CGK' => ['Cengkareng', 'Soekarno Hatta', 'Soetta', 'Jakarta'],
        'DPS' => ['Ngurah Rai', 'Denpasar', 'Bali'],
        'HLP' => ['Halim', 'Halim Perdanakusuma', 'Jakarta'],
        'KNO' => ['Kualanamu', 'Medan', 'Deli Serdang'],
        'SIN' => ['Changi', 'Singapore Changi'],
        'KUL' => ['KLIA', 'Kuala Lumpur International'],
        'DMK' => ['Don Mueang', 'Don Muang', 'Bangkok'],
        'BKK' => ['Suvarnabhumi', 'Suvarnabumi', 'Bangkok'],
        'HKT' => ['Phuket', 'Phuket International'],
        'CNX' => ['Chiang Mai'],
        'PNH' => ['Phnom Penh', 'Pochentong'],
        'REP' => ['Siem Reap', 'Angkor'],
        'SGN' => ['Tan Son Nhat', 'Ho Chi Minh', 'Saigon'],
        'HAN' => ['Noi Bai', 'Hanoi'],
        'DAD' => ['Da Nang'],
        'RGN' => ['Yangon', 'Rangoon'],
        'MNL' => ['Ninoy Aquino', 'Manila'],
        'CEB' => ['Cebu', 'Mactan'],
        'BWN' => ['Brunei International', 'Bandar Seri Begawan'],
        'DIL' => ['Presidente Nicolau Lobato', 'Dili', 'Timor Leste'],
        'UPG' => ['Makassar', 'Sultan Hasanuddin'],
        'SUB' => ['Juanda', 'Surabaya'],
        'SOC' => ['Adi Soemarmo', 'Solo', 'Surakarta'],
        'JOG' => ['Adisutjipto', 'Yogyakarta'],
        'YIA' => ['Yogyakarta International', 'Kulon Progo'],
        'BPN' => ['Sultan Aji Muhammad Sulaiman', 'Balikpapan'],
        'PLM' => ['Sultan Mahmud Badaruddin II', 'Palembang'],
        'PKU' => ['Sultan Syarif Kasim II', 'Pekanbaru'],
        'BTJ' => ['Sultan Iskandar Muda', 'Banda Aceh'],
        'PDG' => ['Minangkabau', 'Padang'],
        'MDC' => ['Sam Ratulangi', 'Manado'],
        'SRG' => ['Ahmad Yani', 'Semarang'],
        'BDO' => ['Husein Sastranegara', 'Bandung'],
        'TKG' => ['Radin Inten II', 'Lampung'],
    ];

    public function run(): void
    {
        $airports = Airport::query()
            ->whereNotNull('iata_code')
            ->get(['id', 'iata_code', 'label', 'local_name'])
            ->keyBy(fn (Airport $airport): string => strtoupper((string) $airport->iata_code));

        foreach (self::ALIAS as $iata => $aliases) {
            $airport = $airports->get($iata);

            if (! $airport) {
                continue;
            }

            foreach ($aliases as $alias) {
                $this->simpan($airport, $alias);
            }
        }

        foreach ($airports as $airport) {
            $this->simpan($airport, (string) $airport->local_name);
        }
    }

    private function simpan(Airport $airport, string $alias): void
    {
        $alias = trim($alias);

        if ($alias === '' || mb_strtolower($alias) === mb_strtolower((string) $airport->label)) {
            return;
        }

        AirportAlias::query()->firstOrCreate([
            'airport_id' => $airport->id,
            'alias' => $alias,
        ]);
    }
}
