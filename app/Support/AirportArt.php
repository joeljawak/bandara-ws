<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Membuat ilustrasi SVG untuk satu bandara.
 *
 * Gambarnya selalu sama untuk bandara yang sama (acak dengan seed dari kode IATA),
 * tapi beberapa bagiannya mengikuti data bandara:
 * - langit mengikuti jam matahari setempat (dihitung dari bujur)
 * - panjang landasan menentukan seberapa jauh landasan terlihat
 * - jumlah penumpang menentukan ukuran terminal dan jumlah pesawat di apron
 */
class AirportArt
{
    private const W = 1200;
    private const H = 420;

    private int $state = 1;

    private array $a;
    private string $mode;
    private string $uid;
    private bool $fine;
    private string $phase;
    private float $hour;
    private array $c;

    public static function svg(array $airport, string $mode = 'hero', ?DateTimeInterface $now = null): string
    {
        return (new self($airport, $mode, $now))->build();
    }

    private function __construct(array $airport, string $mode, ?DateTimeInterface $now)
    {
        $this->a = $airport;
        $this->mode = $mode;
        $this->fine = $mode === 'hero';
        $iata = strtoupper((string) ($airport['iata'] ?? 'XXX'));
        $this->uid = 'art' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $iata)) . $mode;
        $this->state = crc32($iata . '|' . ($airport['nama'] ?? '')) ?: 1;

        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $utc = $now->setTimezone(new DateTimeZone('UTC'));
        $utcHour = (int) $utc->format('G') + ((int) $utc->format('i')) / 60;
        $lon = isset($airport['lon']) && $airport['lon'] !== '' ? (float) $airport['lon'] : null;
        $this->hour = $lon === null ? 12.0 : fmod($utcHour + $lon / 15 + 24, 24);

        $h = $this->hour;
        if ($h >= 5 && $h < 7) {
            $this->phase = 'dawn';
        } elseif ($h >= 7 && $h < 17) {
            $this->phase = 'day';
        } elseif ($h >= 17 && $h < 19) {
            $this->phase = 'dusk';
        } else {
            $this->phase = 'night';
        }
        $this->c = $this->palette($this->phase);
    }

    // angka acak 0..1 yang selalu sama untuk seed yang sama
    private function rnd(): float
    {
        $x = $this->state & 0xFFFFFFFF;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= ($x >> 17);
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x;

        return $x / 4294967296;
    }

    private function between(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->rnd();
    }

    private function palette(string $phase): array
    {
        return match ($phase) {
            'night' => [
                'sky' => ['#0a1330', '#16254d', '#34497f'],
                'far' => '#1b2a4d', 'near' => '#121d38', 'ground' => '#0d1426', 'apron' => '#1a2340',
                'runway' => '#222a3f', 'mark' => '#aeb9d3', 'cloud' => '#7c8db8', 'cloudOp' => .22,
                'build' => '#202c4d', 'glass' => '#ffcf6e', 'sea' => '#14234a',
            ],
            'dawn' => [
                'sky' => ['#34447f', '#e58f86', '#ffd9a8'],
                'far' => '#8a6f96', 'near' => '#5a4a6e', 'ground' => '#37324d', 'apron' => '#4a4160',
                'runway' => '#3b3a52', 'mark' => '#f1e4dc', 'cloud' => '#ffe3d0', 'cloudOp' => .7,
                'build' => '#4b4263', 'glass' => '#ffe0b0', 'sea' => '#8a86b4',
            ],
            'dusk' => [
                'sky' => ['#2b3875', '#d9645c', '#ffb36b'],
                'far' => '#7a5887', 'near' => '#4a3a62', 'ground' => '#2d2b47', 'apron' => '#3d3858',
                'runway' => '#343350', 'mark' => '#f1ddd2', 'cloud' => '#ffc9a6', 'cloudOp' => .65,
                'build' => '#443b63', 'glass' => '#ffd08a', 'sea' => '#7b6aa8',
            ],
            default => [
                'sky' => ['#4a9ae8', '#8cc4f5', '#e1f1ff'],
                'far' => '#a9c9d2', 'near' => '#6aa58a', 'ground' => '#5b9577', 'apron' => '#9aa5b1',
                'runway' => '#4b5563', 'mark' => '#f8fafc', 'cloud' => '#ffffff', 'cloudOp' => .92,
                'build' => '#e8eef5', 'glass' => '#7fb2e0', 'sea' => '#4f9bd1',
            ],
        };
    }

    private function build(): string
    {
        $W = self::W;
        $H = self::H;
        $u = $this->uid;
        $c = $this->c;

        // nilai acak dibaca dengan urutan tetap supaya gambar stabil
        $horizon = round($this->between(236, 262));
        $vx = round($W / 2 + $this->between(-110, 110));
        $terrain = $this->rnd() > 0.62 ? 'sea' : 'hills';
        $side = $this->rnd() > 0.5 ? 1 : -1;
        $planeX = $vx + $this->between(-150, 230);
        $planeY = $this->between(70, 150);
        $planeScale = $this->between(0.9, 1.25);

        $runway = (float) ($this->a['runway'] ?? 0);
        if ($runway <= 0) {
            $runway = 2400;
        }
        $pax = (float) ($this->a['penumpang'] ?? 0);
        $size = $pax > 0 ? max(0.25, min(1, (log10($pax) - 4) / 3.5)) : 0.3;
        $planes = $pax > 0 ? max(1, min(5, (int) round(log10($pax) - 3))) : 2;

        $o = [];
        $o[] = $this->defs($horizon);
        $o[] = '<rect width="' . $W . '" height="' . $H . '" fill="url(#' . $u . 'sky)"/>';
        $o[] = $this->celestial($horizon);
        if ($this->fine) {
            $o[] = $this->bigCode($horizon);
        }
        $o[] = $this->clouds($horizon);
        $o[] = $this->plane($planeX, $planeY, $planeScale);
        $o[] = $terrain === 'sea' ? $this->sea($horizon) : $this->hills($horizon);
        $o[] = '<rect y="' . $horizon . '" width="' . $W . '" height="' . ($H - $horizon) . '" fill="url(#' . $u . 'gnd)"/>';
        $o[] = $this->runwayShape($horizon, $vx, $runway);
        $o[] = $this->terminal($horizon, $vx, $side, $size, $planes);
        $o[] = '<rect width="' . $W . '" height="' . $H . '" fill="url(#' . $u . 'vig)"/>';

        $view = $this->mode === 'thumb'
            ? ($vx - 260) . ' 30 520 390'
            : '0 0 ' . $W . ' ' . $H;

        $label = 'Ilustrasi bandara ' . htmlspecialchars((string) ($this->a['nama'] ?? ''), ENT_QUOTES);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $view . '" preserveAspectRatio="xMidYMid slice" role="img" aria-label="' . $label . '">'
            . implode('', array_filter($o)) . '</svg>';
    }

    private function defs(float $horizon): string
    {
        $u = $this->uid;
        $c = $this->c;

        return '<defs>'
            . '<linearGradient id="' . $u . 'sky" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0" stop-color="' . $c['sky'][0] . '"/><stop offset=".55" stop-color="' . $c['sky'][1] . '"/><stop offset="1" stop-color="' . $c['sky'][2] . '"/></linearGradient>'
            . '<linearGradient id="' . $u . 'gnd" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0" stop-color="' . $c['near'] . '"/><stop offset=".25" stop-color="' . $c['ground'] . '"/><stop offset="1" stop-color="' . $c['ground'] . '"/></linearGradient>'
            . '<radialGradient id="' . $u . 'glow"><stop offset="0" stop-color="#fff6d6" stop-opacity=".95"/><stop offset=".35" stop-color="#ffe2a0" stop-opacity=".35"/><stop offset="1" stop-color="#ffe2a0" stop-opacity="0"/></radialGradient>'
            . '<linearGradient id="' . $u . 'trail" x1="1" y1="0" x2="0" y2="0"><stop offset="0" stop-color="#fff" stop-opacity=".75"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>'
            . '<radialGradient id="' . $u . 'vig" cx=".5" cy=".5" r=".75"><stop offset=".6" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="' . ($this->phase === 'night' ? '.35' : '.16') . '"/></radialGradient>'
            . '</defs>';
    }

    private function celestial(float $horizon): string
    {
        $o = '';
        if ($this->phase === 'night') {
            $stars = $this->fine ? 70 : 28;
            for ($i = 0; $i < $stars; $i++) {
                $x = $this->between(10, 1190);
                $y = $this->between(8, $horizon - 30);
                $r = $this->between(0.6, 1.7);
                $o .= '<circle cx="' . round($x) . '" cy="' . round($y) . '" r="' . round($r, 1) . '" fill="#fff" opacity="' . round($this->between(.35, .95), 2) . '"/>';
            }
            // bulan, posisi mengikuti jam malam (18:00 sampai 05:00)
            $p = (($this->hour - 18 + 24) % 24) / 11;
            $p = max(0, min(1, $p));
            $x = 110 + $p * 980;
            $y = $horizon - 40 - sin(M_PI * $p) * ($horizon - 120);
            $o .= '<circle cx="' . round($x) . '" cy="' . round($y) . '" r="64" fill="url(#' . $this->uid . 'glow)" opacity=".5"/>'
                . '<circle cx="' . round($x) . '" cy="' . round($y) . '" r="19" fill="#f6f2e2"/>'
                . '<circle cx="' . round($x + 6) . '" cy="' . round($y - 4) . '" r="19" fill="' . $this->c['sky'][0] . '" opacity=".0"/>'
                . '<circle cx="' . round($x - 5) . '" cy="' . round($y + 4) . '" r="3.2" fill="#d9d3bd"/>'
                . '<circle cx="' . round($x + 6) . '" cy="' . round($y - 6) . '" r="2.2" fill="#d9d3bd"/>';

            return $o;
        }

        $p = (($this->hour - 6) / 12);
        $p = max(0.02, min(0.98, $p));
        $x = 90 + $p * 1020;
        $y = $horizon - 8 - sin(M_PI * $p) * ($horizon - 110);
        $core = $this->phase === 'day' ? '#fffbe6' : '#ffd9a0';

        return '<circle cx="' . round($x) . '" cy="' . round($y) . '" r="130" fill="url(#' . $this->uid . 'glow)"/>'
            . '<circle cx="' . round($x) . '" cy="' . round($y) . '" r="26" fill="' . $core . '"/>';
    }

    private function bigCode(float $horizon): string
    {
        $code = htmlspecialchars(strtoupper((string) ($this->a['iata'] ?? '')), ENT_QUOTES);
        $op = $this->phase === 'night' ? '.07' : '.16';

        return '<text x="56" y="' . round($horizon - 36) . '" font-family="Plus Jakarta Sans, Arial, sans-serif" font-size="190" font-weight="800" letter-spacing="-6" fill="#fff" opacity="' . $op . '">' . $code . '</text>';
    }

    private function cloud(float $x, float $y, float $s): string
    {
        $c = $this->c;
        $fill = $c['cloud'];
        $op = $c['cloudOp'];

        return '<g opacity="' . $op . '" fill="' . $fill . '">'
            . '<ellipse cx="' . round($x) . '" cy="' . round($y) . '" rx="' . round(58 * $s) . '" ry="' . round(15 * $s) . '"/>'
            . '<ellipse cx="' . round($x - 26 * $s) . '" cy="' . round($y - 9 * $s) . '" rx="' . round(30 * $s) . '" ry="' . round(15 * $s) . '"/>'
            . '<ellipse cx="' . round($x + 12 * $s) . '" cy="' . round($y - 14 * $s) . '" rx="' . round(34 * $s) . '" ry="' . round(18 * $s) . '"/>'
            . '</g>';
    }

    private function clouds(float $horizon): string
    {
        $n = $this->fine ? (int) round($this->between(4, 7)) : 3;
        $o = '';
        for ($i = 0; $i < $n; $i++) {
            $o .= $this->cloud($this->between(40, 1160), $this->between(40, $horizon - 60), $this->between(0.7, 1.6));
        }

        return $o;
    }

    // siluet bukit dengan kurva halus
    private function ridge(float $horizon, float $amp, float $base, string $fill, float $opacity): string
    {
        $pts = [];
        for ($x = -60; $x <= self::W + 60; $x += 130) {
            $pts[] = [$x, $horizon - $base - $this->between(0, $amp)];
        }
        $d = 'M-60 ' . ($horizon + 2) . ' L' . $pts[0][0] . ' ' . round($pts[0][1]);
        for ($i = 1; $i < count($pts); $i++) {
            $mx = ($pts[$i - 1][0] + $pts[$i][0]) / 2;
            $my = ($pts[$i - 1][1] + $pts[$i][1]) / 2;
            $d .= ' Q' . $pts[$i - 1][0] . ' ' . round($pts[$i - 1][1]) . ' ' . round($mx) . ' ' . round($my);
        }
        $d .= ' L' . (self::W + 60) . ' ' . ($horizon + 2) . ' Z';

        return '<path d="' . $d . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
    }

    private function hills(float $horizon): string
    {
        return $this->ridge($horizon, 70, 6, $this->c['far'], 1)
            . $this->ridge($horizon, 38, 0, $this->c['near'], 1);
    }

    private function sea(float $horizon): string
    {
        $o = '<rect y="' . ($horizon - 2) . '" width="' . self::W . '" height="40" fill="' . $this->c['sea'] . '"/>'
            . $this->ridge($horizon - 2, 26, 0, $this->c['far'], .9);
        for ($i = 0; $i < 9; $i++) {
            $x = $this->between(30, 1170);
            $y = $horizon + $this->between(2, 30);
            $o .= '<rect x="' . round($x) . '" y="' . round($y) . '" width="' . round($this->between(30, 90)) . '" height="1.6" fill="#fff" opacity=".28"/>';
        }

        return $o;
    }

    private function plane(float $x, float $y, float $s): string
    {
        $night = $this->phase === 'night';
        $body = $night ? '#cfd8ec' : '#ffffff';
        $line = $night ? '#8d9cc2' : '#b9c6d8';
        $wing = $night ? '#aab6d4' : '#e4ebf4';

        $trail = '<rect x="' . round($x - 330 * $s) . '" y="' . round($y + 26 * $s) . '" width="' . round(300 * $s) . '" height="' . round(4 * $s, 1) . '" rx="2" fill="url(#' . $this->uid . 'trail)" transform="rotate(14 ' . round($x) . ' ' . round($y) . ')" opacity=".9"/>';

        $g = '<g transform="translate(' . round($x) . ' ' . round($y) . ') rotate(-14) scale(' . round($s, 2) . ')">'
            . '<path d="M-52 -8 L-68 -36 L-50 -36 L-26 -10 Z" fill="' . $wing . '" stroke="' . $line . '" stroke-width=".8"/>'
            . '<path d="M-62 0 C-62 -9 -30 -13 18 -13 C50 -13 66 -5 74 0 C66 5 42 9 10 9 L-50 9 C-59 9 -62 5 -62 0 Z" fill="' . $body . '" stroke="' . $line . '" stroke-width=".8"/>'
            . '<path d="M-6 6 L-34 30 L-18 30 L22 7 Z" fill="' . $wing . '" stroke="' . $line . '" stroke-width=".8"/>'
            . '<ellipse cx="2" cy="19" rx="11" ry="4.5" fill="' . $line . '"/>'
            . '<path d="M58 -6 C64 -5 69 -2 71 0 L52 0 Z" fill="' . ($night ? '#5d6d96' : '#7e93b4') . '"/>';
        if ($this->fine) {
            for ($i = 0; $i < 12; $i++) {
                $g .= '<rect x="' . (-40 + $i * 6.4) . '" y="-6" width="2.4" height="2.4" rx="1" fill="' . ($night ? '#ffd98a' : '#8fa4c4') . '"/>';
            }
        }
        if ($night) {
            $g .= '<circle cx="-32" cy="29" r="2.2" fill="#ff5a5a"/><circle cx="-64" cy="-36" r="2" fill="#ffffff"/>';
        }
        $g .= '</g>';

        return $trail . $g;
    }

    // batas kiri/kanan landasan pada ketinggian y (perspektif)
    private function edge(float $y, float $horizon, float $vx, int $dir): float
    {
        $half = 200 * ($y - $horizon) / (self::H - $horizon);

        return $vx + $dir * $half;
    }

    private function runwayShape(float $horizon, float $vx, float $runway): string
    {
        $H = self::H;
        $c = $this->c;
        $t = max(0, min(1, ($runway - 700) / 3500));
        $far = 150 - 138 * $t;
        $yTop = $horizon + $far;
        $night = $this->phase === 'night';

        $o = '<polygon points="' . round($this->edge($yTop, $horizon, $vx, -1)) . ',' . round($yTop) . ' ' . round($this->edge($yTop, $horizon, $vx, 1)) . ',' . round($yTop) . ' ' . round($this->edge($H, $horizon, $vx, 1)) . ',' . $H . ' ' . round($this->edge($H, $horizon, $vx, -1)) . ',' . $H . '" fill="' . $c['runway'] . '"/>';

        $zmax = ($H - $horizon) / $far;
        $perspY = fn (float $z) => $horizon + ($H - $horizon) / $z;

        // garis tengah putus-putus
        for ($z = 1.0; $z < $zmax - 0.4; $z += 0.42) {
            $y1 = $perspY($z);
            $y2 = $perspY($z + 0.2);
            $w1 = 3.4 * ($y1 - $horizon) / ($H - $horizon);
            $w2 = 3.4 * ($y2 - $horizon) / ($H - $horizon);
            $o .= '<polygon points="' . round($vx - $w2, 1) . ',' . round($y2, 1) . ' ' . round($vx + $w2, 1) . ',' . round($y2, 1) . ' ' . round($vx + $w1, 1) . ',' . round($y1, 1) . ' ' . round($vx - $w1, 1) . ',' . round($y1, 1) . '" fill="' . $c['mark'] . '" opacity=".9"/>';
        }

        // garis tepi dan lampu landasan
        foreach ([-1, 1] as $dir) {
            $o .= '<line x1="' . round($this->edge($yTop, $horizon, $vx, $dir)) . '" y1="' . round($yTop) . '" x2="' . round($this->edge($H, $horizon, $vx, $dir)) . '" y2="' . $H . '" stroke="' . $c['mark'] . '" stroke-width="1.4" opacity=".5"/>';
            if ($this->fine || $night) {
                for ($z = 1.0; $z < $zmax - 0.2; $z += 0.5) {
                    $y = $perspY($z);
                    $r = max(0.6, 3.2 * ($y - $horizon) / ($H - $horizon));
                    $x = $this->edge($y, $horizon, $vx, $dir);
                    if ($night) {
                        $o .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="' . round($r * 2.6, 1) . '" fill="#ffd27a" opacity=".16"/>';
                    }
                    $o .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="' . round($r, 1) . '" fill="' . ($night ? '#ffe3a1' : '#ffffff') . '" opacity="' . ($night ? '1' : '.7') . '"/>';
                }
            }
        }

        // ujung landasan
        $yEnd = $yTop + 4;
        for ($i = -3; $i <= 3; $i++) {
            if ($i === 0) {
                continue;
            }
            $x = $vx + $i * (170 * ($yEnd - $horizon) / ($H - $horizon)) / 3.6;
            $o .= '<rect x="' . round($x - 1.2, 1) . '" y="' . round($yEnd, 1) . '" width="2.4" height="' . round(max(2, 18 * ($yEnd - $horizon) / ($H - $horizon)), 1) . '" fill="' . $c['mark'] . '" opacity=".8"/>';
        }

        return $o;
    }

    private function terminal(float $horizon, float $vx, int $side, float $size, int $planes): string
    {
        $c = $this->c;
        $night = $this->phase === 'night';
        $baseY = $horizon + 46;

        // apron di sisi terminal
        $runEdge = $this->edge($baseY, $horizon, $vx, $side === 1 ? 1 : -1);
        $gap = 34;
        $width = 200 + $size * 400;
        if ($side === 1) {
            $x0 = $runEdge + $gap;
            $x1 = min(self::W - 20, $x0 + $width);
        } else {
            $x1 = $runEdge - $gap;
            $x0 = max(20, $x1 - $width);
        }

        $o = '<polygon points="' . round($x0 - 20) . ',' . round($baseY) . ' ' . round($x1 + 20) . ',' . round($baseY) . ' ' . round($x1 + 60 * $side) . ',' . round($baseY + 70) . ' ' . round($x0 + 60 * $side) . ',' . round($baseY + 70) . '" fill="' . $c['apron'] . '" opacity=".95"/>';

        // pesawat parkir
        $span = $x1 - $x0;
        for ($i = 0; $i < $planes; $i++) {
            $px = $x0 + ($i + 0.5) * $span / $planes;
            $o .= $this->parkedPlane($px, $baseY + 26, 0.55 + $this->between(0, .08));
        }

        // gedung terminal
        $x = $x0;
        $blocks = 0;
        while ($x < $x1 - 24 && $blocks < 9) {
            $bw = min($x1 - $x, $this->between(46, 110));
            $bh = $this->between(26, 44) + $size * 38;
            $o .= '<rect x="' . round($x) . '" y="' . round($baseY - $bh) . '" width="' . round($bw) . '" height="' . round($bh) . '" fill="' . $c['build'] . '"/>'
                . '<rect x="' . round($x) . '" y="' . round($baseY - $bh) . '" width="' . round($bw) . '" height="3" fill="' . $c['mark'] . '" opacity=".55"/>';
            if ($this->fine) {
                $o .= $this->windows($x, $baseY - $bh + 8, $bw, $bh - 12);
            } else {
                $o .= '<rect x="' . round($x + 4) . '" y="' . round($baseY - $bh * .55) . '" width="' . round($bw - 8) . '" height="' . round($bh * .3) . '" fill="' . $c['glass'] . '" opacity="' . ($night ? '.85' : '.6') . '"/>';
            }
            $x += $bw + 2;
            $blocks++;
        }

        // menara kontrol
        $tx = $side === 1 ? $x0 - 4 : $x1 + 4;
        $th = 78 + $size * 40;
        $o .= '<rect x="' . round($tx - 4) . '" y="' . round($baseY - $th) . '" width="8" height="' . round($th) . '" fill="' . $c['build'] . '"/>'
            . '<polygon points="' . round($tx - 15) . ',' . round($baseY - $th) . ' ' . round($tx + 15) . ',' . round($baseY - $th) . ' ' . round($tx + 11) . ',' . round($baseY - $th - 15) . ' ' . round($tx - 11) . ',' . round($baseY - $th - 15) . '" fill="' . $c['build'] . '"/>'
            . '<rect x="' . round($tx - 12) . '" y="' . round($baseY - $th - 12) . '" width="24" height="7" fill="' . $c['glass'] . '" opacity="' . ($night ? '.95' : '.8') . '"/>'
            . '<line x1="' . round($tx) . '" y1="' . round($baseY - $th - 15) . '" x2="' . round($tx) . '" y2="' . round($baseY - $th - 30) . '" stroke="' . $c['build'] . '" stroke-width="1.6"/>'
            . '<circle cx="' . round($tx) . '" cy="' . round($baseY - $th - 31) . '" r="2.6" fill="#ff5a4f"/>';

        return $o;
    }

    private function windows(float $x, float $y, float $w, float $h): string
    {
        $o = '';
        $night = $this->phase === 'night';
        for ($yy = $y; $yy < $y + $h - 4; $yy += 8) {
            for ($xx = $x + 5; $xx < $x + $w - 8; $xx += 9) {
                $lit = !$night || $this->rnd() > 0.28;
                $o .= '<rect x="' . round($xx, 1) . '" y="' . round($yy, 1) . '" width="5" height="4" fill="' . ($lit ? $this->c['glass'] : '#000') . '" opacity="' . ($lit ? ($night ? '.9' : '.55') : '.18') . '"/>';
            }
        }

        return $o;
    }

    private function parkedPlane(float $x, float $y, float $s): string
    {
        $night = $this->phase === 'night';
        $body = $night ? '#c3cde4' : '#ffffff';
        $line = $night ? '#7f8fb8' : '#aab8cc';

        return '<g transform="translate(' . round($x) . ' ' . round($y) . ') scale(' . round($s, 2) . ')">'
            . '<path d="M-44 -26 L-50 -34 L-40 -34 L-26 -18 Z" fill="' . $body . '" stroke="' . $line . '" stroke-width=".8"/>'
            . '<path d="M-52 0 C-52 -8 -24 -12 14 -12 C42 -12 54 -5 60 0 C54 5 34 9 8 9 L-42 9 C-50 9 -52 5 -52 0 Z" fill="' . $body . '" stroke="' . $line . '" stroke-width=".8"/>'
            . '<path d="M-4 5 L-26 20 L-12 20 L18 6 Z" fill="' . ($night ? '#aab6d4' : '#e1e8f2') . '" stroke="' . $line . '" stroke-width=".8"/>'
            . '</g>';
    }
}
