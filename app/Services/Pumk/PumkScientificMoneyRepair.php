<?php

namespace App\Services\Pumk;

use App\Models\PumkPinjaman;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Koreksi terbatas hasil parser lama; bukan re-import atau penggantian snapshot. */
final class PumkScientificMoneyRepair
{
    public function __construct(
        private readonly PumkImportService $importer,
        private readonly PiutangCalculator $calculator,
    ) {}

    /**
     * @param  array<int, array{worksheet_row:int,cells:array<string, ?string>}>  $rows
     * @return array{rows:int,principal:int,opening_principal:int,conflicts:list<int>}
     */
    public function run(array $rows, bool $apply = false): array
    {
        return DB::transaction(function () use ($rows, $apply): array {
            $sourceNumbers = [];
            foreach ($rows as $row) {
                $rawNumber = $this->importer->sourceMoney($row['cells']['A'] ?? null);
                $number = $rawNumber === null ? 0 : (int) $rawNumber;
                if ($number < 1 || $rawNumber === null || bccomp($rawNumber, (string) $number, 2) !== 0
                    || isset($sourceNumbers[$number])) {
                    throw new RuntimeException('Nomor urut sumber kosong, tidak valid, atau ganda pada baris worksheet '.$row['worksheet_row'].'.');
                }
                $sourceNumbers[$number] = true;
            }

            $loans = PumkPinjaman::query()->with('saldoAwal')
                ->whereIn('source_key', array_map(
                    fn (int $number): string => hash('sha256', "db-pumk-v1|pinjaman|{$number}"),
                    array_keys($sourceNumbers),
                ))
                ->when($apply, fn ($query) => $query->lockForUpdate())
                ->get()->keyBy('no_urut_sumber');

            $changes = [];
            $conflicts = [];
            $principalCount = 0;
            $openingCount = 0;
            foreach ($rows as $row) {
                $cells = $row['cells'];
                $number = (int) $cells['A'];
                $loan = $loans->get($number);
                if ($loan === null || $loan->created_by !== null || $loan->source_updated_at === null) {
                    $conflicts[] = $number;
                    continue;
                }

                $principal = $this->correction($cells['AD'] ?? null, $loan->pinjaman_pokok);
                $opening = $this->correction($cells['AX'] ?? null, $loan->saldoAwal?->pokok_masuk);
                if ($principal === false || $opening === false
                    || ($opening !== null && ($loan->saldoAwal === null || ! isset($loan->baseline_sumber['total_pokok_masuk'])))) {
                    $conflicts[] = $number;
                    continue;
                }

                if ($principal !== null || $opening !== null) {
                    $changes[] = [$loan, $principal, $opening];
                    $principalCount += (int) ($principal !== null);
                    $openingCount += (int) ($opening !== null);
                }
            }

            if ($apply && $conflicts !== []) {
                throw new RuntimeException('Koreksi dibatalkan: ada '.count($conflicts).' konflik profil/nilai sumber. Tidak ada perubahan disimpan.');
            }

            if ($apply) {
                foreach ($changes as [$loan, $principal, $opening]) {
                    if ($principal !== null) {
                        $loan->pinjaman_pokok = $principal;
                        $loan->save(); // Total pinjaman dihitung oleh model.
                    }
                    if ($opening !== null) {
                        $old = (string) $loan->saldoAwal->pokok_masuk;
                        $loan->saldoAwal->pokok_masuk = $opening;
                        $loan->saldoAwal->save();

                        $baseline = $loan->baseline_sumber;
                        $baseline['total_pokok_masuk'] = bcadd(
                            (string) $baseline['total_pokok_masuk'],
                            bcsub($opening, $old, 2),
                            2,
                        );
                        $loan->forceFill(['baseline_sumber' => $baseline])->saveQuietly();
                    }
                    // Snapshot sisa dan angsuran manual tetap sumber kebenaran.
                    $this->calculator->sinkronkanCache($loan->fresh());
                }
            }

            return [
                'rows' => count($rows),
                'principal' => $principalCount,
                'opening_principal' => $openingCount,
                'conflicts' => $conflicts,
            ];
        });
    }

    /** null=tidak perlu koreksi, false=konflik, string=nilai sumber benar. */
    private function correction(?string $raw, ?string $actual): string|false|null
    {
        $expected = $this->importer->sourceMoney($raw);
        if ($expected === null) {
            return null; // Sumber kosong tidak menghapus isian manual.
        }
        if ($actual !== null && bccomp($expected, $actual, 2) === 0) {
            return null;
        }
        if (! preg_match('/^[+-]?\d+(?:\.\d+)?[eE][+-]?\d+$/D', trim((string) $raw))) {
            return false;
        }

        // Hanya perbaiki nilai yang persis dihasilkan parser lama.
        $legacy = preg_replace('/[^0-9,\.\-]/', '', trim((string) $raw));
        if ($actual === null || ! is_numeric($legacy) || bccomp(bcadd($legacy, '0', 2), $actual, 2) !== 0) {
            return false;
        }

        return $expected;
    }
}
