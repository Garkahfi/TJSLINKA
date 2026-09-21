<?php

namespace App\Services\Pumk;

use App\Models\PumkAngsuran;
use App\Models\PumkImportBatch;
use App\Models\PumkImportRow;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Models\PumkSaldoAwal;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class PumkImportService
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
        private readonly PumkXlsxReader $reader,
        private readonly PiutangCalculator $calculator,
    ) {}

    /** @return array<string, int> */
    public function import(string $path, ?User $importer = null): array
    {
        if ($importer !== null && $importer->role !== 'super_admin') {
            throw new RuntimeException('Pengimpor harus memiliki role Super Admin.');
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File XLSX tidak ditemukan atau tidak dapat dibaca.');
        }

        $fileHash = hash_file('sha256', $path);
        if (! is_string($fileHash)) {
            throw new RuntimeException('Hash file XLSX tidak dapat dihitung.');
        }

        $batch = PumkImportBatch::where('file_hash', $fileHash)->first();

        $retryableBatch = $batch !== null && (
            $batch->status === 'failed'
            || ($batch->status === 'completed_with_warnings' && $batch->gagal > 0)
        );

        if ($batch !== null && ! $retryableBatch) {
            throw new DuplicatePumkImportException('File ini sudah pernah diimpor, tidak ada perubahan.');
        }

        if ($batch === null) {
            $batch = PumkImportBatch::create([
                'nama_file' => basename($path),
                'file_hash' => $fileHash,
                'status' => 'processing',
                'imported_by' => $importer?->id,
                'started_at' => now(),
            ]);
        } else {
            // Batch yang benar-benar gagal boleh dicoba ulang. Bersihkan data
            // anak dari percobaan parsial, tetapi pertahankan batch yang sama
            // agar constraint file_hash tetap menjadi pelindung idempotensi.
            DB::transaction(function () use ($batch, $path, $importer): void {
                $batch->rows()->delete();
                $batch->angsuran()->delete();
                $batch->saldoAwal()->delete();
                $batch->update([
                    'nama_file' => basename($path),
                    'status' => 'processing',
                    'tanggal_acuan' => null,
                    'total_baris' => 0,
                    'berhasil' => 0,
                    'gagal' => 0,
                    'imported_by' => $importer?->id,
                    'started_at' => now(),
                    'completed_at' => null,
                    'error_summary' => null,
                ]);
            });
        }

        try {
            $workbook = $this->reader->read($path);
            $tanggalAcuan = $this->excelDate(
                $workbook['tanggal_acuan_raw'],
                $workbook['date_1904'],
            );

            if ($tanggalAcuan === null) {
                throw new RuntimeException('Tanggal acuan workbook pada sel C5 tidak valid.');
            }

            $batch->update([
                'tanggal_acuan' => $tanggalAcuan->toDateString(),
                'total_baris' => count($workbook['rows']),
            ]);

            $previousActiveImported = PumkPinjaman::query()
                ->whereNull('created_by')
                ->where('is_active', true)
                ->count();

            $summary = [
                'batch_id' => $batch->id,
                'total' => count($workbook['rows']),
                'berhasil' => 0,
                'gagal' => 0,
                'peringatan' => 0,
                'baru' => 0,
                'diperbarui' => 0,
                'tanpa_perubahan' => 0,
                'dinonaktifkan' => 0,
                'perbandingan_diperiksa' => 0,
                'perbandingan_berbeda' => 0,
                'matched' => 0,
                'conflict' => 0,
                'candidate_deactivation' => 0,
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
            $seenPinjamanKeys = [];

            foreach ($workbook['rows'] as $sourceRow) {
                $worksheetRow = $sourceRow['worksheet_row'];
                $cells = $sourceRow['cells'];
                $rowHash = $this->rowHash($cells);

                try {
                    $result = DB::transaction(fn (): array => $this->importRow(
                        $cells,
                        $batch,
                        $tanggalAcuan,
                        $workbook['date_1904'],
                    ));

                    $result['warnings'] = array_values(array_unique(array_merge(
                        $result['warnings'],
                        $sourceRow['source_warnings'] ?? [],
                    )));

                    $status = $result['warnings'] === [] ? $result['change'] : 'needs_review';

                    PumkImportRow::create([
                        'batch_id' => $batch->id,
                        'source_row_number' => $worksheetRow,
                        'row_hash' => $rowHash,
                        'status' => $status,
                        'error_message' => $result['warnings'] === []
                            ? null
                            : json_encode(array_values(array_unique($result['warnings'])), JSON_UNESCAPED_SLASHES),
                    ]);

                    $summary['berhasil']++;
                    $summary[$this->summaryKey($result['change'])]++;
                    if ($result['warnings'] !== []) {
                        $summary['peringatan']++;
                    }
                    if ($result['comparison_checked']) {
                        $summary['perbandingan_diperiksa']++;
                    }
                    if ($result['comparison_differences'] !== []) {
                        $summary['perbandingan_berbeda']++;
                    }
                    foreach ($result['metrics'] as $metric => $value) {
                        $summary[$metric] += $value;
                    }

                    $seenPinjamanKeys[] = $result['pinjaman_key'];
                } catch (\Throwable $exception) {
                    PumkImportRow::create([
                        'batch_id' => $batch->id,
                        'source_row_number' => $worksheetRow,
                        'row_hash' => $rowHash,
                        'status' => 'failed',
                        'error_message' => $this->safeRowError($exception),
                    ]);

                    Log::warning('Baris impor PUMK gagal.', [
                        'batch_id' => $batch->id,
                        'worksheet_row' => $worksheetRow,
                        'exception' => $exception::class,
                    ]);

                    $summary['gagal']++;
                    if ($this->safeRowError($exception) === 'source_profile_conflict') {
                        $summary['conflict']++;
                    }
                }
            }

            $summary['candidate_deactivation'] = $this->countMissingSourceRows($seenPinjamanKeys);
            $deactivationSkipped = false;
            if ($summary['gagal'] === 0) {
                $coverageIsSafe = $previousActiveImported === 0
                    || count($seenPinjamanKeys) >= (int) ceil($previousActiveImported * 0.8);

                if ($coverageIsSafe) {
                    $summary['dinonaktifkan'] = $this->deactivateMissingSourceRows($seenPinjamanKeys);
                } else {
                    $deactivationSkipped = true;
                }
            } else {
                $deactivationSkipped = true;
            }

            $hasWarnings = $summary['peringatan'] > 0 || $summary['gagal'] > 0 || $deactivationSkipped;
            $batch->update([
                'status' => $hasWarnings ? 'completed_with_warnings' : 'completed',
                'berhasil' => $summary['berhasil'],
                'gagal' => $summary['gagal'],
                'completed_at' => now(),
                'error_summary' => $hasWarnings
                    ? json_encode([
                        'rows_needing_review' => $summary['peringatan'],
                        'failed_rows' => $summary['gagal'],
                        'missing_row_deactivation_skipped' => $deactivationSkipped,
                        'calculator_comparison_checked' => $summary['perbandingan_diperiksa'],
                        'calculator_comparison_different' => $summary['perbandingan_berbeda'],
                    ], JSON_UNESCAPED_SLASHES)
                    : null,
            ]);

            return $summary;
        } catch (\Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_summary' => json_encode(['error' => $this->safeBatchError($exception)]),
            ]);

            Log::error('Impor PUMK gagal.', [
                'batch_id' => $batch->id,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException(
                "Impor PUMK gagal. Gunakan ID batch {$batch->id} untuk pemeriksaan log.",
                0,
                $exception,
            );
        }
    }

    /**
     * @param  array<string, ?string>  $cells
     * @return array{change:'new'|'updated'|'unchanged',warnings:list<string>,pinjaman_key:string,comparison_checked:bool,comparison_differences:list<string>,metrics:array<string,int>}
     */
    private function importRow(
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
        $noUrut = $this->positiveInteger($cells['A'] ?? null);
        $namaMitra = $this->cleanText($cells['B'] ?? null);

        if ($noUrut === null) {
            throw new PumkImportRowException('missing_source_number');
        }

        if ($namaMitra === null) {
            throw new PumkImportRowException('missing_partner_name');
        }

        $this->appendMissingWarnings($warnings, $cells);

        $tanggalPencairan = $this->sourceDate($cells['AB'] ?? null, $date1904, 'tanggal_pencairan', $warnings);
        $mulaiAngsuran = $this->sourceDate($cells['AH'] ?? null, $date1904, 'mulai_angsuran', $warnings);
        $selesaiAngsuran = $this->sourceDate($cells['AI'] ?? null, $date1904, 'selesai_angsuran', $warnings);

        if ($this->cleanText($cells['AB'] ?? null) === null && (int) ($this->decimal($cells['AC'] ?? null) ?? 0) === 1900) {
            $warnings[] = 'invalid_tanggal_pencairan_1900';
        }

        $sector = $this->sector($cells['I'] ?? null);
        $region = $this->region($cells['K'] ?? null);
        $mitraKey = $this->sourceKey('mitra', $noUrut);
        $pinjamanKey = $this->sourceKey('pinjaman', $noUrut);

        $mitra = PumkMitra::firstOrNew(['source_key' => $mitraKey]);
        if ($mitra->exists && $mitra->created_by !== null) {
            throw new PumkImportRowException('manual_partner_source_conflict');
        }

        $pinjaman = PumkPinjaman::firstOrNew(['source_key' => $pinjamanKey]);
        if ($pinjaman->exists && $pinjaman->created_by !== null) {
            throw new PumkImportRowException('manual_loan_source_conflict');
        }

        $incomingMitra = [
            'nama_mitra' => $namaMitra,
            'jenis_usaha' => $this->cleanText($cells['H'] ?? null),
            'sektor_sumber' => $this->cleanText($cells['I'] ?? null),
            'alamat' => $this->cleanText($cells['J'] ?? null),
            'wilayah_sumber' => $this->cleanText($cells['K'] ?? null),
            'nama_pemilik' => $this->cleanText($cells['L'] ?? null),
            'no_ktp_encrypted' => $this->cleanIdentifier($cells['M'] ?? null),
            'no_telepon_encrypted' => $this->cleanIdentifier($cells['N'] ?? null),
            'no_rekening_encrypted' => $this->cleanIdentifier($cells['O'] ?? null),
        ];
        $incomingPrincipal = $this->money($cells['AD'] ?? null, 'AD', $warnings);

        if ($mitra->exists && $pinjaman->exists) {
            $warnings = array_merge($warnings, $this->sourceProfileWarnings(
                $mitra,
                $pinjaman,
                $incomingMitra,
                $this->cleanText($cells['C'] ?? null),
                $tanggalPencairan,
                $incomingPrincipal,
            ));
            $warnings = array_merge($warnings, $this->contractDocumentWarnings($pinjaman, $cells));
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
            'spj_awal' => $this->cleanText($cells['C'] ?? null),
            'reschedule_ke1' => $this->cleanText($cells['D'] ?? null),
            'reschedule_ke2' => $this->cleanText($cells['E'] ?? null),
            'reschedule_ke3' => $this->cleanText($cells['F'] ?? null),
            'reschedule_ke4' => $this->cleanText($cells['G'] ?? null),
            'jenis_jaminan' => $this->cleanText($cells['P'] ?? null),
            'jaminan_no_pol' => $this->cleanText($cells['Q'] ?? null),
            'jaminan_no_bpkb' => $this->cleanText($cells['R'] ?? null),
            'jaminan_merk' => $this->cleanText($cells['S'] ?? null),
            'jaminan_type' => $this->cleanText($cells['T'] ?? null),
            'jaminan_tahun_kendaraan' => $this->cleanText($cells['U'] ?? null),
            'jaminan_no_sertifikat' => $this->cleanText($cells['V'] ?? null),
            'jaminan_luas' => $this->cleanText($cells['W'] ?? null),
            'jaminan_atas_nama' => $this->cleanText($cells['X'] ?? null),
            'jaminan_alamat' => $this->cleanText($cells['Y'] ?? null),
            'berkas_spj_path' => $this->cleanText($cells['Z'] ?? null),
            'berkas_jaminan_path' => $this->cleanText($cells['AA'] ?? null),
            'tanggal_pencairan' => $tanggalPencairan?->toDateString(),
            'tahun_pencairan' => $this->sourceYear($cells['AC'] ?? null, $tanggalPencairan),
            'mulai_angsuran' => $mulaiAngsuran?->toDateString(),
            'selesai_angsuran' => $selesaiAngsuran?->toDateString(),
            'pinjaman_pokok' => $incomingPrincipal,
            'persen_bunga' => $this->rate($cells['AE'] ?? null, 'AE', $warnings),
            'pinjaman_bunga' => $this->money($cells['AF'] ?? null, 'AF', $warnings),
            'nilai_angsuran_bulanan' => $this->money($cells['AK'] ?? null, 'AK', $warnings),
            'bulan_tunggakan' => $this->integer($cells['AR'] ?? null),
            'nilai_tunggakan' => $this->money($cells['AS'] ?? null, 'AS', $warnings),
            'kolektibilitas' => $this->collectibility($cells['AT'] ?? null, $warnings),
            'sisa_pokok' => $this->money($cells['AU'] ?? null, 'AU', $warnings),
            'sisa_bunga' => $this->money($cells['AV'] ?? null, 'AV', $warnings),
            'total_sisa' => $this->money($cells['AW'] ?? null, 'AW', $warnings),
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

        $this->validateLoanTotals($cells, $warnings);
        $this->validateRemainingTotals($cells, $warnings);

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
                'pokok_masuk' => $this->money($cells['AX'] ?? null, 'AX', $warnings),
                'bunga_masuk' => $this->money($cells['AY'] ?? null, 'AY', $warnings),
                'denda' => $this->money($cells['AZ'] ?? null, 'AZ', $warnings),
            ]);
            $changed = $changed || ! $saldo->exists || $saldoChanged;
            if ($saldoIsNew || $saldoChanged) {
                $metrics['saldo_awal_update']++;
            }
            $saldo->batch_id = $batch->id;
            $saldo->save();
        }
        $this->validateOpeningTotal($cells, $warnings);

        foreach (self::MONTH_COLUMNS as $month => [$principalColumn, $interestColumn, $totalColumn]) {
            $pokokSource = $this->money($cells[$principalColumn] ?? null, $principalColumn, $warnings);
            $bungaSource = $this->money($cells[$interestColumn] ?? null, $interestColumn, $warnings);
            $sourceTotal = $this->money($cells[$totalColumn] ?? null, $totalColumn, $warnings);
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
        $comparisonDifferences = $this->compareSourceWithCalculator($pinjaman->fresh(), $cells);
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
     * @param  array<string, ?string>  $incomingMitra
     * @return list<string>
     */
    private function sourceProfileWarnings(
        PumkMitra $existingMitra,
        PumkPinjaman $pinjaman,
        array $incomingMitra,
        ?string $incomingSpj,
        ?CarbonImmutable $incomingDisbursementDate,
        ?string $incomingPrincipal,
    ): array {
        $identityDifferences = 0;
        $identityComparisons = 0;

        foreach (array_keys($incomingMitra) as $field) {
            $existing = $existingMitra->getAttribute($field);
            $incoming = $incomingMitra[$field];
            if ($this->blank($existing) || $this->blank($incoming)) {
                continue;
            }

            $identityComparisons++;
            if ($this->normalizedProfileText($existing) !== $this->normalizedProfileText($incoming)) {
                $identityDifferences++;
            }
        }

        $contractDifferences = 0;

        if (! $this->blank($pinjaman->spj_awal) && ! $this->blank($incomingSpj)
            && $this->normalizedProfileText($pinjaman->spj_awal) !== $this->normalizedProfileText($incomingSpj)) {
            $contractDifferences++;
        }

        if ($pinjaman->tanggal_pencairan !== null && $incomingDisbursementDate !== null
            && $pinjaman->tanggal_pencairan->toDateString() !== $incomingDisbursementDate->toDateString()) {
            $contractDifferences++;
        }

        if (! $this->blank($pinjaman->pinjaman_pokok) && ! $this->blank($incomingPrincipal)
            && bccomp($this->scale((string) $pinjaman->pinjaman_pokok, 2), $incomingPrincipal, 2) !== 0) {
            $contractDifferences++;
        }

        // Perubahan seluruh nilai kontrak tidak otomatis berarti Mitra berbeda.
        // Workbook V2 memang merupakan sumber koreksi terbaru. Konflik keras
        // hanya terjadi bila profil identitas juga berubah secara material.
        if ($identityDifferences >= 3 || ($identityDifferences >= 2 && $contractDifferences >= 2)) {
            throw new PumkImportRowException('source_profile_conflict');
        }

        $warnings = [];
        if ($contractDifferences >= 2) {
            $warnings[] = 'source_contract_corrected';
        }
        if ($identityComparisons > 0 && $identityDifferences > 0) {
            $warnings[] = 'source_identity_corrected';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $cells
     * @return list<string>
     */
    private function contractDocumentWarnings(PumkPinjaman $pinjaman, array $cells): array
    {
        $columns = ['spj_awal' => 'C', 'reschedule_ke1' => 'D', 'reschedule_ke2' => 'E', 'reschedule_ke3' => 'F', 'reschedule_ke4' => 'G'];
        $documentTypes = $pinjaman->dokumenKontrak()->pluck('jenis_dokumen')->all();
        $warnings = [];

        foreach (PumkPinjamanDokumen::CONTRACT_FIELDS as $type => $field) {
            if (! in_array($type, $documentTypes, true)) {
                continue;
            }
            $incoming = $this->cleanText($cells[$columns[$field]] ?? null);
            $existing = $pinjaman->getAttribute($field);
            if ($this->blank($incoming) || $this->blank($existing)) {
                continue;
            }
            if ($this->normalizedProfileText($incoming) !== $this->normalizedProfileText($existing)) {
                $warnings[] = 'contract_number_changed_with_manual_document_'.$type;
            }
        }

        return $warnings;
    }

    private function normalizedProfileText(mixed $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }

    /** @param array<string, ?string> $cells */
    private function rowHash(array $cells): string
    {
        ksort($cells);
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY wajib tersedia untuk membuat audit hash.');
        }

        return hash_hmac('sha256', (string) json_encode($cells, JSON_UNESCAPED_UNICODE), $key);
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

            if ($model->exists && $this->blank($incoming) && ! $this->blank($current)) {
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

    private function deactivateMissingSourceRows(array $seenPinjamanKeys): int
    {
        $query = PumkPinjaman::query()->whereNull('created_by')->where('is_active', true);

        if ($seenPinjamanKeys !== []) {
            $query->whereNotIn('source_key', $seenPinjamanKeys);
        }

        $count = $query->update([
            'is_active' => false,
            'status' => PumkPinjaman::STATUS_NONAKTIF,
        ]);

        PumkMitra::query()
            ->whereNull('created_by')
            ->whereDoesntHave('pinjaman', fn ($query) => $query
                ->where('status', PumkPinjaman::STATUS_AKTIF)
                ->where('is_active', true))
            ->update(['is_active' => false]);

        PumkMitra::query()
            ->whereNull('created_by')
            ->whereHas('pinjaman', fn ($query) => $query
                ->where('status', PumkPinjaman::STATUS_AKTIF)
                ->where('is_active', true))
            ->update(['is_active' => true]);

        return $count;
    }

    private function countMissingSourceRows(array $seenPinjamanKeys): int
    {
        $query = PumkPinjaman::query()->whereNull('created_by')->where('is_active', true);

        if ($seenPinjamanKeys !== []) {
            $query->whereNotIn('source_key', $seenPinjamanKeys);
        }

        return $query->count();
    }

    /** @param list<string> $warnings @param array<string, ?string> $cells */
    private function appendMissingWarnings(array &$warnings, array $cells): void
    {
        $requiredReview = [
            'C' => 'spj',
            'H' => 'jenis_usaha',
            'J' => 'alamat',
            'L' => 'nama_pemilik',
            'M' => 'ktp',
            'N' => 'telepon',
            'O' => 'rekening',
            'AB' => 'tanggal_pencairan',
            'AD' => 'pinjaman_pokok',
            'AE' => 'persen_bunga',
            'AF' => 'pinjaman_bunga',
            'AK' => 'angsuran_bulanan',
            'AT' => 'kolektibilitas',
        ];

        foreach ($requiredReview as $column => $label) {
            if ($this->blank($cells[$column] ?? null)) {
                $warnings[] = "missing_{$label}";
            }
        }
    }

    /** @param array<string, ?string> $cells @param list<string> $warnings */
    private function validateLoanTotals(array $cells, array &$warnings): void
    {
        $pokok = $this->decimal($cells['AD'] ?? null);
        $bunga = $this->decimal($cells['AF'] ?? null);
        $total = $this->decimal($cells['AG'] ?? null);

        if ($pokok !== null && $bunga !== null && $total !== null
            && bccomp(bcadd($pokok, $bunga, 2), $total, 2) !== 0) {
            $warnings[] = 'loan_total_mismatch';
        }
    }

    /** @param array<string, ?string> $cells @param list<string> $warnings */
    private function validateOpeningTotal(array $cells, array &$warnings): void
    {
        $pokok = $this->decimal($cells['AX'] ?? null) ?? '0.00';
        $bunga = $this->decimal($cells['AY'] ?? null) ?? '0.00';
        $denda = $this->decimal($cells['AZ'] ?? null) ?? '0.00';
        $total = $this->decimal($cells['BA'] ?? null);

        if ($total !== null && bccomp(bcadd(bcadd($pokok, $bunga, 2), $denda, 2), $total, 2) !== 0) {
            $warnings[] = 'opening_total_mismatch';
        }
    }

    /** @param array<string, ?string> $cells @param list<string> $warnings */
    private function validateRemainingTotals(array $cells, array &$warnings): void
    {
        $sisaPokok = $this->decimal($cells['AU'] ?? null);
        $sisaBunga = $this->decimal($cells['AV'] ?? null);
        $totalSisa = $this->decimal($cells['AW'] ?? null);

        if ($sisaPokok !== null && $sisaBunga !== null && $totalSisa !== null
            && bccomp(bcadd($sisaPokok, $sisaBunga, 2), $totalSisa, 2) !== 0) {
            $warnings[] = 'remaining_total_mismatch';
        }
    }

    /**
     * Membandingkan snapshot sumber dengan kalkulator tanpa menerapkan hasil
     * kalkulator ke pinjaman. Nilai Excel tetap menjadi baseline historis.
     *
     * @param  array<string, ?string>  $cells
     * @return list<string>
     */
    private function compareSourceWithCalculator(PumkPinjaman $pinjaman, array $cells): array
    {
        $pinjaman->unsetRelation('saldoAwal');
        $pinjaman->unsetRelation('angsuran');
        $calculated = $this->calculator->hitungUntukPinjaman($pinjaman);
        $differences = [];

        $this->appendMoneyDifference(
            $differences,
            'calculator_mismatch_total_pokok_masuk',
            $cells['AL'] ?? null,
            $calculated['total_pokok_masuk'] ?? null,
        );
        $this->appendMoneyDifference(
            $differences,
            'calculator_mismatch_total_bunga_masuk',
            $cells['AM'] ?? null,
            $calculated['total_bunga_masuk'] ?? null,
        );

        $calculatedPokokBunga = bcadd(
            (string) ($calculated['total_pokok_masuk'] ?? '0.00'),
            (string) ($calculated['total_bunga_masuk'] ?? '0.00'),
            2,
        );
        $this->appendMoneyDifference(
            $differences,
            'calculator_mismatch_total_angsuran_masuk',
            $cells['AN'] ?? null,
            $calculatedPokokBunga,
        );

        return $differences;
    }

    /** @param list<string> $differences */
    private function appendMoneyDifference(
        array &$differences,
        string $warning,
        mixed $source,
        mixed $calculated,
    ): void {
        $sourceValue = $this->decimal($source);
        $calculatedValue = $this->decimal($calculated);

        // Sel kosong di sumber berarti tidak ada basis pembanding, bukan selisih.
        if ($sourceValue === null || $calculatedValue === null) {
            return;
        }

        if (bccomp($sourceValue, $calculatedValue, 2) !== 0) {
            $differences[] = $warning;
        }
    }

    private function sourceDate(mixed $raw, bool $date1904, string $field, array &$warnings): ?CarbonImmutable
    {
        if ($this->blank($raw)) {
            return null;
        }

        $date = $this->excelDate($raw, $date1904);
        if ($date === null || $date->year <= 1900) {
            $warnings[] = "invalid_{$field}";

            return null;
        }

        return $date;
    }

    private function excelDate(mixed $raw, bool $date1904): ?CarbonImmutable
    {
        if ($this->blank($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial <= 0) {
                return null;
            }

            $base = $date1904
                ? CarbonImmutable::create(1904, 1, 1, 0, 0, 0, 'UTC')
                : CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC');

            return $base->addDays((int) floor($serial));
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value, 'UTC');
                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
                // Coba format berikutnya.
            }
        }

        return null;
    }

    private function sourceYear(mixed $raw, ?CarbonImmutable $tanggalPencairan): ?int
    {
        $year = $this->integer($raw);

        if ($year !== null && $year >= 1900 && $year <= 2200) {
            return $year;
        }

        return $tanggalPencairan?->year;
    }

    private function sector(mixed $raw): ?PumkSektorUsaha
    {
        $name = $this->cleanText($raw);
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
        $source = $this->cleanText($raw);
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

    private function collectibility(mixed $raw, array &$warnings): ?string
    {
        $value = $this->cleanText($raw);
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        $map = [
            'lancar' => 'lancar',
            'kurang_lancar' => 'kurang_lancar',
            'diragukan' => 'diragukan',
            'macet' => 'macet',
        ];

        if (! isset($map[$normalized])) {
            $warnings[] = 'unknown_collectibility';

            return $normalized;
        }

        return $map[$normalized];
    }

    private function money(mixed $raw, string $column, array &$warnings): ?string
    {
        $value = $this->decimal($raw);
        if ($value !== null && bccomp($value, '0.00', 2) < 0) {
            $warnings[] = "negative_value_{$column}";
        }

        return $value === null ? null : $this->scale($value, 2);
    }

    /** Normalisasi nilai sumber untuk audit dan perbaikan terbatas data impor. */
    public function sourceMoney(mixed $raw): ?string
    {
        $value = $this->decimal($raw);

        return $value === null ? null : $this->scale($value, 2);
    }

    private function rate(mixed $raw, string $column, array &$warnings): ?string
    {
        $value = $this->decimal($raw);
        if ($value !== null && bccomp($value, '0', 4) < 0) {
            $warnings[] = "negative_value_{$column}";
        }

        return $value === null ? null : $this->scale($value, 4);
    }

    private function decimal(mixed $raw): ?string
    {
        if ($this->blank($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        $negativeByParentheses = str_starts_with($value, '(') && str_ends_with($value, ')');

        // XLSX dapat menyimpan angka uang sebagai <v>1.5E7</v>. Membuang
        // huruf sebelum mengembangkan eksponen mengubahnya menjadi 1.57.
        $scientific = $negativeByParentheses ? substr($value, 1, -1) : $value;
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/D', $scientific, $parts)) {
            $exponent = (int) $parts[4];
            if ($exponent < -30 || $exponent > 30) {
                return null;
            }

            $digits = $parts[2].($parts[3] ?? '');
            $decimalPosition = strlen($parts[2]) + $exponent;
            $expanded = match (true) {
                $decimalPosition <= 0 => '0.'.str_repeat('0', -$decimalPosition).$digits,
                $decimalPosition >= strlen($digits) => $digits.str_repeat('0', $decimalPosition - strlen($digits)),
                default => substr($digits, 0, $decimalPosition).'.'.substr($digits, $decimalPosition),
            };

            return ($negativeByParentheses || $parts[1] === '-' ? '-' : '').$expanded;
        }

        // Notasi ilmiah yang rusak tidak boleh diam-diam dibaca sebagai angka lain.
        if (preg_match('/\d[eE](?:[+-]?\d*|$)/', $scientific)) {
            return null;
        }

        $value = preg_replace('/[^0-9,\.\-]/', '', $value) ?? '';

        if ($negativeByParentheses) {
            $value = '-'.ltrim($value, '-');
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $parts = explode(',', $value);
            $last = end($parts);
            $value = strlen((string) $last) <= 2
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        }

        return is_numeric($value) ? $value : null;
    }

    private function scale(string $value, int $scale): string
    {
        return bcadd($value, '0', $scale);
    }

    private function integer(mixed $raw): ?int
    {
        $value = $this->decimal($raw);

        return $value === null ? null : (int) round((float) $value);
    }

    private function positiveInteger(mixed $raw): ?int
    {
        $value = $this->integer($raw);

        return $value !== null && $value > 0 ? $value : null;
    }

    private function cleanText(mixed $raw): ?string
    {
        if ($this->blank($raw)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $raw));

        return $value === '' ? null : $value;
    }

    private function cleanIdentifier(mixed $raw): ?string
    {
        $value = $this->cleanText($raw);

        return $value === null ? null : trim($value);
    }

    private function sourceKey(string $entity, int $noUrut): string
    {
        return hash('sha256', "db-pumk-v1|{$entity}|{$noUrut}");
    }

    private function summaryKey(string $change): string
    {
        return match ($change) {
            'new' => 'baru',
            'updated' => 'diperbarui',
            default => 'tanpa_perubahan',
        };
    }

    private function safeRowError(\Throwable $exception): string
    {
        if ($exception instanceof PumkImportRowException) {
            return $exception->getMessage();
        }

        return 'unexpected_row_error:'.class_basename($exception);
    }

    private function safeBatchError(\Throwable $exception): string
    {
        return 'unexpected_batch_error:'.class_basename($exception);
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

    private function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
