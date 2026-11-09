<?php

namespace App\Support;

/**
 * Citra satelit asli untuk bandara yang tidak punya foto.
 * Memakai tile peta Esri World Imagery, jadi cukup dengan koordinat bandara.
 */
class Satellite
{
    public const URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    public const KREDIT = 'Citra satelit: Esri, Maxar, Earthstar Geographics';

    // posisi tile (bisa berkoma) untuk satu titik di peta tingkat zoom tertentu
    public static function tile(float $lat, float $lon, int $zoom): array
    {
        $lat = max(-85.0511, min(85.0511, $lat));
        $n = 2 ** $zoom;
        $x = ($lon + 180) / 360 * $n;
        $rad = deg2rad($lat);
        $y = (1 - log(tan($rad) + 1 / cos($rad)) / M_PI) / 2 * $n;

        return [$x, $y];
    }

    public static function url(int $zoom, int $x, int $y): string
    {
        return str_replace(['{z}', '{y}', '{x}'], [$zoom, $y, $x], self::URL);
    }

    /**
     * Potongan satu tile yang memuat titik bandara, untuk gambar kecil di daftar.
     * Hasilnya dipakai sebagai: <img src=url style="width:Spx;margin-left:-leftpx;margin-top:-toppx">
     */
    public static function thumb(float $lat, float $lon, int $lebar = 104, int $tinggi = 76, int $zoom = 13, int $ukuran = 160): array
    {
        [$xf, $yf] = self::tile($lat, $lon, $zoom);
        $x = (int) floor($xf);
        $y = (int) floor($yf);
        $px = ($xf - $x) * $ukuran;
        $py = ($yf - $y) * $ukuran;

        // geser potongan supaya titik bandara tetap di dalam dan tidak keluar dari tile
        $kiri = max(0, min($ukuran - $lebar, $px - $lebar / 2));
        $atas = max(0, min($ukuran - $tinggi, $py - $tinggi / 2));

        return [
            'url' => self::url($zoom, $x, $y),
            'ukuran' => $ukuran,
            'kiri' => round($kiri),
            'atas' => round($atas),
        ];
    }
}
