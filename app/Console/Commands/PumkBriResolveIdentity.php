<?php

namespace App\Console\Commands;

use App\Models\PumkBriFasilitas;
use App\Models\PumkBriIdentityReview;
use App\Models\PumkBriMitra;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PumkBriResolveIdentity extends Command
{
    protected $signature = 'pumkbri:resolve-identity
        {review : ID kasus yang sudah divalidasi terhadap sumber}
        {--fasilitas= : Hubungkan ke ID fasilitas yang sudah ada}
        {--mitra= : Buat fasilitas baru untuk ID mitra yang sudah ada saat import ulang}
        {--new-mitra : Nyatakan sebagai mitra baru setelah validasi manual}
        {--same-profile : Terapkan keputusan ke review pending dengan profil identitas dan kandidat sama pada tahun yang sama}';

    protected $description = 'Catat keputusan validasi identitas ambigu PUMK BRI';

    public function handle(): int
    {
        $review = PumkBriIdentityReview::query()->find($this->argument('review'));
        if ($review === null) {
            $this->error('Kasus review tidak ditemukan.');

            return self::FAILURE;
        }

        $facilityId = $this->option('fasilitas');
        $partnerId = $this->option('mitra');
        $newPartner = (bool) $this->option('new-mitra');
        if ((int) ($facilityId !== null) + (int) ($partnerId !== null) + (int) $newPartner !== 1) {
            $this->error('Pilih tepat satu keputusan: --fasilitas=ID, --mitra=ID, atau --new-mitra.');

            return self::FAILURE;
        }

        $facility = $facilityId !== null ? PumkBriFasilitas::query()->find($facilityId) : null;
        $partner = $partnerId !== null ? PumkBriMitra::query()->find($partnerId) : null;
        if (($facilityId !== null && $facility === null) || ($partnerId !== null && $partner === null)) {
            $this->error('ID mitra/fasilitas tidak ditemukan.');

            return self::FAILURE;
        }

        $reviews = $this->reviewsToResolve($review, (bool) $this->option('same-profile'));
        $resolution = [
            'status' => 'resolved',
            'resolution_action' => $facility !== null ? 'match' : ($partner !== null ? 'new_facility' : 'new'),
            'resolved_fasilitas_id' => $facility?->id,
            'resolved_mitra_id' => $facility?->mitra_id ?? $partner?->id,
        ];
        $reviews->each(fn (PumkBriIdentityReview $item) => $item->update($resolution));
        $this->info('Keputusan review tersimpan untuk '.$reviews->count().' baris. Import ulang workbook dengan --force untuk menerapkannya; profil sumber yang berubah wajib divalidasi ulang.');

        return self::SUCCESS;
    }

    /** @return Collection<int, PumkBriIdentityReview> */
    private function reviewsToResolve(PumkBriIdentityReview $review, bool $sameProfile): Collection
    {
        if (! $sameProfile) {
            return collect([$review]);
        }

        $groupKey = $this->groupKey($review);

        return PumkBriIdentityReview::query()
            ->where('tahun', $review->tahun)
            ->where('status', 'pending')
            ->orderBy('bulan')
            ->orderBy('source_row')
            ->get()
            ->filter(fn (PumkBriIdentityReview $item): bool => $this->groupKey($item) === $groupKey)
            ->values();
    }

    private function groupKey(PumkBriIdentityReview $review): string
    {
        // This matches the display-only grouping used by review-identities.
        // It is deliberately not a natural key and is only used after the
        // reviewer explicitly chooses --same-profile.
        $profile = Arr::only($review->source_profile ?? [], [
            'nama_mitra', 'wilayah', 'sektor_usaha', 'pinjaman', 'tenor_raw',
        ]);
        ksort($profile);
        $candidates = array_values((array) ($review->candidate_fasilitas_ids ?? []));
        sort($candidates);

        return hash('sha256', json_encode([$profile, $candidates], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
