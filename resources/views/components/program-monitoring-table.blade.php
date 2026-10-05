@php
    $allMonitoringPrograms = collect($programs ?? [])->values();
    $emptyMessage = $emptyMessage ?? 'Belum ada program untuk dimonitor.';
    $showRejectedInfo = $showRejectedInfo ?? false;
    $showCreator = $showCreator ?? false;
    $showSearch = $showSearch ?? false;
    $showCsrLegend = $showCsrLegend ?? false;
    $searchKeyword = trim($searchKeyword ?? '');
    $monitoringBaseUrl = $monitoringBaseUrl ?? request()->url();
    $monitoringSignature = $monitoringSignature ?? '';
    $yearOptions = [2024, 2025, 2026];
    $selectedYear = (int) request()->query('year');
    $selectedYear = in_array($selectedYear, $yearOptions, true) ? $selectedYear : null;
    $filterQuery = request()->except(['year', 'signature']);
    $clearYearUrl = $monitoringBaseUrl.($filterQuery ? '?'.http_build_query($filterQuery) : '');
    $monitoringPrograms = $selectedYear
        ? $allMonitoringPrograms
            ->filter(function (array $program) use ($selectedYear) {
                $programYear = $program['monitoring_year'] ?? null;

                if (! $programYear && ! empty($program['sort_timestamp'])) {
                    $programYear = (int) date('Y', $program['sort_timestamp']);
                }

                return (int) $programYear === $selectedYear;
            })
            ->values()
        : $allMonitoringPrograms;
    $tableColumnCount = $showRejectedInfo ? 9 : 8;

    $documentColumns = [
        ['code' => 'A', 'label' => 'Proposal Pengajuan Program', 'type' => 'proposal_pengajuan_program'],
        ['code' => 'B', 'label' => 'Kelengkapan Survei', 'type' => 'kelengkapan_survei'],
        ['code' => 'C', 'label' => 'Kajian Kelayakan Kerja Sama', 'type' => 'kajian_kelayakan'],
        ['code' => 'D', 'label' => 'Kajian Risiko', 'type' => 'kajian_mitigasi_risiko'],
        ['code' => 'E', 'label' => 'Perjanjian Kerja Sama', 'type' => 'perjanjian_kerja_sama'],
        ['code' => 'F', 'label' => 'Berita Acara Serah Terima', 'type' => 'bast'],
    ];

    $groups = [
        'sosial' => ['label' => 'PILAR PEMBANGUNAN SOSIAL', 'color' => '#2f6de9'],
        'ekonomi' => ['label' => 'PILAR PEMBANGUNAN EKONOMI', 'color' => '#f59e0b'],
        'lingkungan' => ['label' => 'PILAR PEMBANGUNAN LINGKUNGAN', 'color' => '#16a34a'],
        'hukum-tata-kelola' => ['label' => 'PILAR PEMBANGUNAN HUKUM DAN TATA KELOLA', 'color' => '#ef272d'],
    ];

    $knownPillars = array_keys($groups);
    $additionalPillars = $monitoringPrograms
        ->pluck('pillar')
        ->filter()
        ->unique()
        ->reject(fn ($pillar) => in_array($pillar, $knownPillars, true));

    foreach ($additionalPillars as $pillar) {
        $firstProgram = $monitoringPrograms->firstWhere('pillar', $pillar);
        $groups[$pillar] = [
            'label' => mb_strtoupper($firstProgram['pillar_label'] ?? 'PILAR BELUM DITENTUKAN'),
            'color' => $firstProgram['pillar_color'] ?? '#64748b',
        ];
    }
@endphp

<div
    class="program-monitoring"
    data-program-monitoring
    data-program-count="{{ $monitoringPrograms->count() }}"
    data-monitoring-signature="{{ $monitoringSignature }}"
>
    @include('components.program-monitoring.legend-and-filters')

    @include('components.program-monitoring.styles')

    @include('components.program-monitoring.table')
</div>

@include('components.program-monitoring.scripts')
