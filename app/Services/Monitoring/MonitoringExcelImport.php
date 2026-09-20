<?php

namespace App\Services\Monitoring;

use App\Models\BidangPrioritas;
use App\Models\Pillar;
use App\Models\PumkBriKualitas;
use App\Models\PumkBriRingkasan;
use App\Models\PumkBriSaldoBulanan;
use App\Models\PumkBriSektor;
use App\Models\TpbDashboard;
use App\Models\WilayahOperasional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MonitoringExcelImport
{
    public const HEADERS = [
        'Pilar' => ['nama_pilar', 'rencana_anggaran', 'realisasi_anggaran'],
        'Wilayah' => ['nama_wilayah', 'realisasi_anggaran'],
        'BidangPrioritas' => ['nama_bidang', 'rencana_anggaran', 'realisasi_anggaran', 'penyerapan_persen'],
        'TPB' => ['nomor_tpb', 'nama_tpb', 'rencana_anggaran', 'realisasi_anggaran'],
        'PUMK_BRI_Ringkasan' => ['tahun', 'rka_tahun_ini', 'realisasi_sd_desember', 'progres_kolaborasi_persen'],
        'PUMK_BRI_Sektor' => ['nama_sektor', 'nilai_portofolio'],
        'PUMK_BRI_Kualitas' => ['kategori', 'nilai'],
        'PUMK_BRI_SaldoBulanan' => ['sektor_atau_kategori', 'tipe', 'bulan', 'tahun', 'nilai'],
    ];

    public function __construct(private readonly MonitoringXlsxReader $reader) {}

    /**
     * @return array<string, array{status:string, berhasil:int, gagal:int, pesan:string, errors:list<string>}>
     */
    public function import(string $path): array
    {
        $workbook = $this->reader->read($path, array_keys(self::HEADERS));
        $summary = [];

        foreach (self::HEADERS as $sheetName => $requiredHeaders) {
            if (! isset($workbook[$sheetName])) {
                $summary[$sheetName] = $this->result('skipped', 0, 0, 'Dilewati — sheet tidak ditemukan; data lama tetap dipakai.');

                continue;
            }

            $missing = array_values(array_diff($requiredHeaders, $workbook[$sheetName]['headers']));
            if ($missing !== []) {
                $summary[$sheetName] = $this->result(
                    'failed',
                    0,
                    0,
                    'Gagal — header wajib tidak lengkap.',
                    ['Header tidak ditemukan: '.implode(', ', $missing).'.'],
                );

                continue;
            }

            $summary[$sheetName] = $this->importRows($sheetName, $workbook[$sheetName]['rows']);
        }

        return $summary;
    }

    /**
     * @param  list<array{row:int, values:array<string, ?string>}>  $rows
     * @return array{status:string, berhasil:int, gagal:int, pesan:string, errors:list<string>}
     */
    private function importRows(string $sheetName, array $rows): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        foreach ($rows as $record) {
            try {
                $payload = $this->validatedPayload($sheetName, $record['values']);
                DB::transaction(fn () => $this->persist($sheetName, $payload));
                $success++;
            } catch (Throwable $exception) {
                $failed++;
                $knownProblem = $exception instanceof InvalidArgumentException
                    || $exception::class === RuntimeException::class;
                $errors[] = "Baris {$record['row']}: ".($knownProblem
                    ? $exception->getMessage()
                    : 'Terjadi kesalahan saat menyimpan data. Periksa log aplikasi.');

                if (! $knownProblem) {
                    Log::error("Import monitoring sheet {$sheetName} gagal pada satu baris.", [
                        'row' => $record['row'],
                        'exception' => $exception,
                    ]);
                }
            }
        }

        $status = $failed === 0 ? 'success' : ($success > 0 ? 'partial' : 'failed');
        $message = match ($status) {
            'success' => "Berhasil — {$success} baris disimpan.",
            'partial' => "Sebagian berhasil — {$success} baris disimpan, {$failed} baris gagal.",
            default => "Gagal — {$failed} baris tidak dapat disimpan.",
        };

        return $this->result($status, $success, $failed, $message, $errors);
    }

    /** @param array<string, ?string> $row */
    private function validatedPayload(string $sheetName, array $row): array
    {
        return match ($sheetName) {
            'Pilar' => [
                'nama_pilar' => $this->requiredText($row['nama_pilar'] ?? null, 'nama_pilar'),
                'rencana_anggaran' => $this->decimal($row['rencana_anggaran'] ?? null, 'rencana_anggaran'),
                'realisasi_anggaran' => $this->decimal($row['realisasi_anggaran'] ?? null, 'realisasi_anggaran'),
            ],
            'Wilayah' => [
                'nama_wilayah' => $this->requiredText($row['nama_wilayah'] ?? null, 'nama_wilayah'),
                'realisasi_anggaran' => $this->decimal($row['realisasi_anggaran'] ?? null, 'realisasi_anggaran'),
            ],
            'BidangPrioritas' => [
                'nama_bidang' => $this->requiredText($row['nama_bidang'] ?? null, 'nama_bidang'),
                'rencana_anggaran' => $this->decimal($row['rencana_anggaran'] ?? null, 'rencana_anggaran', true),
                'realisasi_anggaran' => $this->decimal($row['realisasi_anggaran'] ?? null, 'realisasi_anggaran', true),
                'penyerapan_persen' => $this->percentage($row['penyerapan_persen'] ?? null, 'penyerapan_persen', true),
            ],
            'TPB' => [
                'nomor_tpb' => $this->tpbNumber($row['nomor_tpb'] ?? null),
                'nama_tpb' => $this->nullableText($row['nama_tpb'] ?? null),
                'rencana_anggaran' => $this->decimal($row['rencana_anggaran'] ?? null, 'rencana_anggaran', true),
                'realisasi_anggaran' => $this->decimal($row['realisasi_anggaran'] ?? null, 'realisasi_anggaran', true),
            ],
            'PUMK_BRI_Ringkasan' => [
                'tahun' => $this->integerInRange($row['tahun'] ?? null, 'tahun', 1900, 2100),
                'rka_tahun_ini' => $this->decimal($row['rka_tahun_ini'] ?? null, 'rka_tahun_ini'),
                'realisasi_sd_desember' => $this->decimal($row['realisasi_sd_desember'] ?? null, 'realisasi_sd_desember'),
                'progres_kolaborasi_persen' => $this->percentage($row['progres_kolaborasi_persen'] ?? null, 'progres_kolaborasi_persen'),
            ],
            'PUMK_BRI_Sektor' => [
                'nama_sektor' => $this->requiredText($row['nama_sektor'] ?? null, 'nama_sektor'),
                'nilai_portofolio' => $this->decimal($row['nilai_portofolio'] ?? null, 'nilai_portofolio'),
            ],
            'PUMK_BRI_Kualitas' => [
                'kategori' => $this->qualityLabel($row['kategori'] ?? null),
                'nilai' => $this->decimal($row['nilai'] ?? null, 'nilai'),
            ],
            'PUMK_BRI_SaldoBulanan' => [
                'sektor_atau_kategori' => $this->requiredText($row['sektor_atau_kategori'] ?? null, 'sektor_atau_kategori'),
                'tipe' => $this->type($row['tipe'] ?? null),
                'bulan' => $this->integerInRange($row['bulan'] ?? null, 'bulan', 1, 12),
                'tahun' => $this->integerInRange($row['tahun'] ?? null, 'tahun', 1900, 2100),
                'nilai' => $this->decimal($row['nilai'] ?? null, 'nilai'),
            ],
            default => throw new InvalidArgumentException("Sheet {$sheetName} tidak didukung."),
        };
    }

    private function persist(string $sheetName, array $payload): void
    {
        switch ($sheetName) {
            case 'Pilar':
                $this->persistPillar($payload);
                break;
            case 'Wilayah':
                $this->persistWilayah($payload);
                break;
            case 'BidangPrioritas':
                BidangPrioritas::query()->updateOrCreate(['nama_bidang' => $payload['nama_bidang']], $payload);
                break;
            case 'TPB':
                TpbDashboard::query()->updateOrCreate(['nomor_tpb' => $payload['nomor_tpb']], $payload);
                break;
            case 'PUMK_BRI_Ringkasan':
                $this->persistBriSummary($payload);
                break;
            case 'PUMK_BRI_Sektor':
                PumkBriSektor::query()->updateOrCreate(['nama_sektor' => $payload['nama_sektor']], $payload);
                break;
            case 'PUMK_BRI_Kualitas':
                PumkBriKualitas::query()->updateOrCreate(['kategori' => $payload['kategori']], $payload);
                break;
            case 'PUMK_BRI_SaldoBulanan':
                PumkBriSaldoBulanan::query()->updateOrCreate(
                    [
                        'sektor_atau_kategori' => $payload['sektor_atau_kategori'],
                        'tipe' => $payload['tipe'],
                        'bulan' => $payload['bulan'],
                        'tahun' => $payload['tahun'],
                    ],
                    ['nilai' => $payload['nilai']],
                );
                break;
            default:
                throw new InvalidArgumentException("Sheet {$sheetName} tidak didukung.");
        }
    }

    private function persistPillar(array $payload): void
    {
        $pillar = Pillar::query()->where('slug', Str::slug($payload['nama_pilar']))->first();
        if ($pillar === null) {
            throw new RuntimeException("Pilar '{$payload['nama_pilar']}' tidak ditemukan.");
        }
        $pillar->update([
            'dashboard_rencana_anggaran' => $payload['rencana_anggaran'],
            'dashboard_realisasi_anggaran' => $payload['realisasi_anggaran'],
        ]);
    }

    private function persistWilayah(array $payload): void
    {
        $wilayah = WilayahOperasional::query()->where('nama', $payload['nama_wilayah'])->first();
        if ($wilayah === null) {
            throw new RuntimeException("Wilayah '{$payload['nama_wilayah']}' tidak ditemukan; koordinat harus disiapkan lebih dahulu.");
        }
        $wilayah->update(['realisasi_anggaran' => $payload['realisasi_anggaran']]);
    }

    private function persistBriSummary(array $payload): void
    {
        PumkBriRingkasan::query()->updateOrCreate(
            ['tahun' => $payload['tahun']],
            $payload,
        );
    }

    private function requiredText(mixed $value, string $field): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException("{$field} wajib diisi.");
        }
        if (mb_strlen($value) > 255) {
            throw new InvalidArgumentException("{$field} melebihi 255 karakter.");
        }

        return $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function decimal(mixed $value, string $field, bool $nullable = false): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            if ($nullable) {
                return null;
            }
            throw new InvalidArgumentException("{$field} wajib berupa angka.");
        }

        $normalized = str_ireplace(['rp', ' ', "\u{00A0}"], '', $value);
        $normalized = rtrim($normalized, '%');
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = strrpos($normalized, ',') > strrpos($normalized, '.')
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $parts = explode(',', $normalized);
            $normalized = count($parts) === 2 && strlen($parts[1]) <= 2
                ? $parts[0].'.'.$parts[1]
                : implode('', $parts);
        } elseif (str_contains($normalized, '.')) {
            $parts = explode('.', $normalized);
            $allThousands = count($parts) > 2
                || (count($parts) === 2 && strlen($parts[1]) === 3);
            $normalized = $allThousands ? implode('', $parts) : $normalized;
        }

        if (! preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("{$field} harus berupa angka nol atau positif.");
        }

        [$integer] = explode('.', $normalized, 2);
        $integer = ltrim($integer, '0') ?: '0';
        if (strlen($integer) > 16) {
            throw new InvalidArgumentException("{$field} melebihi batas angka yang dapat disimpan.");
        }

        return bcadd($normalized, '0', 2);
    }

    private function percentage(mixed $value, string $field, bool $nullable = false): ?string
    {
        $percentage = $this->decimal($value, $field, $nullable);
        if ($percentage !== null && (float) $percentage > 100) {
            throw new InvalidArgumentException("{$field} harus berada pada rentang 0 sampai 100.");
        }

        return $percentage;
    }

    private function integerInRange(mixed $value, string $field, int $min, int $max): int
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+$/', $value) || (int) $value < $min || (int) $value > $max) {
            throw new InvalidArgumentException("{$field} harus berupa angka {$min}–{$max}.");
        }

        return (int) $value;
    }

    private function tpbNumber(mixed $value): string
    {
        $value = $this->requiredText($value, 'nomor_tpb');
        if (preg_match('/(\d{1,2})/', $value, $matches)) {
            return 'TPB '.(int) $matches[1];
        }

        throw new InvalidArgumentException('nomor_tpb harus berisi nomor, contoh TPB 1.');
    }

    private function type(mixed $value): string
    {
        $value = Str::of((string) $value)->trim()->lower()->toString();
        if (! in_array($value, ['sektor', 'kolektibilitas'], true)) {
            throw new InvalidArgumentException("tipe harus 'sektor' atau 'kolektibilitas'.");
        }

        return $value;
    }

    private function qualityLabel(mixed $value): string
    {
        $key = Str::of((string) $value)->trim()->lower()->replace(['-', ' '], '_')->toString();

        return match ($key) {
            'lancar' => 'Lancar',
            'kurang_lancar' => 'Kurang Lancar',
            'diragukan' => 'Diragukan',
            'macet' => 'Macet',
            default => throw new InvalidArgumentException('kategori kualitas harus Lancar, Kurang Lancar, Diragukan, atau Macet.'),
        };
    }

    /**
     * @param  list<string>  $errors
     * @return array{status:string, berhasil:int, gagal:int, pesan:string, errors:list<string>}
     */
    private function result(string $status, int $success, int $failed, string $message, array $errors = []): array
    {
        return [
            'status' => $status,
            'berhasil' => $success,
            'gagal' => $failed,
            'pesan' => $message,
            'errors' => $errors,
        ];
    }
}
