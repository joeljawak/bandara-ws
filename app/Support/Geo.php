<?php

namespace App\Support;

class Geo
{
    /**
     * Jarak garis lurus antara dua koordinat dalam kilometer.
     */
    public static function jarakKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    public static function parseKoordinat(?string $nilai): ?array
    {
        if ($nilai === null || trim($nilai) === '') {
            return null;
        }

        $bagian = array_map('trim', explode(',', $nilai));

        if (count($bagian) !== 2 || ! is_numeric($bagian[0]) || ! is_numeric($bagian[1])) {
            return null;
        }

        $lat = (float) $bagian[0];
        $lon = (float) $bagian[1];

        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        return ['lat' => $lat, 'lon' => $lon];
    }
}
