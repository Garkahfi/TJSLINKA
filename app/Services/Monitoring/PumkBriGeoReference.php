<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Str;
use RuntimeException;

final class PumkBriGeoReference
{
    public const ASSET_PATH = 'geo/indonesia-kabupaten-kota.geojson';

    /** @var array<string, true>|null */
    private static ?array $knownKeys = null;

    public function normalize(?string $region): string
    {
        $value = Str::of((string) $region)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        if ($value === '') {
            return 'belum ditentukan';
        }

        if (preg_match('/^kota\s+(.+)$/', $value, $matches) === 1) {
            return 'kota '.$matches[1];
        }

        $value = preg_replace('/^(kab|kabupaten)\s+/', '', $value) ?? $value;
        $value = preg_replace('/\s+(kab|kabupaten)$/', '', $value) ?? $value;

        return trim($value);
    }

    public function isMapped(?string $region): bool
    {
        return isset($this->knownKeys()[$this->normalize($region)]);
    }

    /** @return array<string, true> */
    private function knownKeys(): array
    {
        if (self::$knownKeys !== null) {
            return self::$knownKeys;
        }

        $path = public_path(self::ASSET_PATH);
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Referensi GeoJSON PUMK BRI tidak tersedia.');
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $features = is_array($decoded['features'] ?? null) ? $decoded['features'] : [];
        $keys = [];

        foreach ($features as $feature) {
            $name = $feature['properties']['WADMKK'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $keys[$this->normalize($name)] = true;
        }

        return self::$knownKeys = $keys;
    }
}
