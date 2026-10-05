<?php

namespace App\Services\Pumk;

use App\Models\PumkBriFasilitas;
use App\Models\PumkBriIdentityReview;
use App\Models\PumkBriMitra;
use App\Models\PumkBriSnapshotBulanan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PumkBriSnapshotPeriodWriter
{
    public function __construct(
        private readonly PumkBriIdentityMatcher $matcher,
        private readonly PumkBriSnapshotParser $parser,
    ) {}

    /** @param list<array<string, mixed>> $records */
    public function persistPeriod(
        string $sheetName,
        int $month,
        int $year,
        array $records,
        bool $replacePeriod,
    ): void {
        DB::transaction(function () use ($sheetName, $month, $year, $records, $replacePeriod): void {
            $facilities = PumkBriFasilitas::query()->with('snapshots')->get();
            $planned = [];
            $newProfiles = [];
            $reviews = [];
            $usedFacilities = [];
            $decisions = PumkBriIdentityReview::query()
                ->where('tahun', $year)->where('bulan', $month)->where('source_sheet', $sheetName)
                ->where('status', 'resolved')->get()->keyBy('source_row');

            foreach ($records as $record) {
                $resolution = $decisions->get($record['source_row']);
                if ($resolution !== null && ! hash_equals(
                    $this->parser->profileFingerprint($resolution->source_profile ?? []),
                    $this->parser->profileFingerprint($this->parser->sourceProfile($record)),
                )) {
                    $resolution = null;
                }
                $decision = $resolution === null
                    ? $this->matcher->decide($record, $facilities)
                    : [
                        'action' => $resolution->resolution_action,
                        'facility' => $facilities->firstWhere('id', $resolution->resolved_fasilitas_id),
                        'mitra' => $resolution->resolved_mitra_id === null
                            ? null
                            : PumkBriMitra::query()->find($resolution->resolved_mitra_id),
                        'candidates' => [],
                        'reason' => null,
                    ];
                if ($decision['action'] === 'match' && $decision['facility'] === null) {
                    $decision = ['action' => 'review', 'facility' => null, 'candidates' => [], 'reason' => 'Fasilitas hasil validasi tidak lagi tersedia.'];
                }
                foreach ($resolution === null ? $newProfiles : [] as $other) {
                    $comparison = $this->matcher->compareSourceProfiles($record, $other);
                    if ($comparison !== 'unrelated') {
                        $decision = [
                            'action' => 'review',
                            'facility' => null,
                            'candidates' => [],
                            'reason' => $comparison === 'match'
                                ? 'Profil duplikat dalam satu periode.'
                                : 'Profil mirip dengan baris lain pada periode ini.',
                        ];
                        break;
                    }
                }

                if ($decision['action'] === 'match' && isset($usedFacilities[$decision['facility']->id])) {
                    $decision = [
                        'action' => 'review', 'facility' => null,
                        'candidates' => [$decision['facility']->id],
                        'reason' => 'Dua baris mengarah ke fasilitas yang sama dalam satu periode.',
                    ];
                }

                if ($decision['action'] === 'review') {
                    $reviews[] = [
                        'source_row' => $record['source_row'],
                        'profile' => $this->parser->sourceProfile($record),
                        'candidates' => $decision['candidates'],
                        'reason' => $decision['reason'],
                    ];
                } else {
                    $planned[] = [
                        'record' => $record,
                        'facility' => $decision['facility'],
                        'mitra' => $decision['mitra'] ?? null,
                        'resolution' => $resolution,
                    ];
                    if ($decision['facility'] !== null) {
                        $usedFacilities[$decision['facility']->id] = true;
                    } else {
                        $newProfiles[] = $record;
                    }
                }
            }

            if ($reviews !== []) {
                throw new PumkBriIdentityNeedsReview($reviews);
            }

            if ($replacePeriod) {
                PumkBriSnapshotBulanan::query()->where('bulan', $month)->where('tahun', $year)->delete();
            }

            foreach ($planned as $item) {
                $record = $item['record'];
                $facility = $item['facility'];
                if ($facility === null) {
                    $mitra = $item['resolution']?->resolved_mitra_id !== null
                        ? PumkBriMitra::query()->findOrFail($item['resolution']->resolved_mitra_id)
                        : ($item['mitra'] ?? null);
                    $mitra ??= PumkBriMitra::query()->create([
                        'source_key' => bin2hex(random_bytes(32)),
                        'nama_mitra' => $record['nama_mitra'],
                        'alamat' => $record['alamat'],
                        'wilayah' => $record['wilayah'],
                        'sektor_usaha' => $record['sektor_usaha'],
                        // Retain the first verified profile in legacy columns for
                        // backwards-compatible screens/manual enrichment. The
                        // authoritative month-by-month values remain on snapshots.
                        'pinjaman' => $record['pinjaman'],
                        'tenor_raw' => $record['tenor_raw'],
                    ]);
                    $facility = PumkBriFasilitas::query()->create([
                        'mitra_id' => $mitra->id,
                        'reference_key' => (string) Str::uuid(),
                        'pinjaman' => $record['pinjaman'],
                        'tenor_raw' => $record['tenor_raw'],
                        'sektor_usaha' => $record['sektor_usaha'],
                    ]);
                    if ($item['resolution'] !== null) {
                        $item['resolution']->update([
                            'resolution_action' => 'match',
                            'resolved_fasilitas_id' => $facility->id,
                            'resolved_mitra_id' => $facility->mitra_id,
                        ]);
                    }
                }

                // Profil fasilitas merupakan source-owned field. Nilai terbaru,
                // termasuk null, mengikuti workbook; field manual tidak disentuh.
                $facility->forceFill([
                    'pinjaman' => $record['pinjaman'],
                    'tenor_raw' => $record['tenor_raw'],
                    'sektor_usaha' => $record['sektor_usaha'],
                ])->save();

                PumkBriSnapshotBulanan::query()->updateOrCreate(
                    ['fasilitas_id' => $facility->id, 'bulan' => $month, 'tahun' => $year],
                    [
                        'mitra_id' => $facility->mitra_id,
                        'saldo_piutang' => $record['saldo_piutang'],
                        'kolektibilitas_kode' => $record['kolektibilitas_kode'],
                        'kolektibilitas_label' => $record['kolektibilitas_label'],
                        'source_sheet' => $sheetName,
                        'source_row' => $record['source_row'],
                        'no_urut_sumber' => $record['no_urut_sumber'],
                        'nama_mitra_sumber' => $record['nama_mitra'],
                        'alamat_sumber' => $record['alamat'],
                        'wilayah_sumber' => $record['wilayah'],
                        'sektor_usaha_sumber' => $record['sektor_usaha'],
                        'pinjaman_sumber' => $record['pinjaman'],
                        'tenor_sumber' => $record['tenor_raw'],
                        'tanggal_pencairan_sumber' => null,
                        'tanggal_jatuh_tempo_sumber' => null,
                        'profil_sumber_terverifikasi' => true,
                        'source_payload' => $record['source_payload'],
                    ],
                );
            }
        });
    }
}
