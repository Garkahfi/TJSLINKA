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
    <div class="monitoring-document-legend panel grid gap-3 p-5 text-sm md:grid-cols-3">
        @foreach (array_chunk($documentColumns, 2) as $columnPair)
            <p>
                @foreach ($columnPair as $column)
                    {{ $column['code'] }} : {{ $column['label'] }}@if(! $loop->last)<br>@endif
                @endforeach
            </p>
        @endforeach
    </div>

    <div class="monitoring-kind-legend panel my-5 flex flex-wrap justify-center gap-8 p-4">
        <span class="flex items-center gap-2">
            <i class="h-6 w-6 rounded" style="background-color: #dc2626"></i>
            Program TJSL
        </span>
        @if($showCsrLegend)
            <span class="flex items-center gap-2">
                <i class="h-6 w-6 rounded" style="background-color: #7c3aed"></i>
                CSR
            </span>
        @endif
    </div>

    <div class="monitoring-year-legend panel mb-5 flex justify-center gap-3 p-3" aria-label="Filter tahun monitoring">
        @foreach ($yearOptions as $year)
            @php
                $isSelectedYear = $selectedYear === $year;
                $yearUrl = $isSelectedYear
                    ? $clearYearUrl
                    : $monitoringBaseUrl.'?'.http_build_query([...$filterQuery, 'year' => $year]);
            @endphp
            <a
                href="{{ $yearUrl }}"
                data-monitoring-year="{{ $year }}"
                aria-pressed="{{ $isSelectedYear ? 'true' : 'false' }}"
                title="{{ $isSelectedYear ? 'Tampilkan semua tahun' : 'Tampilkan tahun '.$year }}"
                @class([
                    'rounded-md border-2 border-slate-500 px-4 font-bold',
                    'bg-slate-200' => $isSelectedYear,
                ])
            >
                {{ $year }}
            </a>
        @endforeach
    </div>

    @if($showSearch)
        <form method="GET" action="{{ $monitoringBaseUrl }}" class="monitoring-search panel mb-5 p-4">
            @if($selectedYear)
                <input type="hidden" name="year" value="{{ $selectedYear }}">
            @endif
            <label class="sr-only" for="program-monitoring-search">Cari program berdasarkan kata kunci</label>
            <div class="monitoring-search-field">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path stroke-linecap="round" d="m16.2 16.2 4 4"></path>
                </svg>
                <input
                    id="program-monitoring-search"
                    name="keyword"
                    type="search"
                    inputmode="search"
                    autocomplete="off"
                    maxlength="100"
                    value="{{ $searchKeyword }}"
                    placeholder="Cari nama program atau nomor registrasi..."
                >
                @if($searchKeyword !== '')
                    <a
                        href="{{ $monitoringBaseUrl.($selectedYear ? '?year='.$selectedYear : '') }}"
                        class="monitoring-search-clear"
                    >
                        Hapus
                    </a>
                @endif
                <button type="submit" class="monitoring-search-submit">Cari</button>
            </div>
            <p class="monitoring-search-hint">
                Pencarian hanya dijalankan saat tombol Cari ditekan agar tidak membebani database pada setiap ketikan.
            </p>
        </form>
    @endif

    <style>
        .program-monitoring .program-kind-badge{display:inline-flex;align-items:center;margin-right:6px;border-radius:999px;padding:3px 9px;color:#fff;font-size:11px;font-weight:700;line-height:1.2;vertical-align:middle}
        .program-monitoring .program-kind-badge.internal{background:#dc2626}
        .program-monitoring .program-kind-badge.csr{background:#7c3aed}
        .program-monitoring .program-kind-badge.cooperation{background:#6b7280}
        .program-monitoring .program-kind-badge.rejected{background:#6b7280}
        .program-monitoring .document-check{display:inline-grid;width:24px;height:24px;place-items:center;border-radius:5px;color:#fff;font-weight:800;line-height:1}
        .program-monitoring .document-check.complete{background:#16a34a}
        .program-monitoring .document-check.incomplete{background:#9ca3af}
        .program-monitoring .document-check.in-progress{background:#f59e0b}
        .program-monitoring .document-check.in-progress svg{width:16px;height:16px}
        .program-monitoring .document-na{display:inline-block;color:#cbd5e1;font-size:25px;font-weight:500;line-height:1}
        .program-monitoring .monitoring-search-field{display:flex;min-height:46px;align-items:center;gap:10px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;padding:0 14px;transition:border-color .15s,box-shadow .15s}
        .program-monitoring .monitoring-search-field:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.13)}
        .program-monitoring .monitoring-search-field svg{width:21px;height:21px;flex:0 0 auto;color:#64748b}
        .program-monitoring .monitoring-search-field input{min-width:0;flex:1;border:0;background:transparent;padding:10px 0;color:#0f172a;outline:0}
        .program-monitoring .monitoring-search-field input::placeholder{color:#94a3b8}
        .program-monitoring .monitoring-search-clear{border-radius:7px;background:#e2e8f0;padding:7px 11px;color:#334155;font-size:12px;font-weight:700;text-decoration:none}
        .program-monitoring .monitoring-search-submit{border:0;border-radius:7px;background:#2563eb;padding:8px 16px;color:#fff;font-size:12px;font-weight:700;cursor:pointer}
        .program-monitoring .monitoring-search-submit:hover{background:#1d4ed8}
        .program-monitoring .monitoring-search-hint{margin-top:7px;color:#64748b;font-size:11px}
        .program-monitoring .monitoring-meta{margin-top:4px;color:#64748b;font-size:11px}
        .program-monitoring .rejection-open{display:inline-grid;width:34px;height:34px;place-items:center;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#334155;cursor:pointer}
        .program-monitoring .rejection-open:hover{border-color:#dc2626;background:#fef2f2;color:#b91c1c}
        .program-monitoring .rejection-open svg{width:20px;height:20px}
        .rejection-dialog{width:min(460px,calc(100vw - 32px));max-width:460px;padding:0;border:0;border-radius:14px;background:transparent;box-shadow:0 24px 80px rgba(15,23,42,.3)}
        .rejection-dialog::backdrop{background:rgba(15,23,42,.58)}
        .rejection-dialog-card{overflow:hidden;border:1px solid #fecaca;border-radius:14px;background:#fff}
        .rejection-dialog-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;background:#fef2f2;color:#991b1b}
        .rejection-dialog-close{display:grid;width:32px;height:32px;place-items:center;border:0;border-radius:999px;background:#fff;color:#991b1b;font-size:22px;cursor:pointer}
        .rejection-dialog-body{display:grid;gap:14px;padding:20px;color:#334155}
        .rejection-dialog-label{display:block;margin-bottom:4px;color:#64748b;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
        .rejection-dialog-reason{border-left:3px solid #dc2626;border-radius:4px;background:#fef2f2;padding:10px 12px;color:#7f1d1d;line-height:1.55}
    </style>

    @if($monitoringPrograms->isEmpty())
        <p class="rounded-xl border bg-white p-8 text-center text-slate-500">
            @if($searchKeyword !== '')
                Tidak ada program yang cocok dengan kata kunci "{{ $searchKeyword }}".
            @else
                {{ $selectedYear ? 'Tidak ada program pada tahun '.$selectedYear.'.' : $emptyMessage }}
            @endif
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="w-full min-w-[1020px] text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="p-4">No</th>
                        <th class="p-4 text-left">Nama Program</th>
                        <th class="p-4" colspan="6">Kelengkapan Dokumen</th>
                        @if($showRejectedInfo)
                            <th class="p-4">Alasan</th>
                        @endif
                    </tr>
                    <tr>
                        <th></th>
                        <th></th>
                        @foreach ($documentColumns as $column)
                            <th class="p-2">{{ $column['code'] }}</th>
                        @endforeach
                        @if($showRejectedInfo)
                            <th aria-label="Informasi penolakan"></th>
                        @endif
                    </tr>
                </thead>

                @foreach ($groups as $pillar => $group)
                    @php
                        $groupPrograms = $monitoringPrograms->where('pillar', $pillar)->values();
                    @endphp

                    <tbody data-pillar-group="{{ $pillar }}">
                        <tr>
                            <th
                                colspan="{{ $tableColumnCount }}"
                                class="p-2 text-white"
                                @style(['background:'.$group['color']])
                            >
                                {{ $group['label'] }}
                            </th>
                        </tr>

                        @foreach ($groupPrograms as $program)
                            <tr
                                class="border-b align-middle hover:bg-slate-50"
                                data-program-kind="{{ $program['jenis'] }}"
                                data-program-id="{{ $program['id'] }}"
                                data-program-status="{{ $program['status'] }}"
                                data-program-year="{{ $program['monitoring_year'] ?? (! empty($program['sort_timestamp']) ? date('Y', $program['sort_timestamp']) : '') }}"
                                @if($program['jenis'] === 'internal')
                                    data-cooperation-kind="{{ $program['jenis_kerjasama'] }}"
                                @endif
                            >
                                <td class="p-3 text-center">{{ $loop->iteration }}</td>
                                <td class="p-3">
                                    <span class="program-kind-badge {{ $program['jenis'] }}">
                                        {{ match ($program['jenis']) {
                                            'internal' => 'Program TJSL',
                                            'csr' => 'Bantuan TJSL',
                                            default => ucfirst($program['jenis']),
                                        } }}
                                    </span>

                                    @if($program['jenis'] === 'internal')
                                        <span class="program-kind-badge cooperation">
                                            {{ $program['jenis_kerjasama'] === 'non_pks' ? 'NON-PKS' : 'PKS' }}
                                        </span>
                                    @endif

                                    @php
                                        $isRejected = in_array(
                                            $program['status'],
                                            ['rejected_fase1'],
                                            true,
                                        );
                                    @endphp

                                    @if($isRejected)
                                        <span class="program-kind-badge rejected">Ditolak</span>
                                    @endif

                                    @php
                                        // Setiap controller menentukan sendiri apakah baris boleh
                                        // dibuka. Nilai null sengaja dipertahankan supaya status yang
                                        // belum publik (termasuk yang ditolak) tidak menghasilkan
                                        // tautan detail yang berakhir 404.
                                        $detailUrl = $program['detail_url'] ?? null;
                                    @endphp

                                    @if($detailUrl)
                                        <a class="font-medium hover:text-inka-red" href="{{ $detailUrl }}">
                                            {{ $program['title'] }}
                                        </a>
                                    @else
                                        <span class="font-medium">{{ $program['title'] }}</span>
                                    @endif

                                    @if($showCreator && ! empty($program['creator_name']))
                                        <p class="monitoring-meta">Dibuat oleh {{ $program['creator_name'] }}</p>
                                    @endif
                                </td>

                                @foreach ($documentColumns as $column)
                                    @php
                                        $notApplicable = (
                                            $program['jenis'] === 'internal'
                                            && $program['jenis_kerjasama'] === 'non_pks'
                                            && in_array($column['code'], ['C', 'D', 'E'], true)
                                        ) || (
                                            $program['jenis'] === 'csr'
                                            && in_array($column['code'], ['C', 'D', 'E'], true)
                                        );
                                        $hasDocument = ! $notApplicable
                                            && (
                                                in_array($column['code'], $program['document_codes'] ?? [], true)
                                                || in_array($column['type'], $program['document_types'] ?? [], true)
                                            );
                                        $isBastInProgress = ! $notApplicable
                                            && in_array($program['jenis'], ['internal', 'csr'], true)
                                            && $column['code'] === 'F'
                                            && $program['status'] === 'approved_fase1';
                                        $documentState = $notApplicable
                                            ? 'na'
                                            : ($isBastInProgress ? 'in-progress' : ($hasDocument ? 'complete' : 'incomplete'));
                                    @endphp
                                    <td
                                        class="p-2 text-center"
                                        data-document-column="{{ $column['code'] }}"
                                        data-document-state="{{ $documentState }}"
                                    >
                                        @if($notApplicable)
                                            <span
                                                class="document-na"
                                                title="Dokumen tidak berlaku untuk jenis program ini"
                                                aria-label="{{ $column['code'] }}: tidak berlaku"
                                            >
                                                &ndash;
                                            </span>
                                        @elseif($isBastInProgress)
                                            <span
                                                class="document-check in-progress"
                                                title="{{ ! empty($program['rejected_reason'])
                                                    ? 'On Progress - menunggu perbaikan dan unggah ulang dokumen BAST'
                                                    : 'On Progress - menunggu upload dokumen BAST' }}"
                                                aria-label="{{ $column['code'] }}: on progress, menunggu upload dokumen BAST"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="9"></circle>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                                                </svg>
                                            </span>
                                        @else
                                            <span
                                                class="document-check {{ $hasDocument ? 'complete' : 'incomplete' }}"
                                                title="{{ $hasDocument ? 'Dokumen lengkap' : 'Dokumen belum lengkap' }}"
                                                aria-label="{{ $column['code'] }}: {{ $hasDocument ? 'lengkap' : 'belum lengkap' }}"
                                            >
                                                {!! $hasDocument ? '&#10003;' : '&times;' !!}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach

                                @if($showRejectedInfo)
                                    <td class="p-2 text-center">
                                        @if($isRejected)
                                            @php
                                                $rejectionDialogId = 'rejection-'.$program['jenis'].'-'.$program['id'];
                                            @endphp
                                        <button
                                            type="button"
                                            class="rejection-open"
                                            data-rejection-open="{{ $rejectionDialogId }}"
                                            title="{{ $program['rejected_reason'] ?: 'Lihat alasan penolakan' }}"
                                            aria-label="Lihat alasan penolakan {{ $program['title'] }}"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <circle cx="12" cy="12" r="9"></circle>
                                                <path stroke-linecap="round" d="M12 10.5v6M12 7.5h.01"></path>
                                            </svg>
                                        </button>

                                        <dialog
                                            id="{{ $rejectionDialogId }}"
                                            class="rejection-dialog"
                                            data-rejection-dialog="{{ $rejectionDialogId }}"
                                        >
                                            <div class="rejection-dialog-card">
                                                <div class="rejection-dialog-header">
                                                    <div class="text-left">
                                                        <strong class="block text-lg">Alasan Penolakan</strong>
                                                        <span class="text-xs">{{ $program['title'] }}</span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="rejection-dialog-close"
                                                        data-rejection-close
                                                        aria-label="Tutup informasi penolakan"
                                                    >
                                                        &times;
                                                    </button>
                                                </div>
                                                <div class="rejection-dialog-body text-left">
                                                    <div>
                                                        <span class="rejection-dialog-label">Alasan</span>
                                                        <div class="rejection-dialog-reason">
                                                            {{ $program['rejected_reason'] ?: 'Alasan penolakan belum dicatat.' }}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <span class="rejection-dialog-label">Ditolak oleh</span>
                                                        <span>{{ $program['reviewer_name'] ?: 'Belum tercatat' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="rejection-dialog-label">Waktu penolakan</span>
                                                        <span>{{ $program['reviewed_at'] ?: 'Belum tercatat' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </dialog>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif
</div>

@if($showRejectedInfo)
    <script>
        document.querySelectorAll('[data-rejection-open]').forEach(function (button) {
            if (button.dataset.rejectionBound === 'true') return;
            button.dataset.rejectionBound = 'true';
            button.addEventListener('click', function () {
                document.getElementById(button.dataset.rejectionOpen)?.showModal();
            });
        });

        document.querySelectorAll('[data-rejection-dialog]').forEach(function (dialog) {
            if (dialog.dataset.rejectionBound === 'true') return;
            dialog.dataset.rejectionBound = 'true';
            dialog.querySelector('[data-rejection-close]')?.addEventListener('click', function () {
                dialog.close();
            });
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) dialog.close();
            });
        });
    </script>
@endif
