<?php

namespace App\Console\Commands;

use App\Models\PumkBriIdentityReview;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PumkBriReviewIdentities extends Command
{
    protected $signature = 'pumkbri:review-identities
        {--year= : Batasi ke tahun tertentu}
        {--status=pending : Status review: pending, resolved, atau all}
        {--detail : Tampilkan setiap baris sumber tanpa pengelompokan}';

    protected $description = 'Tampilkan kasus validasi identitas PUMK BRI untuk ditinjau manual';

    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agst', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    public function handle(): int
    {
        $status = (string) $this->option('status');
        if (! in_array($status, ['pending', 'resolved', 'all'], true)) {
            $this->error('Opsi --status harus pending, resolved, atau all.');

            return self::FAILURE;
        }

        $year = $this->option('year');
        if ($year !== null && (! ctype_digit((string) $year) || (int) $year < 1900 || (int) $year > 2100)) {
            $this->error('Opsi --year harus berupa tahun antara 1900 dan 2100.');

            return self::FAILURE;
        }

        $reviews = PumkBriIdentityReview::query()
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($year !== null, fn ($query) => $query->where('tahun', (int) $year))
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->orderBy('source_sheet')
            ->orderBy('source_row')
            ->get();

        if ($reviews->isEmpty()) {
            $this->info('Tidak ada kasus review identitas yang sesuai filter.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('detail')) {
            $this->detailRows($reviews);

            return self::SUCCESS;
        }

        $this->groupedRows($reviews);

        return self::SUCCESS;
    }

    /** @param Collection<int, PumkBriIdentityReview> $reviews */
    private function groupedRows(Collection $reviews): void
    {
        $groups = $reviews->groupBy(fn (PumkBriIdentityReview $review): string => $this->groupKey($review));
        $rows = $groups->map(function (Collection $group): array {
            /** @var PumkBriIdentityReview $first */
            $first = $group->first();
            $profile = $first->source_profile ?? [];

            return [
                (string) $first->id,
                (string) $group->count(),
                $group->map(fn (PumkBriIdentityReview $review): string => $this->period($review))
                    ->unique()->implode(', '),
                (string) ($profile['nama_mitra'] ?? '—'),
                (string) ($profile['wilayah'] ?? '—'),
                implode(', ', (array) ($first->candidate_fasilitas_ids ?? [])) ?: '—',
                (string) $first->reason,
            ];
        })->values()->all();

        $this->table(
            ['Contoh ID', 'Baris', 'Periode', 'Nama sumber', 'Wilayah', 'Kandidat fasilitas', 'Alasan'],
            $rows,
        );
        $this->newLine();
        $this->info('Menampilkan '.$groups->count().' grup dari '.$reviews->count().' baris review.');
        $this->info('Tiap grup hanya membantu peninjauan. Pastikan profil sumber sebelum memakai pumkbri:resolve-identity.');
        $this->line('Gunakan --detail untuk melihat ID dan baris sumber satu per satu.');
    }

    /** @param Collection<int, PumkBriIdentityReview> $reviews */
    private function detailRows(Collection $reviews): void
    {
        $this->table(
            ['ID', 'Periode', 'Sheet/baris', 'Nama sumber', 'Kandidat fasilitas', 'Alasan'],
            $reviews->map(function (PumkBriIdentityReview $review): array {
                $profile = $review->source_profile ?? [];

                return [
                    (string) $review->id,
                    $this->period($review),
                    $review->source_sheet.'/'.$review->source_row,
                    (string) ($profile['nama_mitra'] ?? '—'),
                    implode(', ', (array) ($review->candidate_fasilitas_ids ?? [])) ?: '—',
                    (string) $review->reason,
                ];
            })->all(),
        );
    }

    private function period(PumkBriIdentityReview $review): string
    {
        return (self::MONTHS[$review->bulan] ?? (string) $review->bulan).'/'.$review->tahun;
    }

    private function groupKey(PumkBriIdentityReview $review): string
    {
        // Due date belongs to the snapshot and may change legitimately. It is
        // deliberately excluded from this display-only group; this key is not
        // a database identity nor an automatic merge rule.
        $profile = Arr::only($review->source_profile ?? [], [
            'nama_mitra', 'wilayah', 'sektor_usaha', 'pinjaman', 'tenor_raw',
        ]);
        ksort($profile);
        $candidates = array_values((array) ($review->candidate_fasilitas_ids ?? []));
        sort($candidates);

        return hash('sha256', json_encode([$profile, $candidates], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
