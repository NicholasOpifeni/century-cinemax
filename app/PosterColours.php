<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Works out a film's accent and panel colours from its poster, the same way as the mockup's
 * pickColour(): shrink to 40x60, bucket the hues weighted by saturation, ignore very dark,
 * very light and grey pixels, and take the strongest bucket.
 */
final class PosterColours
{
    // Used when a poster is mostly grey, as in the mockup.
    public const GREY_ACCENT = '#C6CDD8';
    public const GREY_TONE = '#0C0D11';

    /** @return array{accent: string, tone: string} */
    public static function fromImage(string $bytes): array
    {
        if (getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('The poster isn’t an image GD can read.');
        }
        $source = imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('The poster isn’t an image GD can read.');
        }

        $small = imagecreatetruecolor(40, 60);
        imagecopyresampled($small, $source, 0, 0, 0, 0, 40, 60, imagesx($source), imagesy($source));

        $bins = array_fill(0, 24, ['w' => 0.0, 'h' => 0.0, 's' => 0.0]);
        for ($x = 0; $x < 40; $x++) {
            for ($y = 0; $y < 60; $y++) {
                $rgb = imagecolorat($small, $x, $y);
                [$h, $s, $l] = self::rgbToHsl(($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255);
                if ($l < .12 || $l > .9 || $s < .22) {
                    continue;
                }
                $weight = $s * (1 - abs($l - .5) * 1.4);
                $bin = ((int) floor($h * 24)) % 24;
                $bins[$bin]['w'] += $weight;
                $bins[$bin]['h'] += $h * $weight;
                $bins[$bin]['s'] += $s * $weight;
            }
        }

        $best = array_reduce($bins, fn (array $a, array $b): array => $b['w'] > $a['w'] ? $b : $a, $bins[0]);
        if ($best['w'] < 2) {
            return ['accent' => self::GREY_ACCENT, 'tone' => self::GREY_TONE];
        }

        $hue = $best['h'] / $best['w'];
        $saturation = $best['s'] / $best['w'];
        return [
            'accent' => self::hslToHex($hue, min(.85, max(.5, $saturation)), .62),
            'tone' => self::hslToHex($hue, .5, .07),
        ];
    }

    /** @return array{float, float, float} hue, saturation and lightness, each 0 to 1 */
    private static function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > .5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        return [$h / 6, $s, $l];
    }

    /** Rounds to whole degrees and percents first, like the mockup's hsl() string. */
    private static function hslToHex(float $h, float $s, float $l): string
    {
        $h = round($h * 360) / 360;
        $s = round($s * 100) / 100;
        $l = round($l * 100) / 100;

        $q = $l < .5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $channel = static function (float $t) use ($p, $q): int {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);
            $v = match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
            return (int) round($v * 255);
        };

        return sprintf('#%02X%02X%02X', $channel($h + 1 / 3), $channel($h), $channel($h - 1 / 3));
    }
}
