<?php

namespace App\Services\Monitoring;

use App\Models\PumkBriIdentityReview;
use App\Models\PumkBriPenyaluranBulanan;
use App\Models\PumkBriRkaTahunan;
use App\Models\PumkBriSnapshotBulanan;
use App\Models\PumkSnapshotBulanan;
use App\Services\Pumk\PumkBriYearService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PumkDashboardService
{
    private const CENTRAL_JAVA_REGIONS = [
        'boyolali',
        'karanganyar',
        'klaten',
        'sragen',
        'sukoharjo',
        'wonogiri',
    ];

    private const EAST_JAVA_REGIONS = [
        'blitar',
        'jember',
        'kediri',
        'madiun',
        'magetan',
        'ngawi',
        'pacitan',
        'ponorogo',
        'trenggalek',
        'tulungagung',
    ];

    private const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    private const QUALITY_LABELS = [
        'L' => 'Lancar',
        'KL' => 'Kurang Lancar',
        'D' => 'Diragukan',
        'M' => 'Macet',
    ];

    public function __construct(
        private readonly PumkBriGeoReference $geoReference,
        private readonly PumkBriYearService $yearService,
    ) {}

    /** @return array<string, mixed> */
    public function bri(?int $requestedYear = null): array
    {
        $years = $this->yearService->availableYears();
        $year = $requestedYear !== null && $years->contains($requestedYear)
            ? $requestedYear
            : ($years->first() ?? now()->year);
        $latestMonth = (int) (PumkBriSnapshotBulanan::query()
            ->where('tahun', $year)
            ->max('bulan') ?? 0);
        $hasSnapshot = $latestMonth > 0;
        $rkaRecord = PumkBriRkaTahunan::query()->where('tahun', $year)->first();
        $rka = $rkaRecord?->nominal_rka === null ? null : (float) $rkaRecord->nominal_rka;
        $monthlyInputs = PumkBriPenyaluranBulanan::query()
            ->where('tahun', $year)->orderBy('bulan')->get()->keyBy('bulan');
        $hasMonthlyInput = $monthlyInputs->isNotEmpty();
        $realisasi = (float) $monthlyInputs->sum('nominal_penyaluran');
        $hasRealisasi = $hasMonthlyInput || $rka !== null;

        $latestSnapshots = DB::table('pumk_bri_snapshot_bulanan as snapshot')
            ->join('pumk_bri_mitra as mitra', 'mitra.id', '=', 'snapshot.mitra_id')
            ->leftJoin('pumk_bri_fasilitas as fasilitas', 'fasilitas.id', '=', 'snapshot.fasilitas_id')
            ->where('snapshot.tahun', $year)
            ->where('snapshot.bulan', $latestMonth);
        $latestTotals = (clone $latestSnapshots)
            ->selectRaw('SUM(snapshot.saldo_piutang) as outstanding')
            ->first();
        $yearlyPartners = PumkBriSnapshotBulanan::query()
            ->where('tahun', $year)
            ->distinct('mitra_id')
            ->count('mitra_id');
        $latestVerification = PumkBriSnapshotBulanan::query()
            ->where('tahun', $year)
            ->where('bulan', $latestMonth)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN profil_sumber_terverifikasi = 0 THEN 1 ELSE 0 END) as belum_terverifikasi')
            ->first();
        $verification = [
            'snapshot_terverifikasi' => PumkBriSnapshotBulanan::query()
                ->where('tahun', $year)
                ->where('profil_sumber_terverifikasi', true)
                ->count(),
            'snapshot_belum_terverifikasi' => PumkBriSnapshotBulanan::query()
                ->where('tahun', $year)
                ->where('profil_sumber_terverifikasi', false)
                ->count(),
            'identitas_menunggu_review' => PumkBriIdentityReview::query()
                ->where('tahun', $year)
                ->where('status', 'pending')
                ->count(),
            'snapshot_terbaru_belum_terverifikasi' => (int) ($latestVerification?->belum_terverifikasi ?? 0),
        ];
        $verification['identitas_final'] = $hasSnapshot
            && $verification['snapshot_belum_terverifikasi'] === 0
            && $verification['identitas_menunggu_review'] === 0;
        $verification['breakdown_terverifikasi'] = $hasSnapshot
            && $verification['snapshot_terbaru_belum_terverifikasi'] === 0;
        $sectors = (clone $latestSnapshots)
            ->selectRaw("COALESCE(NULLIF(snapshot.sektor_usaha_sumber, ''), NULLIF(mitra.sektor_usaha, ''), 'Belum Ditentukan') as label")
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw('SUM(snapshot.saldo_piutang) as nilai')
            ->groupByRaw("COALESCE(NULLIF(snapshot.sektor_usaha_sumber, ''), NULLIF(mitra.sektor_usaha, ''), 'Belum Ditentukan')")
            ->orderByDesc('nilai')
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'jumlah' => (int) $row->jumlah,
                'nilai' => (float) $row->nilai,
            ])
            ->values();
        $qualityRows = (clone $latestSnapshots)
            ->selectRaw('snapshot.kolektibilitas_kode as kode')
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw('SUM(snapshot.saldo_piutang) as nilai')
            ->groupBy('snapshot.kolektibilitas_kode')
            ->get()
            ->keyBy('kode');
        $quality = collect(self::QUALITY_LABELS)
            ->map(function (string $label, string $code) use ($qualityRows): array {
                $row = $qualityRows->get($code);

                return [
                    'kode' => $code,
                    'label' => $label,
                    'jumlah' => (int) ($row?->jumlah ?? 0),
                    'nilai' => (float) ($row?->nilai ?? 0),
                ];
            })
            ->values();
        $regions = (clone $latestSnapshots)
            ->selectRaw("COALESCE(NULLIF(snapshot.wilayah_sumber, ''), NULLIF(mitra.wilayah, ''), 'Belum Ditentukan') as nama")
            ->selectRaw('COUNT(DISTINCT snapshot.mitra_id) as jumlah_mitra')
            ->selectRaw('COUNT(DISTINCT snapshot.fasilitas_id) as jumlah_fasilitas')
            ->selectRaw('SUM(COALESCE(snapshot.pinjaman_sumber, fasilitas.pinjaman, mitra.pinjaman, 0)) as jumlah_penyaluran')
            ->selectRaw('SUM(snapshot.saldo_piutang) as outstanding')
            ->selectRaw("COUNT(DISTINCT CASE WHEN snapshot.kolektibilitas_kode = 'L' THEN snapshot.mitra_id END) as lancar")
            ->selectRaw("COUNT(DISTINCT CASE WHEN snapshot.kolektibilitas_kode = 'KL' THEN snapshot.mitra_id END) as kurang_lancar")
            ->selectRaw("COUNT(DISTINCT CASE WHEN snapshot.kolektibilitas_kode = 'D' THEN snapshot.mitra_id END) as diragukan")
            ->selectRaw("COUNT(DISTINCT CASE WHEN snapshot.kolektibilitas_kode = 'M' THEN snapshot.mitra_id END) as macet")
            ->groupByRaw("COALESCE(NULLIF(snapshot.wilayah_sumber, ''), NULLIF(mitra.wilayah, ''), 'Belum Ditentukan')")
            ->orderBy('nama')
            ->get()
            ->map(function (object $row): array {
                $name = (string) $row->nama;

                return [
                    'nama' => $name,
                    'geo_key' => $this->geoReference->normalize($name),
                    'geo_mapped' => $this->geoReference->isMapped($name),
                    'jumlah_mitra' => (int) $row->jumlah_mitra,
                    'jumlah_fasilitas' => (int) $row->jumlah_fasilitas,
                    'jumlah_penyaluran' => (float) $row->jumlah_penyaluran,
                    'outstanding' => (float) $row->outstanding,
                    'lancar' => (int) $row->lancar,
                    'kurang_lancar' => (int) $row->kurang_lancar,
                    'diragukan' => (int) $row->diragukan,
                    'macet' => (int) $row->macet,
                ];
            })
            ->values();
        $latestMitraTotal = (clone $latestSnapshots)
            ->selectRaw('COUNT(DISTINCT snapshot.mitra_id) as jumlah')
            ->first();
        $latestMitraCount = (int) ($latestMitraTotal?->jumlah ?? 0);
        $wilayahMitraDapatBerulang = $regions->sum('jumlah_mitra') > $latestMitraCount;
        $unmappedRegions = $regions->where('geo_mapped', false)->values();
        $monthlyDisbursement = collect(self::MONTH_NAMES)
            ->map(fn (string $label, int $month): array => [
                'bulan' => $month,
                'label' => $label,
                'nilai' => $monthlyInputs->has($month)
                    ? (float) $monthlyInputs->get($month)->nominal_penyaluran
                    : null,
            ])
            ->values();
        $outstandingTrend = PumkBriSnapshotBulanan::query()
            ->where('tahun', $year)
            ->selectRaw('bulan, SUM(saldo_piutang) as nilai')
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get()
            ->map(fn (PumkBriSnapshotBulanan $snapshot): array => [
                'bulan' => $snapshot->bulan,
                'label' => self::MONTH_NAMES[$snapshot->bulan] ?? (string) $snapshot->bulan,
                'nilai' => (float) $snapshot->nilai,
            ])
            ->values();
        $progress = $rka !== null && $rka > 0 ? round(($realisasi / $rka) * 100, 2) : null;

        return [
            'years' => $years,
            'year' => $year,
            'latest_month' => $latestMonth,
            'latest_month_label' => self::MONTH_NAMES[$latestMonth] ?? 'Belum tersedia',
            'ringkasan' => [
                'rka' => $rka,
                'realisasi' => $hasRealisasi ? $realisasi : null,
                'progres' => $progress,
                'outstanding' => (float) ($latestTotals?->outstanding ?? 0),
                'jumlah_mitra' => $yearlyPartners,
            ],
            'ketersediaan' => [
                'snapshot' => $hasSnapshot,
                'rka' => $rka !== null,
                'realisasi' => $hasRealisasi,
                'realisasi_source' => $hasRealisasi ? 'input_bulanan' : null,
                'wilayah_mitra_dapat_berulang' => $wilayahMitraDapatBerulang,
            ],
            'verifikasi' => $verification,
            'sektor' => $sectors,
            'kualitas' => $quality,
            'wilayah' => $regions,
            'peta' => [
                'available' => $hasSnapshot,
                'tahun' => $year,
                'bulan_snapshot' => $latestMonth,
                'bulan_snapshot_label' => self::MONTH_NAMES[$latestMonth] ?? null,
                'total_wilayah' => $regions->count(),
                'total_mitra_snapshot' => $latestMitraCount,
                'unmapped_wilayah' => $unmappedRegions->count(),
                'unmapped_names' => $unmappedRegions->pluck('nama')->all(),
                'geojson_url' => '/'.PumkBriGeoReference::ASSET_PATH,
                'items' => $regions,
            ],
            'realisasi_bulanan' => $monthlyDisbursement,
            'tren_outstanding' => $outstandingTrend,
            'rka_bulanan' => $monthlyDisbursement,
            'updated_at' => PumkBriSnapshotBulanan::query()
                ->where('tahun', $year)
                ->where('bulan', $latestMonth)
                ->max('updated_at'),
        ];
    }

    /** @return array<string, mixed> */
    public function live(): array
    {
        $base = DB::table('pumk_pinjaman as p')
            ->join('pumk_mitra as m', 'p.mitra_id', '=', 'm.id')
            ->where('p.is_active', true)
            ->where('m.is_active', true);
        $totals = (clone $base)
            ->selectRaw('COUNT(*) as jumlah_pinjaman')
            ->selectRaw('COUNT(DISTINCT m.id) as jumlah_mitra')
            ->selectRaw('SUM(COALESCE(p.total_pinjaman, 0)) as total_pinjaman')
            ->selectRaw('SUM(COALESCE(p.total_sisa, p.total_pinjaman, 0)) as total_sisa')
            ->selectRaw('SUM(COALESCE(p.sisa_pokok, p.pinjaman_pokok, 0)) as saldo_pokok')
            ->selectRaw('SUM(COALESCE(p.sisa_bunga, p.pinjaman_bunga, 0)) as saldo_bunga')
            ->first();
        $totalPinjaman = (float) ($totals?->total_pinjaman ?? 0);
        $totalSisa = (float) ($totals?->total_sisa ?? 0);

        $sectors = (clone $base)
            ->leftJoin('pumk_sektor_usaha as s', 'm.sektor_usaha_id', '=', 's.id')
            ->selectRaw("COALESCE(s.nama, m.sektor_sumber, 'Belum Ditentukan') as label")
            ->selectRaw('SUM(COALESCE(p.total_sisa, p.total_pinjaman, 0)) as nilai')
            ->groupByRaw("COALESCE(s.nama, m.sektor_sumber, 'Belum Ditentukan')")
            ->orderBy('label')
            ->get()
            ->map(fn (object $row): array => ['label' => $row->label, 'nilai' => (float) $row->nilai]);
        $quality = (clone $base)
            ->selectRaw("COALESCE(NULLIF(p.kolektibilitas, ''), 'Belum Dinilai') as label")
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw('SUM(COALESCE(p.total_sisa, p.total_pinjaman, 0)) as nilai')
            ->groupByRaw("COALESCE(NULLIF(p.kolektibilitas, ''), 'Belum Dinilai')")
            ->orderBy('label')
            ->get()
            ->map(fn (object $row): array => [
                'label' => $row->label,
                'jumlah' => (int) $row->jumlah,
                'nilai' => (float) $row->nilai,
            ]);
        $provinceDistribution = (clone $base)
            ->leftJoin('pumk_wilayah as w', 'm.wilayah_id', '=', 'w.id')
            ->selectRaw("COALESCE(w.nama, m.wilayah_sumber, 'Belum Ditentukan') as wilayah")
            ->selectRaw('COUNT(DISTINCT m.id) as jumlah')
            ->selectRaw('SUM(COALESCE(p.total_sisa, p.total_pinjaman, 0)) as nilai')
            ->groupByRaw("COALESCE(w.nama, m.wilayah_sumber, 'Belum Ditentukan')")
            ->get()
            ->map(fn (object $row): array => [
                'label' => $this->provinceForRegion((string) $row->wilayah),
                'jumlah' => (int) $row->jumlah,
                'nilai' => (float) $row->nilai,
            ])
            ->groupBy('label')
            ->map(fn (Collection $rows, string $province): array => [
                'label' => $province,
                'jumlah' => (int) $rows->sum('jumlah'),
                'nilai' => (float) $rows->sum('nilai'),
            ])
            ->sortByDesc('nilai')
            ->values();
        $snapshots = PumkSnapshotBulanan::query()->orderBy('tahun')->orderBy('bulan')->get();

        return [
            'total_pinjaman' => $totalPinjaman,
            'total_sisa' => $totalSisa,
            'total_realisasi' => max(0, $totalPinjaman - $totalSisa),
            'saldo_pokok' => (float) ($totals?->saldo_pokok ?? 0),
            'saldo_bunga' => (float) ($totals?->saldo_bunga ?? 0),
            'total_saldo_piutang' => $totalSisa,
            'total_binaan' => (int) ($totals?->jumlah_mitra ?? 0),
            'jumlah_pinjaman' => (int) ($totals?->jumlah_pinjaman ?? 0),
            'jumlah_mitra' => (int) ($totals?->jumlah_mitra ?? 0),
            'sektor' => $sectors->values(),
            'kolektibilitas' => $quality->values(),
            'sebaran_provinsi' => $provinceDistribution,
            'tren_sektor' => $this->trend($snapshots->where('tipe', 'sektor'), 'kategori'),
            'tren_kolektibilitas' => $this->trend($snapshots->where('tipe', 'kolektibilitas'), 'kategori'),
            'updated_at' => (clone $base)->max('p.updated_at'),
        ];
    }

    private function provinceForRegion(string $region): string
    {
        $normalized = str($region)
            ->lower()
            ->ascii()
            ->replaceMatches('/^(kab(?:upaten)?|kota)\.?\s+/i', '')
            ->trim()
            ->toString();

        if (in_array($normalized, self::CENTRAL_JAVA_REGIONS, true)) {
            return 'Jawa Tengah';
        }

        if (in_array($normalized, self::EAST_JAVA_REGIONS, true)) {
            return 'Jawa Timur';
        }

        return $region === '' ? 'Belum Ditentukan' : $region;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{labels:list<string>, datasets:list<array{label:string,data:list<?float>}>}
     */
    private function trend(Collection $rows, string $categoryField): array
    {
        $periods = $rows
            ->map(fn (object $row): string => sprintf('%04d-%02d', $row->tahun, $row->bulan))
            ->unique()->sort()->values();
        $monthNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return [
            'labels' => $periods->map(function (string $period) use ($monthNames): string {
                [$year, $month] = array_map('intval', explode('-', $period));

                return $monthNames[$month].' '.$year;
            })->all(),
            'datasets' => $rows->pluck($categoryField)->unique()->sort()->values()
                ->map(function (string $category) use ($rows, $periods, $categoryField): array {
                    $categoryRows = $rows->where($categoryField, $category)
                        ->keyBy(fn (object $row): string => sprintf('%04d-%02d', $row->tahun, $row->bulan));

                    return [
                        'label' => $category,
                        'data' => $periods->map(
                            fn (string $period): ?float => isset($categoryRows[$period])
                                ? (float) $categoryRows[$period]->nilai
                                : null,
                        )->all(),
                    ];
                })->all(),
        ];
    }
}
