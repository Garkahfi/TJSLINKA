<?php

namespace App\Services\Pumk;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class PumkImportRowChecks
{
    public function __construct(
        private readonly PumkImportValueParser $parser,
        private readonly PiutangCalculator $calculator,
    ) {}

    /**
     * @param  array<string, ?string>  $incomingMitra
     * @return list<string>
     */
    public function sourceProfileWarnings(
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
            if ($this->parser->blank($existing) || $this->parser->blank($incoming)) {
                continue;
            }

            $identityComparisons++;
            if ($this->normalizedProfileText($existing) !== $this->normalizedProfileText($incoming)) {
                $identityDifferences++;
            }
        }

        $contractDifferences = 0;

        if (! $this->parser->blank($pinjaman->spj_awal) && ! $this->parser->blank($incomingSpj)
            && $this->normalizedProfileText($pinjaman->spj_awal) !== $this->normalizedProfileText($incomingSpj)) {
            $contractDifferences++;
        }

        if ($pinjaman->tanggal_pencairan !== null && $incomingDisbursementDate !== null
            && $pinjaman->tanggal_pencairan->toDateString() !== $incomingDisbursementDate->toDateString()) {
            $contractDifferences++;
        }

        if (! $this->parser->blank($pinjaman->pinjaman_pokok) && ! $this->parser->blank($incomingPrincipal)
            && bccomp($this->parser->scale((string) $pinjaman->pinjaman_pokok, 2), $incomingPrincipal, 2) !== 0) {
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
    public function contractDocumentWarnings(PumkPinjaman $pinjaman, array $cells): array
    {
        $columns = ['spj_awal' => 'C', 'reschedule_ke1' => 'D', 'reschedule_ke2' => 'E', 'reschedule_ke3' => 'F', 'reschedule_ke4' => 'G'];
        $documentTypes = $pinjaman->dokumenKontrak()->pluck('jenis_dokumen')->all();
        $warnings = [];

        foreach (PumkPinjamanDokumen::CONTRACT_FIELDS as $type => $field) {
            if (! in_array($type, $documentTypes, true)) {
                continue;
            }
            $incoming = $this->parser->cleanText($cells[$columns[$field]] ?? null);
            $existing = $pinjaman->getAttribute($field);
            if ($this->parser->blank($incoming) || $this->parser->blank($existing)) {
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

    /** @param list<string> $warnings @param array<string, ?string> $cells */
    public function appendMissingWarnings(array &$warnings, array $cells): void
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
            if ($this->parser->blank($cells[$column] ?? null)) {
                $warnings[] = "missing_{$label}";
            }
        }
    }

    /** @param array<string, ?string> $cells @param list<string> $warnings */
    public function validateLoanTotals(array $cells, array &$warnings): void
    {
        $pokok = $this->parser->decimal($cells['AD'] ?? null);
        $bunga = $this->parser->decimal($cells['AF'] ?? null);
        $total = $this->parser->decimal($cells['AG'] ?? null);

        if ($pokok !== null && $bunga !== null && $total !== null
            && bccomp(bcadd($pokok, $bunga, 2), $total, 2) !== 0) {
            $warnings[] = 'loan_total_mismatch';
        }
    }

    /** @param array<string, ?string> $cells @param list<string> $warnings */
    public function validateOpeningTotal(array $cells, array &$warnings): void
    {
        $pokok = $this->parser->decimal($cells['AX'] ?? null) ?? '0.00';
        $bunga = $this->parser->decimal($cells['AY'] ?? null) ?? '0.00';
        $denda = $this->parser->decimal($cells['AZ'] ?? null) ?? '0.00';
        $total = $this->parser->decimal($cells['BA'] ?? null);

        if ($total !== null && bccomp(bcadd(bcadd($pokok, $bunga, 2), $denda, 2), $total, 2) !== 0) {
            $warnings[] = 'opening_total_mismatch';
        }
    }

    /** @param array<string, ?string> $cells @param list<string> $warnings */
    public function validateRemainingTotals(array $cells, array &$warnings): void
    {
        $sisaPokok = $this->parser->decimal($cells['AU'] ?? null);
        $sisaBunga = $this->parser->decimal($cells['AV'] ?? null);
        $totalSisa = $this->parser->decimal($cells['AW'] ?? null);

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
    public function compareSourceWithCalculator(PumkPinjaman $pinjaman, array $cells): array
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
        $sourceValue = $this->parser->decimal($source);
        $calculatedValue = $this->parser->decimal($calculated);

        // Sel kosong di sumber berarti tidak ada basis pembanding, bukan selisih.
        if ($sourceValue === null || $calculatedValue === null) {
            return;
        }

        if (bccomp($sourceValue, $calculatedValue, 2) !== 0) {
            $differences[] = $warning;
        }
    }
}
