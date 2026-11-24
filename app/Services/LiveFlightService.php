<?php

namespace App\Services;

use App\Models\Airport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiveFlightService
{
    private const KNOWN_AIRLINES = [
        'GIA' => ['Garuda Indonesia', '🇮🇩'],
        'LNI' => ['Lion Air', '🇮🇩'],
        'CTV' => ['Citilink', '🇮🇩'],
        'BTK' => ['Batik Air', '🇮🇩'],
        'AWQ' => ['Indonesia AirAsia', '🇮🇩'],
        'SJY' => ['Sriwijaya Air', '🇮🇩'],
        'NAM' => ['NAM Air', '🇮🇩'],
        'WON' => ['Wings Air', '🇮🇩'],
        'TNK' => ['TransNusa', '🇮🇩'],
        'SIA' => ['Singapore Airlines', '🇸🇬'],
        'SLK' => ['SilkAir', '🇸🇬'],
        'TGW' => ['Scoot', '🇸🇬'],
        'MAS' => ['Malaysia Airlines', '🇲🇾'],
        'AXM' => ['AirAsia', '🇲🇾'],
        'MXD' => ['Batik Air Malaysia', '🇲🇾'],
        'FFM' => ['Firefly', '🇲🇾'],
        'THA' => ['Thai Airways', '🇹🇭'],
        'BKP' => ['Bangkok Airways', '🇹🇭'],
        'NOK' => ['Nok Air', '🇹🇭'],
        'AIQ' => ['Thai AirAsia', '🇹🇭'],
        'PAL' => ['Philippine Airlines', '🇵🇭'],
        'CEB' => ['Cebu Pacific', '🇵🇭'],
        'APG' => ['Philippines AirAsia', '🇵🇭'],
        'HVN' => ['Vietnam Airlines', '🇻🇳'],
        'VJC' => ['VietJet Air', '🇻🇳'],
        'BAV' => ['Bamboo Airways', '🇻🇳'],
        'RBA' => ['Royal Brunei Airlines', '🇧🇳'],
        'LAO' => ['Lao Airlines', '🇱🇦'],
        'KHV' => ['Cambodia Angkor Air', '🇰🇭'],
        'QFA' => ['Qantas', '🇦🇺'],
        'JST' => ['Jetstar', '🇦🇺'],
        'VOZ' => ['Virgin Australia', '🇦🇺'],
        'UAE' => ['Emirates', '🇦🇪'],
        'ETD' => ['Etihad Airways', '🇦🇪'],
        'QTR' => ['Qatar Airways', '🇶🇦'],
        'CSN' => ['China Southern Airlines', '🇨🇳'],
        'CES' => ['China Eastern Airlines', '🇨🇳'],
        'CCA' => ['Air China', '🇨🇳'],
        'CPA' => ['Cathay Pacific', '🇭🇰'],
        'CAL' => ['China Airlines', '🇹🇼'],
        'EVA' => ['EVA Air', '🇹🇼'],
        'JAL' => ['Japan Airlines', '🇯🇵'],
        'ANA' => ['All Nippon Airways', '🇯🇵'],
        'KAL' => ['Korean Air', '🇰🇷'],
        'AAR' => ['Asiana Airlines', '🇰🇷'],
        'DLH' => ['Lufthansa', '🇩🇪'],
        'BAW' => ['British Airways', '🇬🇧'],
        'AFR' => ['Air France', '🇫🇷'],
        'KLM' => ['KLM', '🇳🇱'],
        'THY' => ['Turkish Airlines', '🇹🇷'],
        'SAU' => ['Saudia', '🇸🇦'],
    ];

    /**
     * Ambil data posisi penerbangan live di kawasan Indonesia & ASEAN dari OpenSky Network.
     * Menggunakan cache 8 detik agar hemat kuota dan sangat responsif.
     */
    public function getLiveFlights(): array
    {
        return Cache::remember('live_flights_radar_cache_v1', 8, function (): array {
            try {
                // Kotak koordinat Indonesia & ASEAN: Lat -11.5 s.d 8.5, Lon 95.0 s.d 141.5
                $client = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'Bandara-ASEAN-Research/1.0']);

                $user = config('services.opensky.user');
                $pass = config('services.opensky.pass');
                if ($user && $pass) {
                    $client = $client->withBasicAuth($user, $pass);
                }

                $response = $client->get('https://opensky-network.org/api/states/all', [
                    'lamin' => -11.5,
                    'lomin' => 95.0,
                    'lamax' => 8.5,
                    'lomax' => 141.5,
                ]);

                if ($response->successful()) {
                    $rawStates = $response->json('states') ?? [];
                    $time = $response->json('time') ?? time();

                    $flights = [];
                    foreach ($rawStates as $st) {
                        // Pastikan ada koordinat latitude dan longitude
                        if (! isset($st[5], $st[6]) || $st[5] === null || $st[6] === null) {
                            continue;
                        }

                        $callsign = trim((string) ($st[1] ?? ''));
                        if ($callsign === '') {
                            $callsign = strtoupper((string) ($st[0] ?? 'UNKNOWN'));
                        }

                        $airlineCode = strtoupper(substr($callsign, 0, 3));
                        $airlineInfo = self::KNOWN_AIRLINES[$airlineCode] ?? null;

                        $velocityMs = (float) ($st[9] ?? 0);
                        $altitudeM = (float) ($st[7] ?? 0);
                        $heading = round((float) ($st[10] ?? 0), 1);
                        $onGround = (bool) ($st[8] ?? false);

                        $flights[] = [
                            'icao24' => (string) ($st[0] ?? ''),
                            'callsign' => $callsign,
                            'country' => (string) ($st[2] ?? 'Unknown'),
                            'airline' => $airlineInfo ? $airlineInfo[0] : null,
                            'flag' => $airlineInfo ? $airlineInfo[1] : '✈️',
                            'lng' => round((float) $st[5], 4),
                            'lat' => round((float) $st[6], 4),
                            'altitude_m' => round($altitudeM),
                            'altitude_ft' => round($altitudeM * 3.28084),
                            'speed_kmh' => round($velocityMs * 3.6),
                            'speed_kts' => round($velocityMs * 1.94384),
                            'heading' => $heading,
                            'vertical_rate' => round((float) ($st[11] ?? 0), 1),
                            'on_ground' => $onGround,
                            'squawk' => (string) ($st[14] ?? ''),
                        ];
                    }

                    // Simpan data terakhir untuk cadangan jika nanti OpenSky rate limit
                    Cache::put('live_flights_last_good', $flights, 600);

                    return [
                        'time' => $time,
                        'total' => count($flights),
                        'flights' => $flights,
                        'source' => 'opensky_live',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('OpenSky Network fetch error: ' . $e->getMessage());
            }

            // Fallback ke data valid terakhir yang tersimpan di cache
            $backup = Cache::get('live_flights_last_good');
            if ($backup && is_array($backup)) {
                return [
                    'time' => time(),
                    'total' => count($backup),
                    'flights' => $backup,
                    'source' => 'cached_fallback',
                ];
            }

            return [
                'time' => time(),
                'total' => 0,
                'flights' => [],
                'source' => 'empty',
            ];
        });
    }

    /**
     * Mengambil daftar bandara dari database lokal untuk ditampilkan sebagai layer titik di radar.
     */
    public function getRadarAirports(): array
    {
        return Cache::remember('radar_airports_list_v1', 3600, function (): array {
            return Airport::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->with('country:id,label')
                ->get(['id', 'label', 'iata_code', 'icao_code', 'category', 'in_asean', 'latitude', 'longitude', 'country_id'])
                ->map(fn (Airport $a): array => [
                    'id' => $a->id,
                    'nama' => $a->label,
                    'iata' => $a->iata_code,
                    'icao' => $a->icao_code,
                    'kategori' => $a->category,
                    'in_asean' => (bool) $a->in_asean,
                    'lat' => (float) $a->latitude,
                    'lng' => (float) $a->longitude,
                    'negara' => $a->country?->label ?? '',
                    'url' => $a->iata_code ? ('/bandara/' . strtolower($a->iata_code)) : null,
                ])
                ->all();
        });
    }
}
