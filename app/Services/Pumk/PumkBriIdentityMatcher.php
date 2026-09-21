<?php

namespace App\Services\Pumk;

use App\Models\PumkBriFasilitas;
use App\Models\PumkBriMitra;
use App\Models\PumkBriSnapshotBulanan;
use Illuminate\Support\Collection;

class PumkBriIdentityMatcher
{
    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    public function compareSourceProfiles(array $left, array $right): string
    {
        $comparison = $this->compare($left, $right, false);

        return $comparison === 'same_facility' ? 'match' : ($comparison === 'uncertain' ? 'uncertain' : 'unrelated');
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  Collection<int, PumkBriFasilitas>  $facilities
     * @return array{action:string,facility:?PumkBriFasilitas,mitra:?PumkBriMitra,candidates:list<int>,reason:?string}
     */
    public function decide(array $source, Collection $facilities): array
    {
        $matches = collect();
        $samePartner = collect();
        $uncertain = collect();

        foreach ($facilities as $facility) {
            $verdict = 'unrelated';
            foreach ($facility->snapshots as $snapshot) {
                $comparison = $this->compare($source, $this->profileFromSnapshot($snapshot), ! $snapshot->profil_sumber_terverifikasi);
                if ($comparison === 'same_facility') {
                    $verdict = 'same_facility';
                    break;
                }
                if ($comparison === 'same_partner') {
                    $verdict = 'same_partner';
                } elseif ($comparison === 'uncertain' && $verdict === 'unrelated') {
                    $verdict = 'uncertain';
                }
            }

            match ($verdict) {
                'same_facility' => $matches->push($facility),
                'same_partner' => $samePartner->push($facility->mitra),
                'uncertain' => $uncertain->push($facility->id),
                default => null,
            };
        }

        $matches = $matches->unique('id')->values();
        if ($matches->count() === 1) {
            $facility = $matches->first();

            return ['action' => 'match', 'facility' => $facility, 'mitra' => $facility->mitra,
                'candidates' => [$facility->id], 'reason' => null];
        }
        if ($matches->count() > 1) {
            return ['action' => 'review', 'facility' => null, 'mitra' => null,
                'candidates' => $matches->pluck('id')->all(),
                'reason' => 'Lebih dari satu fasilitas memiliki profil usaha yang sama.'];
        }

        $samePartner = $samePartner->filter()->unique('id')->values();
        if ($samePartner->count() === 1) {
            return ['action' => 'new_facility', 'facility' => null, 'mitra' => $samePartner->first(),
                'candidates' => [], 'reason' => null];
        }
        if ($samePartner->count() > 1 || $uncertain->isNotEmpty()) {
            return ['action' => 'review', 'facility' => null, 'mitra' => null,
                'candidates' => $uncertain->unique()->values()->all(),
                'reason' => 'Profil Mitra sebagian cocok, tetapi fasilitas atau identitasnya belum dapat dipastikan.'];
        }

        return ['action' => 'new', 'facility' => null, 'mitra' => null, 'candidates' => [], 'reason' => null];
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $profile */
    private function compare(array $source, array $profile, bool $legacy): string
    {
        $sourceName = $this->normalizedName($source['nama_mitra'] ?? null);
        $profileName = $this->normalizedName($profile['nama_mitra'] ?? null);
        if ($sourceName === null || $profileName === null) {
            return 'unrelated';
        }

        $sameName = $sourceName === $profileName || ($legacy && $this->legacyNameMatches($sourceName, $profileName));
        $markerVariant = ! $sameName && $this->legacyNameMatches($sourceName, $profileName);
        if (! $sameName && ! $markerVariant) {
            return 'unrelated';
        }

        $same = [];
        $different = [];
        foreach (['wilayah', 'sektor_usaha', 'pinjaman', 'tenor_raw'] as $field) {
            $left = $this->normalized($source[$field] ?? null);
            $right = $this->normalized($profile[$field] ?? null);
            if ($left === null || $right === null) {
                continue;
            }
            if ($left === $right) {
                $same[] = $field;
            } else {
                $different[] = $field;
            }
        }

        // Alamat dan kedua tanggal sengaja tidak menjadi penentu identitas.
        if ($different === []
            && count(array_intersect($same, ['wilayah', 'pinjaman', 'tenor_raw'])) >= 2
            && in_array('pinjaman', $same, true)) {
            return 'same_facility';
        }

        $sameRegion = in_array('wilayah', $same, true) || ! $this->bothPresent($source, $profile, 'wilayah');
        if ($sameRegion && count(array_intersect($different, ['pinjaman', 'tenor_raw', 'sektor_usaha'])) >= 2) {
            return 'same_partner';
        }

        if (in_array('wilayah', $different, true)
            && count(array_intersect($different, ['pinjaman', 'tenor_raw', 'sektor_usaha'])) >= 2) {
            return 'unrelated';
        }

        if ($markerVariant) {
            return 'uncertain';
        }

        return 'uncertain';
    }

    /** @return array<string, mixed> */
    private function profileFromSnapshot(PumkBriSnapshotBulanan $snapshot): array
    {
        return [
            'nama_mitra' => $snapshot->nama_mitra_sumber,
            'alamat' => $snapshot->alamat_sumber,
            'wilayah' => $snapshot->wilayah_sumber,
            'sektor_usaha' => $snapshot->sektor_usaha_sumber,
            'pinjaman' => $snapshot->pinjaman_sumber,
            'tenor_raw' => $snapshot->tenor_sumber,
        ];
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function bothPresent(array $left, array $right, string $field): bool
    {
        return $this->normalized($left[$field] ?? null) !== null
            && $this->normalized($right[$field] ?? null) !== null;
    }

    private function normalized(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return bcadd((string) $value, '0', 2);
        }

        return mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function normalizedName(mixed $value): ?string
    {
        $value = $this->normalized($value);

        return $value === null ? null : (preg_replace('/\s+(?=\*+$)/u', '', $value) ?? $value);
    }

    private function legacyNameMatches(string $source, string $legacy): bool
    {
        return preg_replace('/\s*\*+$/u', '', $source) === preg_replace('/\s*\*+$/u', '', $legacy);
    }
}
