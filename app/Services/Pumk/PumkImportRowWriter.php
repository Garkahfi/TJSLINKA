<?php

namespace App\Services\Pumk;

use App\Models\PumkAngsuran;
use App\Models\PumkImportBatch;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSaldoAwal;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use App\Services\Monitoring\PumkClassificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PumkImportRowWriter
{
    /** @var array<int, array{0:string, 1:string, 2:string}> */
    private const MONTH_COLUMNS = [
        1 => ['BB', 'BC', 'BD'],
        2 => ['BE', 'BF', 'BG'],
        3 => ['BH', 'BI', 'BJ'],
        4 => ['BK', 'BL', 'BM'],
        5 => ['BN', 'BO', 'BP'],
        6 => ['BQ', 'BR', 'BS'],
        7 => ['BT', 'BU', 'BV'],
        8 => ['BW', 'BX', 'BY'],
        9 => ['BZ', 'CA', 'CB'],
        10 => ['CC', 'CD', 'CE'],
        11 => ['CF', 'CG', 'CH'],
        12 => ['CI', 'CJ', 'CK'],
    ];

    public function __construct(
        private readonly PumkImportValueParser $parser,
        private readonly PumkImportRowChecks $checks,
        private readonly PiutangCalculator $calculator,
        private readonly PumkClassificationService $classifications,
    ) {}

    /**
     * @param  array<string, ?string>  $cells
     * @return array{change:'new'|'updated'|'unchanged',warnings:list<string>,pinjaman_key:string,comparison_checked:bool,comparison_differences:list<string>,metrics:array<string,int>}
     */
    public function importRow(
        array $cells,
        PumkImportBatch $batch,
        CarbonImmutable $tanggalAcuan,
        bool $date1904,
    ): array {
        $warnings = [];
        $metrics = [
            'matched' => 0,
            'mitra_insert' => 0,
            'mitra_update' => 0,
            'pinjaman_insert' => 0,
            'pinjaman_update' => 0,
            'saldo_awal_update' => 0,
            'angsuran_import_insert' => 0,
            'angsuran_import_update' => 0,
            'angsuran_import_removal_candidate' => 0,
            'manual_angsuran_conflict' => 0,
        ];
        $noUrut = $this->parser->positiveInteger($cells['A'] ?? null);
        $namaMitra = $this->parser->cleanText($cells['B'] ?? null);

        if ($noUrut === null) {
            throw new PumkImportRowException('missing_source_number');
        }

        if ($namaMitra === null) {
            throw new PumkImportRowException('missing_partner_name');
        }

        $this->checks->appendMissingWarnings($warnings, $cells);

        $tanggalPencairan = $this->parser->sourceDate($cells['AB'] ?? null, $date1904, 'tanggal_pencairan', $warnings);
        $mulaiAngsuran = $this->parser->sourceDate($cells['AH'] ?? null, $date1904, 'mulai_angsuran', $warnings);
        $selesaiAngsuran = $this->parser->sourceDate($cells['AI'] ?? null, $date1904, 'selesai_angsuran', $warnings);

        if ($this->parser->cleanText($cells['AB'] ?? null) === null && (int) ($this->parser->decimal($cells['AC'] ?? null) ?? 0) === 1900) {
            $warnings[] = 'invalid_tanggal_pencairan_1900';
        }

        $sector = $this->sector($cells['I'] ?? null);
        $region = $this->region($cells['K'] ?? null);
        $mitraKey = $this->sourceKey('mitra', $noUrut);
        $pinjamanKey = $this->sourceKey('pinjaman', $noUrut);

        $mitra = PumkMitra::query()->where('source_key', $mitraKey)->lockForUpdate()->first()
            ?? new PumkMitra(['source_key' => $mitraKey]);
        if ($mitra->exists && $mitra->created_by !== null) {
            throw new PumkImportRowException('manual_partner_source_conflict');
        }

        $pinjaman = PumkPinjaman::query()->where('source_key', $pinjamanKey)->lockForUpdate()->first()
            ?? new PumkPinjaman(['source_key' => $pinjamanKey]);
        if ($pinjaman->exists && $pinjaman->created_by !== null) {
            throw new PumkImportRowException('manual_loan_source_conflict');
        }

        $incomingMitra = [
            'nama_mitra' => $namaMitra,
            'jenis_usaha' => $this->parser->cleanText($cells['H'] ?? null),
            'sektor_sumber' => $this->parser->cleanText($cells['I'] ?? null),
            'alamat' => $this->parser->cleanText($cells['J'] ?? null),
            'wilayah_sumber' => $this->parser->cleanText($cells['K'] ?? null),
            'nama_pemilik' => $this->parser->cleanText($cells['L'] ?? null),
            'no_ktp_encrypted' => $this->parser->cleanIdentifier($cells['M'] ?? null),
            'no_telepon_encrypted' => $this->parser->cleanIdentifier($cells['N'] ?? null),
            'no_rekening_encrypted' => $this->parser->cleanIdentifier($cells['O'] ?? null),
        ];
        $incomingPrincipal = $this->parser->money($cells['AD'] ?? null, 'AD', $warnings);

        if ($mitra->exists && $pinjaman->exists) {
            $warnings = array_merge($warnings, $this->checks->sourceProfileWarnings(
                $mitra,
                $pinjaman,
                $incomingMitra,
                $this->parser->cleanText($cells['C'] ?? null),
                $tanggalPencairan,
                $incomingPrincipal,
            ));
            $warnings = array_merge($warnings, $this->checks->contractDocumentWarnings($pinjaman, $cells));
        }

        $preserveManualPaidStatus = $pinjaman->exists
            && $pinjaman->status === PumkPinjaman::STATUS_LUNAS;
        if ($preserveManualPaidStatus) {
            $warnings[] = 'manual_paid_status_conflict';
        }

        $mitraIsNew = ! $mitra->exists;
        $mitraChanged = $this->mergeAttributes($mitra, [
            ...$incomingMitra,
            'sektor_usaha_id' => $sector?->id,
            'wilayah_id' => $region?->id,
            'source_key' => $mitraKey,
            'is_active' => $preserveManualPaidStatus ? (bool) $mitra->is_active : true,
        ]);
        $isNew = $mitraIsNew;
        $changed = $mitraIsNew || $mitraChanged;
        if ($mitraIsNew || $mitraChanged) {
            $metrics[$mitraIsNew ? 'mitra_insert' : 'mitra_update']++;
        }
        $mitra->save();
        if ($incomingMitra['sektor_sumber'] !== null) {
            $this->classifications->record($mitra, null, 'sektor', $sector?->nama ?? $incomingMitra['sektor_sumber'],
                $tanggalAcuan, 'import', $mitraKey.':sektor:'.$batch->file_hash);
        }
        if ($incomingMitra['wilayah_sumber'] !== null) {
            $this->classifications->record($mitra, null, 'wilayah', $region?->nama ?? $incomingMitra['wilayah_sumber'],
                $tanggalAcuan, 'import', $mitraKey.':wilayah:'.$batch->file_hash);
        }

        $pinjamanIsNew = ! $pinjaman->exists;
        $metrics['matched'] = ! $mitraIsNew && ! $pinjamanIsNew ? 1 : 0;
        $isNew = $isNew || $pinjamanIsNew;
        // Kolom kosong tetap memakai nilai sumber sebelumnya, bukan cache yang
        // sudah dikurangi pembayaran manual. Snapshot diperbarui sekali di akhir.
        foreach (['sisa_pokok', 'sisa_bunga', 'bulan_tunggakan', 'nilai_tunggakan', 'kolektibilitas'] as $field) {
            if (array_key_exists($field, $pinjaman->baseline_sumber ?? [])) {
                $pinjaman->setAttribute($field, $pinjaman->baseline_sumber[$field]);
            }
        }
        $pinjamanChanged = $this->mergeAttributes($pinjaman, [
            'mitra_id' => $mitra->id,
            'no_urut_sumber' => $noUrut,
            'spj_awal' => $this->parser->cleanText($cells['C'] ?? null),
            'reschedule_ke1' => $this->parser->cleanText($cells['D'] ?? null),
            'reschedule_ke2' => $this->parser->cleanText($cells['E'] ?? null),
            'reschedule_ke3' => $this->parser->cleanText($cells['F'] ?? null),
            'reschedule_ke4' => $this->parser->cleanText($cells['G'] ?? null),
            'jenis_jaminan' => $this->parser->cleanText($cells['P'] ?? null),
            'jaminan_no_pol' => $this->parser->cleanText($cells['Q'] ?? null),
            'jaminan_no_bpkb' => $this->parser->cleanText($cells['R'] ?? null),
            'jaminan_merk' => $this->parser->cleanText($cells['S'] ?? null),
            'jaminan_type' => $this->parser->cleanText($cells['T'] ?? null),
            'jaminan_tahun_kendaraan' => $this->parser->cleanText($cells['U'] ?? null),
            'jaminan_no_sertifikat' => $this->parser->cleanText($cells['V'] ?? null),
            'jaminan_luas' => $this->parser->cleanText($cells['W'] ?? null),
            'jaminan_atas_nama' => $this->parser->cleanText($cells['X'] ?? null),
            'jaminan_alamat' => $this->parser->cleanText($cells['Y'] ?? null),
            'berkas_spj_path' => $this->parser->cleanText($cells['Z'] ?? null),
            'berkas_jaminan_path' => $this->parser->cleanText($cells['AA'] ?? null),
            'tanggal_pencairan' => $tanggalPencairan?->toDateString(),
            'tahun_pencairan' => $this->parser->sourceYear($cells['AC'] ?? null, $tanggalPencairan),
            'mulai_angsuran' => $mulaiAngsuran?->toDateString(),
            'selesai_angsuran' => $selesaiAngsuran?->toDateString(),
            'pinjaman_pokok' => $incomingPrincipal,
            'persen_bunga' => $this->parser->rate($cells['AE'] ?? null, 'AE', $warnings),
            'pinjaman_bunga' => $this->parser->money($cells['AF'] ?? null, 'AF', $warnings),
            'nilai_angsuran_bulanan' => $this->parser->money($cells['AK'] ?? null, 'AK', $warnings),
            'bulan_tunggakan' => $this->parser->integer($cells['AR'] ?? null),
            'nilai_tunggakan' => $this->parser->money($cells['AS'] ?? null, 'AS', $warnings),
            'kolektibilitas' => $this->parser->collectibility($cells['AT'] ?? null, $warnings),
            'sisa_pokok' => $this->parser->money($cells['AU'] ?? null, 'AU', $warnings),
            'sisa_bunga' => $this->parser->money($cells['AV'] ?? null, 'AV', $warnings),
            'total_sisa' => $this->parser->money($cells['AW'] ?? null, 'AW', $warnings),
            'source_key' => $pinjamanKey,
            'is_active' => ! $preserveManualPaidStatus,
            'status' => $preserveManualPaidStatus ? PumkPinjaman::STATUS_LUNAS : PumkPinjaman::STATUS_AKTIF,
            // Gunakan tanggal acuan workbook sebagai batas snapshot, bukan
            // waktu proses impor. Dengan begitu angsuran manual yang dicatat
            // setelah tanggal sumber tetap ikut mengurangi baseline Excel.
            'source_updated_at' => $tanggalAcuan->endOfDay(),
        ]);
        $changed = $changed || $pinjamanChanged || $pinjamanIsNew;
        if ($pinjamanIsNew || $pinjamanChanged) {
            $metrics[$pinjamanIsNew ? 'pinjaman_insert' : 'pinjaman_update']++;
        }
        $pinjaman->save();
        if ($pinjaman->kolektibilitas !== null) {
            $this->classifications->record(null, $pinjaman, 'kolektibilitas', $pinjaman->kolektibilitas,
                $tanggalAcuan, 'import', $pinjamanKey.':kolektibilitas:'.$batch->file_hash);
        }

        $this->checks->validateLoanTotals($cells, $warnings);
        $this->checks->validateRemainingTotals($cells, $warnings);

        $saldoCutoff = CarbonImmutable::parse('2025-12-31');
        $hasDetailedHistoricalPayments = PumkAngsuran::query()
            ->where('pinjaman_id', $pinjaman->id)
            ->whereNull('batch_id')
            ->whereNotNull('created_by')
            ->whereDate('periode', '<=', $saldoCutoff)
            ->exists();

        if ($hasDetailedHistoricalPayments) {
            // Admin 2 sudah memilih histori rinci sebagai sumber perhitungan.
            // Jangan hidupkan kembali saldo awal agregat pada re-import karena
            // kedua sumber tersebut akan menghitung pembayaran yang sama dua kali.
            $deletedOpeningBalance = PumkSaldoAwal::query()
                ->where('pinjaman_id', $pinjaman->id)
                ->delete();
            $changed = $changed || $deletedOpeningBalance > 0;
            if ($deletedOpeningBalance > 0) {
                $metrics['saldo_awal_update']++;
            }
            $warnings[] = 'saldo_awal_replaced_by_manual_history';
        } else {
            $saldo = PumkSaldoAwal::firstOrNew(['pinjaman_id' => $pinjaman->id]);
            $saldoIsNew = ! $saldo->exists;
            $saldoChanged = $this->mergeAttributes($saldo, [
                'cutoff_date' => $saldoCutoff->toDateString(),
                'pokok_masuk' => $this->parser->money($cells['AX'] ?? null, 'AX', $warnings),
                'bunga_masuk' => $this->parser->money($cells['AY'] ?? null, 'AY', $warnings),
                'denda' => $this->parser->money($cells['AZ'] ?? null, 'AZ', $warnings),
            ]);
            $changed = $changed || ! $saldo->exists || $saldoChanged;
            if ($saldoIsNew || $saldoChanged) {
                $metrics['saldo_awal_update']++;
            }
            $saldo->batch_id = $batch->id;
            $saldo->save();
        }
        $this->checks->validateOpeningTotal($cells, $warnings);

        foreach (self::MONTH_COLUMNS as $month => [$principalColumn, $interestColumn, $totalColumn]) {
            $pokokSource = $this->parser->money($cells[$principalColumn] ?? null, $principalColumn, $warnings);
            $bungaSource = $this->parser->money($cells[$interestColumn] ?? null, $interestColumn, $warnings);
            $sourceTotal = $this->parser->money($cells[$totalColumn] ?? null, $totalColumn, $warnings);
            $periode = $tanggalAcuan->startOfYear()->addMonths($month - 1)->startOfDay();

            // Sel bulanan kosong/nol berarti belum ada transaksi. Jangan
            // membuat 12 baris nol per pinjaman karena itu mengubah makna
            // data kosong dan membuat riwayat angsuran seolah-olah terisi.
            // Jika impor sebelumnya pernah membuat transaksi pada periode
            // yang kini kosong, hapus hanya baris sumber tersebut. Angsuran
            // buatan Admin 2 (batch null + created_by terisi) tetap dijaga.
            if (! $this->hasMeaningfulPayment($pokokSource, $bungaSource, $sourceTotal)) {
                $existingSourcePayment = PumkAngsuran::query()
                    ->where('pinjaman_id', $pinjaman->id)
                    ->whereDate('periode', $periode->toDateString())
                    ->whereNotNull('batch_id')
                    ->whereNull('created_by')
                    ->first();

                if ($existingSourcePayment !== null) {
                    $metrics['angsuran_import_removal_candidate']++;
                    $existingSourcePayment->deleteQuietly();
                    $changed = true;
                }

                continue;
            }

            $pokok = $pokokSource ?? '0.00';
            $bunga = $bungaSource ?? '0.00';
            $denda = $sourceTotal === null
                ? '0.00'
                : bcsub(bcsub($sourceTotal, $pokok, 2), $bunga, 2);

            if (bccomp($denda, '0.00', 2) !== 0) {
                $warnings[] = "monthly_total_difference_{$month}";
            }

            $angsuran = PumkAngsuran::firstOrNew([
                'pinjaman_id' => $pinjaman->id,
                // Jangan setMonth() langsung dari tanggal 31 karena bulan yang
                // lebih pendek dapat overflow ke bulan berikutnya dan memicu
                // duplikat periode (mis. Februari dan Maret sama-sama Maret).
                // Gunakan objek tanggal lengkap agar binding query identik
                // dengan format DATETIME yang disimpan Laravel (khususnya
                // SQLite pada test). String Y-m-d tidak selalu cocok dengan
                // nilai "Y-m-d 00:00:00" dan dapat memicu insert duplikat.
                'periode' => $periode,
            ]);

            if ($angsuran->exists && $angsuran->batch_id === null && $angsuran->created_by !== null) {
                $warnings[] = "manual_angsuran_conflict_{$month}";
                $metrics['manual_angsuran_conflict']++;

                continue;
            }

            $angsuranIsNew = ! $angsuran->exists;
            $angsuranChanged = $this->mergeAttributes($angsuran, [
                'pokok' => $pokok,
                'bunga' => $bunga,
                'denda' => $denda,
            ]);
            $changed = $changed || ! $angsuran->exists || $angsuranChanged;
            if ($angsuranIsNew) {
                $metrics['angsuran_import_insert']++;
            } elseif ($angsuranChanged) {
                $metrics['angsuran_import_update']++;
            }
            $angsuran->batch_id = $batch->id;
            $angsuran->total = bcadd(bcadd((string) $angsuran->pokok, (string) $angsuran->bunga, 2), (string) $angsuran->denda, 2);
            $angsuran->saveQuietly();
        }

        if ($hasDetailedHistoricalPayments) {
            $this->calculator->bangunUlangBaselineTanpaSaldoAwal($pinjaman);
        } else {
            $this->calculator->simpanBaselineSumber($pinjaman, replace: true);
        }
        // Simpan input rumus dari satu sheet resmi di luar kolom DECIMAL(?,2).
        // Khusus AK, pecahan sub-sen harus bertahan agar AQ/AR berjalan akurat.
        $pinjaman->forceFill(['baseline_sumber' => array_merge($pinjaman->baseline_sumber ?? [], [
            'formula_sumber' => [
                'mulai_angsuran' => $mulaiAngsuran?->toDateString(),
                'angsuran_bulanan' => $this->parser->decimal($cells['AK'] ?? null),
                'total_kewajiban' => $this->parser->decimal($cells['AG'] ?? null),
                'jumlah_jatuh_tempo' => $this->parser->integer($cells['AP'] ?? null),
                'jatuh_tempo_nominal' => $this->parser->money($cells['AQ'] ?? null, 'AQ', $warnings),
                'total_pokok_bunga_masuk' => $this->parser->money($cells['AN'] ?? null, 'AN', $warnings),
                'tanggal_acuan' => $tanggalAcuan->toDateString(),
                'pokok_masuk_raw' => $this->parser->decimal($cells['AL'] ?? null),
                'bunga_masuk_raw' => $this->parser->decimal($cells['AM'] ?? null),
                'sisa_pokok_raw' => $this->parser->decimal($cells['AU'] ?? null),
                'sisa_bunga_raw' => $this->parser->decimal($cells['AV'] ?? null),
                'total_sisa_raw' => $this->parser->decimal($cells['AW'] ?? null),
            ],
        ])])->saveQuietly();
        $comparisonDifferences = $this->checks->compareSourceWithCalculator($pinjaman->fresh(), $cells);
        $warnings = array_merge($warnings, $comparisonDifferences);
        $this->calculator->sinkronkanCache($pinjaman);

        return [
            'change' => $isNew ? 'new' : ($changed ? 'updated' : 'unchanged'),
            'warnings' => array_values(array_unique($warnings)),
            'pinjaman_key' => $pinjamanKey,
            'comparison_checked' => true,
            'comparison_differences' => $comparisonDifferences,
            'metrics' => $metrics,
        ];
    }

    private function hasMeaningfulPayment(?string ...$values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && bccomp($value, '0.00', 2) !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Menggabungkan nilai sumber tanpa menghapus data lama saat sel baru kosong.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function mergeAttributes(Model $model, array $attributes): bool
    {
        $changed = false;

        foreach ($attributes as $attribute => $incoming) {
            $current = $model->getAttribute($attribute);

            if ($model->exists && $this->parser->blank($incoming) && ! $this->parser->blank($current)) {
                continue;
            }

            if ($this->comparable($current) === $this->comparable($incoming)) {
                continue;
            }

            $model->setAttribute($attribute, $incoming);
            $changed = true;
        }

        return $changed;
    }

    private function sector(mixed $raw): ?PumkSektorUsaha
    {
        $name = $this->parser->cleanText($raw);
        if ($name === null) {
            return null;
        }

        $slug = Str::slug(str_replace('/', ' ', $name));

        return PumkSektorUsaha::firstOrCreate(
            ['slug' => $slug],
            ['nama' => $name, 'is_active' => true],
        );
    }

    private function region(mixed $raw): ?PumkWilayah
    {
        $source = $this->parser->cleanText($raw);
        if ($source === null) {
            return null;
        }

        $canonical = preg_replace('/^kab(?:upaten)?\.?\s+/i', 'Kab. ', $source) ?? $source;
        $canonical = preg_replace('/^kota\s+/i', 'Kota ', $canonical) ?? $canonical;
        $canonical = Str::title($canonical);
        $canonical = str_replace('Kab. ', 'Kab. ', $canonical);
        $slug = Str::slug(str_replace('.', '', $canonical));

        return PumkWilayah::firstOrCreate(
            ['slug' => $slug],
            ['nama' => $canonical, 'is_active' => true],
        );
    }

    private function sourceKey(string $entity, int $noUrut): string
    {
        return hash('sha256', "db-pumk-v1|{$entity}|{$noUrut}");
    }

    private function comparable(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value === null ? null : (string) $value;
    }
}
