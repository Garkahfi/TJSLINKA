@php
    $perPilarChart = $perPilar->map(fn ($item) => [
        'nama' => $item->pillar?->name ?? 'Pilar lainnya',
        'warna' => $item->pillar?->color_hex ?? '#64748b',
        'rencana' => (float) $item->total_rencana,
        'realisasi' => (float) $item->total_realisasi,
    ])->values();

    $perWilayahMap = $perWilayah->map(fn ($item) => [
        'nama' => $item->nama,
        'lat' => (float) $item->latitude,
        'lng' => (float) $item->longitude,
        'realisasi' => (float) $item->realisasi_anggaran,
    ])->values();

    $bidangPrioritasChart = $bidangPrioritas->map(fn ($item) => [
        'nama' => $item->nama_bidang,
        'penyerapan' => (float) $item->penyerapan_persen,
    ])->values();

    $tpbChart = $tpbDashboard->map(fn ($item) => [
        'nomor' => 'TPB '.preg_replace('/^TPB\s*/i', '', $item->nomor_tpb),
        'nama' => $item->nama_tpb,
        'rencana' => (float) $item->rencana_anggaran,
        'realisasi' => (float) $item->realisasi_anggaran,
    ])->values();

    $formatRupiah = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
    $briYear = (int) ($pumkBriDashboard['year'] ?? now()->year);
    $briMonth = (int) ($pumkBriDashboard['latest_month'] ?? 0);
    $briHasSnapshot = (bool) ($pumkBriDashboard['ketersediaan']['snapshot'] ?? false);
    $briHasRka = (bool) ($pumkBriDashboard['ketersediaan']['rka'] ?? false);
    $briHasRealisasi = (bool) ($pumkBriDashboard['ketersediaan']['realisasi'] ?? false);
    $briUpdatedAt = filled($pumkBriDashboard['updated_at'] ?? null)
        ? \Illuminate\Support\Carbon::parse($pumkBriDashboard['updated_at'])
        : ($briMonth > 0
            ? \Illuminate\Support\Carbon::create($briYear, $briMonth, 1)->endOfMonth()
            : now());
    $liveUpdatedAt = filled($pumkLiveDashboard['updated_at'] ?? null)
        ? \Illuminate\Support\Carbon::parse($pumkLiveDashboard['updated_at'])
        : now();
@endphp

@push('head')
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
    <style>
        .tjsl-report-section{background:#f3f3f3;padding:48px 0}.tjsl-report-frame{border:2px solid #202020;background:#fff;padding:16px 20px 20px;color:#111827}.report-header{display:grid;grid-template-columns:190px minmax(0,1fr) 160px;align-items:center;gap:20px}.report-logo{display:block;width:auto;object-fit:contain}.report-logo.danantara{height:50px}.report-logo.inka{height:44px;justify-self:end}.report-title{margin:0;color:#a92d2f;font-size:25px;line-height:1.2;font-weight:700;text-align:center}.report-subhead{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:15px 0 10px;color:#6b7280;font-size:11px}.report-download{border:0;background:transparent;padding:0;color:#b42c30;font:600 11px Poppins,sans-serif;cursor:pointer}.report-download:hover{text-decoration:underline}.report-filters{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:6px;margin-bottom:14px}.report-filter{width:100%;height:31px;box-sizing:border-box;border:1px solid #ba5759;border-radius:9px;background:#fff;padding:4px 12px;color:#a92d2f;font:500 11px Poppins,sans-serif;outline:none}.report-filter:focus{box-shadow:0 0 0 2px rgba(169,45,47,.15)}.report-grid{display:grid;grid-template-columns:1.02fr 1.03fr 2.05fr;grid-template-areas:"summary pillar tpb" "gauge pillar tpb" "priority priority regions" "featured featured regions";gap:10px}.report-card{box-sizing:border-box;border:1px solid #e5e7eb;border-radius:10px;background:#fff;padding:10px;box-shadow:0 2px 5px rgba(15,23,42,.13);overflow:hidden}.report-card-title{margin:0 0 8px;font-size:12px;line-height:1.35;font-weight:600;text-align:center}.report-summary{grid-area:summary;display:grid;grid-template-columns:1fr 1fr;gap:7px}.report-total-card{display:grid;min-height:57px;place-content:center;border:1px solid #e5e7eb;border-radius:9px;background:#fff;text-align:center;box-shadow:0 2px 5px rgba(15,23,42,.12)}.report-total-card span{font-size:11px}.report-total-card strong{margin-top:3px;color:#b13235;font-size:17px;line-height:1.1;font-weight:500}.report-gauge-card{grid-area:gauge}.report-gauge{position:relative;max-width:230px;height:132px;margin:0 auto}.report-gauge svg{display:block;width:100%;height:118px}.report-gauge-center{position:absolute;left:50%;bottom:18px;transform:translateX(-50%);text-align:center;white-space:nowrap}.report-gauge-center span{display:block;color:#6b7280;font-size:11px}.report-gauge-center strong{display:block;margin-top:2px;font-size:25px;line-height:1}.report-gauge-scale{display:flex;justify-content:space-between;margin:-13px 15px 0;color:#6b7280;font-size:10px}.report-pillar-card{grid-area:pillar}.report-tpb-card{grid-area:tpb}.report-chart{position:relative;height:235px}.report-chart canvas,.pumk-chart canvas,.pumk-wide-chart canvas,.region-chart canvas{image-rendering:auto}.report-priority-card{grid-area:priority}.report-featured-card{grid-area:featured}.report-regions-card{grid-area:regions}.report-regions-content{display:grid;grid-template-columns:1.05fr 1fr;gap:12px;height:100%}.region-map{z-index:0;width:100%;height:100%;min-height:270px;overflow:hidden;border-radius:0;background:#e2e8f0}.report-table-wrap{overflow:auto}.report-table{width:100%;border-collapse:collapse;font-size:9px}.report-table th{background:#a92d2f;color:#fff;font-weight:600}.report-table.navy th{background:#0c2856}.report-table th,.report-table td{padding:5px 7px;border-bottom:1px solid #e5e7eb;text-align:left;white-space:nowrap}.report-table th:not(:first-child),.report-table td:not(:first-child){text-align:right}.report-table tbody tr:last-child td{border-bottom:0}.report-table .progress-cell{color:#111;text-align:center!important;font-weight:600}.report-empty{text-align:center!important;color:#6b7280;padding:24px 8px!important}.report-pillar-card .report-card-title,.report-tpb-card .report-card-title{min-height:30px;display:grid;place-items:center}.report-pillar-card .report-chart,.report-tpb-card .report-chart{height:240px}

        .pumk-section{background:#f3f3f3;padding:12px 0 56px}
        .pumk-heading{margin:0 0 28px;text-align:center;font-size:34px;line-height:1.2;font-weight:800;color:#050505}
        .pumk-report-frame{border:2px solid #202020;background:#fff;padding:16px 20px 20px;color:#111827}
        .pumk-report-header{display:grid;grid-template-columns:190px minmax(0,1fr) 160px;align-items:center;gap:20px}
        .pumk-report-title{margin:0;text-align:center;color:#a92d2f;font-size:23px;line-height:1.25;font-weight:700}
        .pumk-meta{display:flex;align-items:center;justify-content:space-between;margin:14px 0 10px;color:#6b7280;font-size:10px}
        .pumk-toolbar{display:grid;grid-template-columns:1fr 1fr 1.55fr 1.55fr 1.55fr;gap:8px;margin-bottom:10px}
        .pumk-total-card{display:grid;min-height:68px;place-content:center;border:1px solid #e5e7eb;border-radius:9px;background:#fff;text-align:center;box-shadow:0 2px 5px rgba(15,23,42,.12)}
        .pumk-total-card span{font-size:11px;font-weight:500}
        .pumk-total-card strong{margin-top:5px;color:#b13235;font-size:18px;line-height:1.1;font-weight:500}
        .pumk-filter{align-self:center;width:100%;height:32px;border:1px solid #ba5759;border-radius:8px;background:#fff;padding:4px 12px;color:#a92d2f;font:500 11px Poppins,sans-serif;outline:none}
        .pumk-filter:focus{box-shadow:0 0 0 2px rgba(169,45,47,.15)}
        .pumk-primary-grid{display:grid;grid-template-columns:1fr 1.35fr 1.35fr;gap:10px;margin-bottom:10px}
        .pumk-secondary-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .pumk-card{box-sizing:border-box;border:1px solid #e5e7eb;border-radius:10px;background:#fff;padding:10px;box-shadow:0 2px 5px rgba(15,23,42,.13);overflow:hidden}
        .pumk-card-title{min-height:30px;margin:0 0 7px;display:grid;place-items:center;text-align:center;font-size:12px;line-height:1.35;font-weight:600}
        .pumk-gauge{position:relative;max-width:250px;height:174px;margin:0 auto}
        .pumk-gauge svg{display:block;width:100%;height:155px}
        .pumk-gauge-center{position:absolute;left:50%;bottom:25px;transform:translateX(-50%);text-align:center;white-space:nowrap}
        .pumk-gauge-center span{display:block;color:#6b7280;font-size:10px}
        .pumk-gauge-center strong{display:block;margin-top:2px;font-size:24px;line-height:1}
        .pumk-gauge-scale{display:flex;justify-content:space-between;margin:-18px 12px 0;color:#6b7280;font-size:10px}
        .pumk-chart{position:relative;height:190px}
        .pumk-wide-chart{position:relative;height:220px}
        .pumk-live-section{background:#e9eef6;padding:12px 0 56px}.pumk-live-frame{border-color:#183153}.pumk-live-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:14px 0}.pumk-empty-note{display:grid;height:100%;min-height:130px;place-items:center;color:#64748b;font-size:12px;text-align:center}.pumk-source-note{margin:12px 0 0;color:#64748b;font-size:10px;text-align:right}
        .pumk-bri-filter-bar{display:flex;min-height:58px;align-items:center;justify-content:flex-end;margin:14px 0 12px;border:1px solid #e2e8f0;border-radius:5px;background:#f8fafc;padding:10px 14px}.pumk-year-form{display:flex;align-items:center;gap:10px}.pumk-year-form label{font-size:12px;font-weight:600}.pumk-year-select{height:38px;min-width:130px;border:1px solid #aebed4;border-radius:5px;background:#fff;padding:0 35px 0 12px;color:#0f2855;font:600 13px Poppins,sans-serif;cursor:pointer}.pumk-bri-frame{border:2px solid #202020;background:#fff;padding:16px 20px 20px}.pumk-bri-report-title{color:#0f2855}.pumk-bri-validation-note{margin:0 0 12px;border:1px solid #f59e0b;border-radius:5px;background:#fffbeb;padding:9px 12px;color:#92400e;font-size:11px;line-height:1.5}.pumk-bri-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:12px}.pumk-bri-summary-card{min-height:96px;border:1px solid #aebed4;border-radius:5px;background:#fff;padding:16px;box-shadow:0 2px 5px rgba(15,23,42,.07)}.pumk-bri-summary-card span{display:block;color:#0c48ac;font-size:12px;line-height:1.45;font-weight:700}.pumk-bri-summary-card strong,.pumk-bri-summary-card strong.neutral{display:block;margin-top:10px;color:#0662df;font-size:20px;line-height:1.2;font-weight:700}.pumk-bri-summary-card small{display:block;margin-top:6px;color:#94a3b8;font-size:9px}.pumk-bri-primary{display:grid;grid-template-columns:.9fr 1.15fr 1.15fr;gap:12px;margin-bottom:12px}.pumk-bri-lower{display:grid;grid-template-columns:1.55fr 1fr;gap:12px;margin-bottom:12px}.pumk-bri-data-grid{display:grid;grid-template-columns:1.65fr 1fr;gap:12px}.pumk-bri-card{box-sizing:border-box;border:1px solid #aebed4;border-radius:5px;background:#fff;padding:13px;box-shadow:0 2px 5px rgba(15,23,42,.06);overflow:hidden}.pumk-bri-card-title{min-height:28px;margin:0 0 8px;display:flex;align-items:center;color:#0f2855;text-align:left;font-size:13px;line-height:1.4;font-weight:700}.pumk-bri-chart{position:relative;height:225px}.pumk-bri-gauge{position:relative;max-width:250px;height:208px;margin:0 auto}.pumk-bri-gauge svg{display:block;width:100%;height:180px}.pumk-bri-gauge-center{position:absolute;left:50%;bottom:34px;transform:translateX(-50%);color:#0f2855;text-align:center;white-space:nowrap}.pumk-bri-gauge-center span{display:block;color:#0f2855;font-size:10px}.pumk-bri-gauge-center strong{display:block;margin-top:4px;font-size:27px;line-height:1}.pumk-bri-gauge-scale{display:flex;justify-content:space-between;margin:-28px 10px 0;color:#0f2855;font-size:10px}.pumk-bri-map{position:relative;z-index:0;height:310px;border-radius:4px;background:#e2e8f0}.pumk-map-card-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:10px}.pumk-map-card-footer span{color:#64748b;font-size:10px}.pumk-map-toggle{border:1px solid #0874f9;border-radius:4px;background:#fff;padding:8px 26px;color:#0562d6;font:600 11px Poppins,sans-serif;cursor:pointer}.pumk-map-table-wrap{overflow-x:auto}.pumk-map-table{width:100%;border-collapse:collapse;font-size:11px}.pumk-map-table th{background:#e7edf5;color:#0f2855;font-weight:600}.pumk-map-table th,.pumk-map-table td{padding:9px 11px;border:1px solid #cbd5e1;text-align:left}.pumk-map-table th:not(:first-child),.pumk-map-table td:not(:first-child){text-align:right}.pumk-map-table tbody tr:hover{background:#f8fafc}.pumk-region-note{margin:8px 0 0;color:#64748b;font-size:9px;line-height:1.45}.pumk-bri-empty{position:absolute;inset:0;display:grid;place-items:center;padding:16px;color:#64748b;font-size:11px;text-align:center}.pumk-rka-note{display:flex;align-items:center;gap:7px;margin-top:10px;border-radius:4px;background:#fff7ed;padding:8px 10px;color:#9a3412;font-size:9px;line-height:1.4}.pumk-rka-panel{border-color:#ef4444;background:#fffafa}.pumk-rka-panel .pumk-bri-card-title{justify-content:center;color:#dc2626}.pumk-rka-list{display:grid;gap:5px;border:1px solid #fecaca;border-radius:4px;background:#fff;padding:10px 12px}.pumk-rka-row{display:grid;grid-template-columns:1fr auto;gap:12px;color:#b91c1c;font-size:10px}.pumk-rka-row strong{font-weight:600}.pumk-rka-empty-note{margin:9px 0 0;color:#b91c1c;font-size:9px;line-height:1.45;text-align:center}
        .pumk-map-state{position:absolute;inset:0;z-index:500;display:grid;place-items:center;background:#eef2f7;padding:20px;color:#64748b;font-size:11px;line-height:1.55;text-align:center}.pumk-map-state[hidden]{display:none}.pumk-map-legend{border-radius:5px;background:rgba(255,255,255,.94);padding:8px 10px;box-shadow:0 1px 5px rgba(15,23,42,.25);color:#334155;font:500 9px Poppins,sans-serif}.pumk-map-legend strong{display:block;margin-bottom:5px;color:#0f2855;font-size:10px}.pumk-map-legend-scale{display:flex;align-items:center;gap:4px}.pumk-map-legend-swatch{width:24px;height:8px;border-radius:2px}.pumk-map-tooltip{min-width:175px;font:500 10px/1.55 Poppins,sans-serif}.pumk-map-tooltip strong{display:block;margin-bottom:4px;color:#0f2855;font-size:11px}.pumk-map-tooltip-row{display:flex;justify-content:space-between;gap:14px}.pumk-map-actions{display:flex;align-items:center;gap:7px}.pumk-map-reset{border:0;background:transparent;padding:7px;color:#64748b;font:500 10px Poppins,sans-serif;cursor:pointer}.pumk-map-reset[hidden]{display:none}.pumk-map-caption{margin:8px 0 0;color:#64748b;font-size:9px;line-height:1.45}.pumk-map-unmapped{color:#b45309}.pumk-map-table tbody tr.is-filtered-out{display:none}.pumk-map-table tbody tr.is-selected{background:#eff6ff}.pumk-region-filter-note{margin:0 0 8px;color:#0f2855;font-size:10px;font-weight:600}
        .faq-section{background:#f3f3f3;padding:34px 0 56px}
        .faq-heading{margin:0 0 34px;text-align:center;font-size:42px;line-height:1.2;font-weight:800;color:#050505}

        @media(max-width:1100px){.report-grid{grid-template-columns:1fr 1fr;grid-template-areas:"summary summary" "gauge pillar" "tpb tpb" "priority priority" "featured featured" "regions regions"}.report-tpb-card .report-chart{height:230px}.report-regions-content{min-height:300px}.report-header{grid-template-columns:160px 1fr 130px}.report-title{font-size:21px}}
        @media(max-width:1100px){.pumk-report-header{grid-template-columns:160px 1fr 130px}.pumk-toolbar{grid-template-columns:1fr 1fr 1fr}.pumk-total-card{grid-row:1}.pumk-primary-grid{grid-template-columns:1fr 1fr}.pumk-primary-grid .pumk-card:first-child{grid-column:1/-1}.pumk-secondary-grid{grid-template-columns:1fr}.pumk-live-summary{grid-template-columns:1fr 1fr}.pumk-bri-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.pumk-bri-primary{grid-template-columns:1fr 1fr}.pumk-bri-primary .pumk-bri-card:first-child{grid-column:1/-1}.pumk-bri-lower,.pumk-bri-data-grid{grid-template-columns:1fr}.faq-heading{font-size:36px}}
        @media(max-width:720px){.tjsl-report-section{padding:28px 0}.tjsl-report-frame{border-width:1px;padding:14px 12px}.report-header{grid-template-columns:1fr 1fr}.report-title{grid-column:1/-1;grid-row:1;margin-bottom:8px}.report-logo.danantara{height:42px}.report-logo.inka{height:37px}.report-filters{grid-template-columns:1fr 1fr}.report-grid{grid-template-columns:1fr;grid-template-areas:"summary" "gauge" "pillar" "tpb" "priority" "featured" "regions"}.report-regions-content{grid-template-columns:1fr;height:auto}.region-map{height:280px}.report-table{font-size:8px}.report-card{padding:8px}.pumk-section{padding-bottom:36px}.pumk-heading{font-size:27px}.pumk-report-frame{border-width:1px;padding:14px 12px}.pumk-report-header{grid-template-columns:1fr 1fr}.pumk-report-title{grid-column:1/-1;grid-row:1;font-size:18px}.pumk-toolbar,.pumk-primary-grid,.pumk-secondary-grid,.pumk-live-summary,.pumk-bri-summary,.pumk-bri-primary{grid-template-columns:1fr}.pumk-primary-grid .pumk-card:first-child,.pumk-bri-primary .pumk-bri-card:first-child{grid-column:auto}.pumk-bri-filter-bar{justify-content:stretch}.pumk-year-form{width:100%;justify-content:space-between}.pumk-year-select{flex:1}.pumk-chart,.pumk-wide-chart,.pumk-bri-chart{height:230px}.pumk-bri-map{height:280px}.faq-section{padding:26px 0 38px}.faq-heading{margin-bottom:24px;font-size:28px}}
        .report-grid{grid-template-areas:"summary pillar tpb" "gauge pillar tpb" "priority priority regions"}
        .report-priority-card .report-chart{height:270px}
        .region-chart{position:relative;min-width:0;height:270px}
        @media(max-width:1100px){.report-grid{grid-template-areas:"summary summary" "gauge pillar" "tpb tpb" "priority priority" "regions regions"}}
        @media(max-width:720px){.report-grid{grid-template-areas:"summary" "gauge" "pillar" "tpb" "priority" "regions"}.region-chart{height:260px}}
        @media print{body>nav,.tjsl-report-section~section,footer{display:none!important}.tjsl-report-section{padding:0;background:#fff}.tjsl-report-frame{border:1px solid #111}.container-site{max-width:none!important;padding:0!important}}
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script>
        (function () {
            const dashboardData = document.getElementById('home-dashboard-data');

            if (!dashboardData) {
                return;
            }

            const perPilar = JSON.parse(dashboardData.dataset.perPilar || '[]');
            const perWilayah = JSON.parse(dashboardData.dataset.perWilayah || '[]');
            const bidangPrioritas = JSON.parse(dashboardData.dataset.bidangPrioritas || '[]');
            const perTpb = JSON.parse(dashboardData.dataset.perTpb || '[]');
            const pumkBri = JSON.parse(dashboardData.dataset.pumkBri || '{}');
            const pumkLive = JSON.parse(dashboardData.dataset.pumkLive || '{}');
            const chartPixelRatio = Math.min(3, Math.max(2, window.devicePixelRatio || 1));

            if (window.Chart) {
                Chart.defaults.devicePixelRatio = chartPixelRatio;
                Chart.defaults.color = '#334155';
                Chart.defaults.font.family = "Poppins, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
                Chart.defaults.font.size = 10;
                Chart.defaults.font.weight = '500';
            }
            const rupiah = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            });
            const compactNumber = new Intl.NumberFormat('id-ID', {
                notation: 'compact',
                maximumFractionDigits: 1,
            });

            const chartCanvas = document.getElementById('per-pilar-chart');

            if (chartCanvas && window.Chart && perPilar.length) {
                new Chart(chartCanvas, {
                    type: 'bar',
                    data: {
                        labels: perPilar.map(function (item) {
                            return item.nama;
                        }),
                        datasets: [
                            {
                                label: 'Rencana',
                                data: perPilar.map(function (item) {
                                    return item.rencana;
                                }),
                                backgroundColor: '#ad3032',
                                borderColor: '#ad3032',
                                borderWidth: 1,
                            },
                            {
                                label: 'Realisasi',
                                data: perPilar.map(function (item) {
                                    return item.realisasi;
                                }),
                                backgroundColor: '#f2a341',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'start',
                                labels: {
                                    boxWidth: 14,
                                    boxHeight: 8,
                                    font: {
                                        size: 10,
                                    },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + rupiah.format(context.parsed.y);
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 10,
                                    },
                                    callback: function (value) {
                                        return compactNumber.format(value);
                                    },
                                },
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 9,
                                    },
                                },
                            },
                        },
                    },
                });
            }

            const tpbCanvas = document.getElementById('tpb-chart');

            if (tpbCanvas && window.Chart && perTpb.length) {
                new Chart(tpbCanvas, {
                    type: 'bar',
                    data: {
                        labels: perTpb.map(function (item) {
                            return item.nomor;
                        }),
                        datasets: [
                            {
                                label: 'Rencana',
                                data: perTpb.map(function (item) {
                                    return item.rencana;
                                }),
                                backgroundColor: '#0c2856',
                            },
                            {
                                label: 'Realisasi',
                                data: perTpb.map(function (item) {
                                    return item.realisasi;
                                }),
                                backgroundColor: '#65b9b7',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'start',
                                labels: {
                                    boxWidth: 14,
                                    boxHeight: 8,
                                    font: {
                                        size: 10,
                                    },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    title: function (items) {
                                        const item = perTpb[items[0].dataIndex];

                                        return item.nama
                                            ? item.nomor + ' - ' + item.nama
                                            : item.nomor;
                                    },
                                    label: function (context) {
                                        return context.dataset.label + ': ' + rupiah.format(context.parsed.y);
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 10,
                                    },
                                    callback: function (value) {
                                        return compactNumber.format(value);
                                    },
                                },
                            },
                            x: {
                                ticks: {
                                    maxRotation: 35,
                                    minRotation: 35,
                                    font: {
                                        size: 9,
                                    },
                                },
                            },
                        },
                    },
                });
            }

            const priorityCanvas = document.getElementById('priority-chart');

            if (priorityCanvas && window.Chart && bidangPrioritas.length) {
                new Chart(priorityCanvas, {
                    type: 'bar',
                    data: {
                        labels: bidangPrioritas.map(function (item) {
                            return item.nama.replace(/^Bidang\s+/i, '');
                        }),
                        datasets: [{
                            label: 'Penyerapan',
                            data: bidangPrioritas.map(function (item) {
                                return item.penyerapan;
                            }),
                            backgroundColor: bidangPrioritas.map(function (item) {
                                if (item.penyerapan >= 70) return '#84c788';
                                if (item.penyerapan >= 40) return '#f4d15c';
                                return '#e96d70';
                            }),
                            borderWidth: 0,
                            borderRadius: 3,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return 'Penyerapan: ' + context.parsed.y.toLocaleString('id-ID') + '%';
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    font: { size: 10 },
                                    callback: function (value) { return value + '%'; },
                                },
                            },
                            x: {
                                ticks: {
                                    maxRotation: 0,
                                    minRotation: 0,
                                    font: { size: 10 },
                                },
                            },
                        },
                    },
                });
            }

            const regionCanvas = document.getElementById('region-chart');

            if (regionCanvas && window.Chart && perWilayah.length) {
                new Chart(regionCanvas, {
                    type: 'bar',
                    data: {
                        labels: perWilayah.map(function (item) { return item.nama; }),
                        datasets: [{
                            label: 'Realisasi Anggaran',
                            data: perWilayah.map(function (item) { return item.realisasi; }),
                            backgroundColor: '#0c2856',
                            borderWidth: 0,
                            borderRadius: 2,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return rupiah.format(context.parsed.x);
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    font: { size: 10 },
                                    callback: function (value) { return compactNumber.format(value); },
                                },
                            },
                            y: {
                                ticks: { font: { size: 10 } },
                            },
                        },
                    },
                });
            }

            const pumkColors = ['#b8313a', '#f3a24b', '#9f6edc', '#b4c966', '#24b4c5', '#f3c445', '#df5f87', '#2563eb'];
            const pumkLegend = {
                position: 'right',
                labels: { boxWidth: 10, boxHeight: 10, padding: 8, font: { size: 10 } },
            };
            const moneyTooltip = {
                callbacks: {
                    label: function (context) {
                        return context.label + ': ' + rupiah.format(context.raw || 0);
                    },
                },
            };

            function renderDistribution(canvasId, items, type, colors) {
                const canvas = document.getElementById(canvasId);
                if (!canvas || !window.Chart || !Array.isArray(items) || items.length === 0) return;

                new Chart(canvas, {
                    type: type,
                    data: {
                        labels: items.map(function (item) { return item.label; }),
                        datasets: [{
                            data: items.map(function (item) { return item.nilai; }),
                            backgroundColor: items.map(function (_, index) {
                                return Array.isArray(colors) && colors[index]
                                    ? colors[index]
                                    : pumkColors[index % pumkColors.length];
                            }),
                            borderColor: '#ffffff',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: type === 'doughnut' ? '38%' : 0,
                        plugins: { legend: pumkLegend, tooltip: moneyTooltip },
                    },
                });
            }

            function renderTrend(canvasId, trend, type, stacked) {
                const canvas = document.getElementById(canvasId);
                if (!canvas || !window.Chart || !trend || !trend.datasets || trend.datasets.length === 0) return;

                new Chart(canvas, {
                    type: type,
                    data: {
                        labels: trend.labels,
                        datasets: trend.datasets.map(function (dataset, index) {
                            const color = pumkColors[index % pumkColors.length];
                            return Object.assign({}, dataset, {
                                borderColor: color,
                                backgroundColor: type === 'bar' ? color : color + '24',
                                borderWidth: type === 'line' ? 2 : 1,
                                pointRadius: type === 'line' ? 2 : 0,
                                tension: .15,
                                spanGaps: false,
                            });
                        }),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { boxWidth: 10, boxHeight: 7, font: { size: 9 } } },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + rupiah.format(context.raw || 0);
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                stacked: stacked,
                                ticks: { font: { size: 10 }, callback: function (value) { return compactNumber.format(value); } },
                            },
                            x: { stacked: stacked, ticks: { font: { size: 10 } } },
                        },
                    },
                });
            }

            const sectorColors = ['#0c65c6', '#3f8dde', '#72ace7', '#a3c9ef', '#d4e6f8', '#8baed3', '#507eb7', '#174f91'];
            const qualityColors = ['#0b72e7', '#69a7e8', '#9fc8f2', '#cce1f7'];

            renderDistribution('pumk-portfolio-chart', pumkBri.sektor, 'pie', sectorColors);
            renderDistribution('pumk-quality-chart', pumkBri.kualitas, 'pie', qualityColors);

            const outstandingMonthCanvas = document.getElementById('pumk-outstanding-month-chart');
            if (outstandingMonthCanvas && window.Chart && Array.isArray(pumkBri.tren_outstanding) && pumkBri.tren_outstanding.length) {
                new Chart(outstandingMonthCanvas, {
                    type: 'bar',
                    data: {
                        labels: pumkBri.tren_outstanding.map(function (item) { return item.label; }),
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Outstanding',
                                data: pumkBri.tren_outstanding.map(function (item) { return item.nilai; }),
                                backgroundColor: 'rgba(108, 169, 230, .42)',
                                borderColor: '#69a7e8',
                                borderWidth: 1,
                                borderRadius: 5,
                                maxBarThickness: 44,
                            },
                            {
                                type: 'line',
                                label: 'Tren Outstanding',
                                data: pumkBri.tren_outstanding.map(function (item) { return item.nilai; }),
                                borderColor: '#076ee8',
                                backgroundColor: '#076ee8',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                tension: .25,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { boxWidth: 11, boxHeight: 8, font: { size: 9 } } },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + rupiah.format(context.raw || 0);
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: { size: 9 },
                                    callback: function (value) { return compactNumber.format(value); },
                                },
                            },
                            x: { ticks: { font: { size: 9 } } },
                        },
                    },
                });
            }

            renderDistribution('pumk-live-sector-chart', pumkLive.sektor, 'doughnut');
            renderDistribution('pumk-live-quality-chart', pumkLive.kolektibilitas, 'pie');
            renderTrend('pumk-live-province-chart', {
                labels: (pumkLive.sebaran_provinsi || []).map(function (item) { return item.label; }),
                datasets: [{
                    label: 'Saldo Piutang',
                    data: (pumkLive.sebaran_provinsi || []).map(function (item) { return item.nilai; }),
                }],
            }, 'bar', false);
            renderTrend('pumk-live-quality-trend-chart', pumkLive.tren_kolektibilitas, 'bar', false);

            const pumkMapData = pumkBri.peta || {};
            const pumkMapElement = document.getElementById('pumk-bri-map');
            const pumkMapState = document.querySelector('[data-pumk-map-state]');
            const mapToggle = document.querySelector('[data-pumk-map-toggle]');
            const mapReset = document.querySelector('[data-pumk-map-reset]');
            const mapSummary = document.querySelector('[data-pumk-map-summary]');
            const regionFilterNote = document.querySelector('[data-pumk-region-filter-note]');
            const regionRows = Array.from(document.querySelectorAll('[data-pumk-region-row]'));
            const regionItems = Array.isArray(pumkMapData.items) ? pumkMapData.items : [];
            let selectedRegion = null;
            let selectedRegionLabel = null;
            let selectedRegionLayer = null;

            function normalizePumkRegion(value) {
                let normalized = String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim()
                    .replace(/\s+/g, ' ');

                if (normalized.startsWith('kota ')) {
                    return normalized;
                }

                normalized = normalized.replace(/^(kab|kabupaten)\s+/, '');
                normalized = normalized.replace(/\s+(kab|kabupaten)$/, '');

                return normalized || 'belum ditentukan';
            }

            function applyRegionFilter(regionKey, regionLabel) {
                selectedRegion = regionKey || null;
                selectedRegionLabel = regionLabel || null;

                regionRows.forEach(function (row) {
                    const matches = !selectedRegion || row.dataset.regionKey === selectedRegion;
                    row.classList.toggle('is-filtered-out', !matches);
                    row.classList.toggle('is-selected', Boolean(selectedRegion && matches));
                });

                if (regionFilterNote) {
                    regionFilterNote.hidden = !selectedRegion;
                    regionFilterNote.textContent = selectedRegion
                        ? 'Menampilkan data wilayah: ' + selectedRegionLabel
                        : '';
                }

                if (mapReset) {
                    mapReset.hidden = !selectedRegion;
                }

                if (mapToggle) {
                    mapToggle.textContent = selectedRegion
                        ? 'Lihat data ' + selectedRegionLabel
                        : 'Lihat data';
                }
            }

            mapToggle?.addEventListener('click', function () {
                applyRegionFilter(selectedRegion, selectedRegionLabel);
                document.getElementById('pumk-bri-data-mitra')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });

            mapReset?.addEventListener('click', function () {
                applyRegionFilter(null, null);

                if (selectedRegionLayer?.setStyle) {
                    selectedRegionLayer.setStyle({ color: '#ffffff', weight: 1 });
                }

                selectedRegionLayer = null;
            });

            if (pumkMapElement && pumkMapData.available && window.L && regionItems.length) {
                const itemByKey = new Map(regionItems.map(function (item) {
                    return [item.geo_key, item];
                }));
                const maxMitra = Math.max(1, ...regionItems.map(function (item) {
                    return Number(item.jumlah_mitra || 0);
                }));
                const choroplethColors = ['#dbeafe', '#93c5fd', '#60a5fa', '#2563eb', '#0f3d91'];
                const fillColor = function (value) {
                    const ratio = Number(value || 0) / maxMitra;
                    const index = Math.min(choroplethColors.length - 1, Math.floor(ratio * choroplethColors.length));

                    return choroplethColors[index];
                };
                const map = L.map(pumkMapElement, {
                    scrollWheelZoom: false,
                    zoomControl: true,
                }).setView([-2.5, 118], 5);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 18,
                }).addTo(map);

                fetch(pumkMapData.geojson_url, { credentials: 'same-origin' })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('GeoJSON tidak dapat dimuat.');
                        }

                        return response.json();
                    })
                    .then(function (geojson) {
                        const geoLayer = L.geoJSON(geojson, {
                            filter: function (feature) {
                                return Boolean(feature?.properties?.WADMKK);
                            },
                            style: function (feature) {
                                const item = itemByKey.get(normalizePumkRegion(feature?.properties?.WADMKK));

                                return {
                                    color: item ? '#ffffff' : '#cbd5e1',
                                    weight: item ? 1 : .5,
                                    fillColor: item ? fillColor(item.jumlah_mitra) : '#e5e7eb',
                                    fillOpacity: item ? .88 : .28,
                                };
                            },
                            onEachFeature: function (feature, layer) {
                                const key = normalizePumkRegion(feature?.properties?.WADMKK);
                                const item = itemByKey.get(key);

                                if (!item) {
                                    return;
                                }

                                const tooltip = document.createElement('div');
                                tooltip.className = 'pumk-map-tooltip';
                                const title = document.createElement('strong');
                                title.textContent = item.nama;
                                tooltip.appendChild(title);

                                [
                                    ['Mitra unik', Number(item.jumlah_mitra || 0).toLocaleString('id-ID')],
                                    ['Fasilitas', Number(item.jumlah_fasilitas || 0).toLocaleString('id-ID')],
                                    ['Outstanding', rupiah.format(Number(item.outstanding || 0))],
                                    ['L / KL / D / M', [item.lancar, item.kurang_lancar, item.diragukan, item.macet].map(Number).join(' / ')],
                                ].forEach(function (entry) {
                                    const row = document.createElement('div');
                                    row.className = 'pumk-map-tooltip-row';
                                    const label = document.createElement('span');
                                    const value = document.createElement('b');
                                    label.textContent = entry[0];
                                    value.textContent = entry[1];
                                    row.append(label, value);
                                    tooltip.appendChild(row);
                                });

                                layer.bindTooltip(tooltip, { sticky: true, direction: 'top' });
                                layer.on('mouseover', function () {
                                    layer.setStyle({ color: '#0f2855', weight: 2 });
                                    layer.bringToFront();
                                });
                                layer.on('mouseout', function () {
                                    if (layer !== selectedRegionLayer) {
                                        geoLayer.resetStyle(layer);
                                    }
                                });
                                layer.on('click', function () {
                                    if (selectedRegionLayer && selectedRegionLayer !== layer) {
                                        geoLayer.resetStyle(selectedRegionLayer);
                                    }

                                    selectedRegionLayer = layer;
                                    layer.setStyle({ color: '#f59e0b', weight: 3 });
                                    applyRegionFilter(key, item.nama);
                                });
                            },
                        }).addTo(map);

                        if (geoLayer.getBounds().isValid()) {
                            map.fitBounds(geoLayer.getBounds().pad(.18), { maxZoom: 8 });
                        }

                        const legend = L.control({ position: 'bottomright' });
                        legend.onAdd = function () {
                            const element = L.DomUtil.create('div', 'pumk-map-legend');
                            const title = document.createElement('strong');
                            const scale = document.createElement('div');
                            const minimum = document.createElement('span');
                            const maximum = document.createElement('span');
                            title.textContent = 'Jumlah Mitra';
                            scale.className = 'pumk-map-legend-scale';
                            minimum.textContent = '1';
                            maximum.textContent = maxMitra.toLocaleString('id-ID');
                            scale.appendChild(minimum);

                            choroplethColors.forEach(function (color) {
                                const swatch = document.createElement('span');
                                swatch.className = 'pumk-map-legend-swatch';
                                swatch.style.backgroundColor = color;
                                scale.appendChild(swatch);
                            });
                            scale.appendChild(maximum);

                            element.append(title, scale);

                            return element;
                        };
                        legend.addTo(map);

                        if (pumkMapState) {
                            pumkMapState.hidden = true;
                        }

                        if (mapSummary) {
                            mapSummary.textContent = regionItems.length.toLocaleString('id-ID')
                                + ' wilayah · '
                                + Number(pumkMapData.total_mitra_snapshot || 0).toLocaleString('id-ID')
                                + ' mitra unik';
                        }
                    })
                    .catch(function () {
                        if (pumkMapState) {
                            pumkMapState.hidden = false;
                            pumkMapState.textContent = 'Data persebaran belum dapat dimuat.';
                        }
                    });
            } else if (pumkMapState && !pumkMapData.available) {
                pumkMapState.hidden = false;
                pumkMapState.textContent = 'Belum tersedia data snapshot untuk tahun ini.';
            } else if (pumkMapState) {
                pumkMapState.hidden = false;
                pumkMapState.textContent = 'Peta belum dapat ditampilkan pada perangkat ini.';
            }

            const mapElement = document.getElementById('peta-wilayah');

            if (mapElement && window.L) {
                const map = L.map(mapElement, {
                    scrollWheelZoom: false,
                }).setView([-7.7, 112.2], 8);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 18,
                }).addTo(map);

                const points = [];

                perWilayah.forEach(function (item) {
                    if (!Number.isFinite(item.lat) || !Number.isFinite(item.lng)) {
                        return;
                    }

                    const point = [item.lat, item.lng];
                    const popup = document.createElement('div');
                    const title = document.createElement('strong');
                    const amount = document.createElement('span');

                    title.textContent = item.nama;
                    amount.textContent = rupiah.format(item.realisasi);
                    popup.append(title, document.createElement('br'), amount);

                    L.marker(point).addTo(map).bindPopup(popup);
                    points.push(point);
                });

                if (points.length > 1) {
                    map.fitBounds(L.latLngBounds(points).pad(0.15), {
                        maxZoom: 9,
                    });
                }
            }
        })();
    </script>
@endpush

<x-layouts.app title="Home — LENSA TJSL INKA">
    <template
        id="home-dashboard-data"
        data-per-pilar="{{ $perPilarChart->toJson() }}"
        data-per-wilayah="{{ $perWilayahMap->toJson() }}"
        data-bidang-prioritas="{{ $bidangPrioritasChart->toJson() }}"
        data-per-tpb="{{ $tpbChart->toJson() }}"
        data-pumk-bri="{{ collect($pumkBriDashboard)->except('updated_at')->toJson() }}"
        data-pumk-live="{{ collect($pumkLiveDashboard)->except('updated_at')->toJson() }}"
    ></template>
    <x-hero-video
        video="videos/waterfall-bg.mp4"
        variant="simple"
        :logo="true"
        description="Lensa TJSL merupakan wujud nyata integrasi dan transparansi informasi atas kontribusi Program Tanggung Jawab Sosial dan Lingkungan (TJSL) dalam pelaksanaan program berkelanjutan dan mitigasi risiko PT Industri Kereta Api (Persero)."
    />

    <section class="tjsl-report-section">
        <div class="container-site">
            <div class="tjsl-report-frame">
                <header class="report-header">
                    <img
                        src="{{ asset('images/logo/danantara.png') }}"
                        alt="Danantara Indonesia"
                        class="report-logo danantara"
                    >
                    <h2 class="report-title">
                        Realisasi Anggaran Program TJSL Tahun {{ $dashboardUpdatedAt->year }}
                    </h2>
                    <img
                        src="{{ asset('images/logo/inka.png') }}"
                        alt="PT INKA"
                        class="report-logo inka"
                    >
                </header>

                <div class="report-subhead">
                    <span>Update: {{ $dashboardUpdatedAt->locale('id')->translatedFormat('d M Y') }}</span>
                    <button type="button" class="report-download" onclick="window.print()">
                        Download Laporan
                    </button>
                </div>

                <div class="report-filters" aria-label="Filter laporan TJSL">
                    <select class="report-filter" aria-label="Nama Program">
                        <option>Nama Program</option>
                    </select>
                    <select class="report-filter" aria-label="Pilar">
                        <option>Pilar</option>
                        @foreach($perPilar as $item)
                            <option>{{ $item->pillar?->name }}</option>
                        @endforeach
                    </select>
                    <select class="report-filter" aria-label="TPB">
                        <option>TPB</option>
                    </select>
                    <select class="report-filter" aria-label="Rencana Anggaran">
                        <option>Rencana Anggaran</option>
                    </select>
                    <select class="report-filter" aria-label="Realisasi Anggaran">
                        <option>Realisasi Anggaran</option>
                    </select>
                </div>

                <div class="report-grid">
                    <div class="report-summary">
                        <article class="report-total-card">
                            <span>Rencana Anggaran</span>
                            <strong>{{ $formatRupiah($totalRencana) }}</strong>
                        </article>
                        <article class="report-total-card">
                            <span>Realisasi Anggaran</span>
                            <strong>{{ $formatRupiah($totalRealisasi) }}</strong>
                        </article>
                    </div>

                    <article class="report-card report-gauge-card">
                        <h3 class="report-card-title">Progres Penyerapan (%)</h3>
                        <div class="report-gauge">
                            <svg viewBox="0 0 200 110" aria-hidden="true">
                                <path
                                    d="M20 100 A80 80 0 0 1 180 100"
                                    fill="none"
                                    stroke="#f8dada"
                                    stroke-width="28"
                                    pathLength="100"
                                />
                                <path
                                    d="M20 100 A80 80 0 0 1 180 100"
                                    fill="none"
                                    stroke="#ad3032"
                                    stroke-width="28"
                                    pathLength="100"
                                    @style(['stroke-dasharray:'.$penyerapan.' 100'])
                                />
                            </svg>
                            <div class="report-gauge-center">
                                <span>Penyerapan (%)</span>
                                <strong>{{ number_format($penyerapan, 1, ',', '.') }}%</strong>
                            </div>
                        </div>
                        <div class="report-gauge-scale"><span>0%</span><span>100%</span></div>
                    </article>

                    <article class="report-card report-pillar-card">
                        <h3 class="report-card-title">Rencana dan Realisasi Anggaran per Pilar</h3>
                        <div class="report-chart">
                            <canvas
                                id="per-pilar-chart"
                                aria-label="Grafik rencana dan realisasi anggaran per pilar"
                            ></canvas>
                        </div>
                    </article>

                    <article class="report-card report-tpb-card">
                        <h3 class="report-card-title">
                            Rencana dan Realisasi Anggaran per Tujuan Pembangunan Berkelanjutan (TPB)
                        </h3>
                        <div class="report-chart">
                            <canvas id="tpb-chart" aria-label="Grafik rencana dan realisasi per TPB"></canvas>
                        </div>
                    </article>

                    <article class="report-card report-priority-card">
                        <h3 class="report-card-title">Realisasi Anggaran untuk Bidang Prioritas</h3>
                        <div class="report-chart">
                            <canvas
                                id="priority-chart"
                                aria-label="Grafik persentase penyerapan anggaran bidang prioritas"
                            ></canvas>
                        </div>
                    </article>

                    <article class="report-card report-regions-card">
                        <h3 class="report-card-title">Realisasi Anggaran per Wilayah</h3>
                        <div class="report-regions-content">
                            <div class="region-chart">
                                <canvas
                                    id="region-chart"
                                    aria-label="Grafik realisasi anggaran berdasarkan wilayah"
                                ></canvas>
                            </div>

                            <div
                                id="peta-wilayah"
                                class="region-map"
                                aria-label="Peta realisasi anggaran berdasarkan wilayah"
                            ></div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section id="dashboard-pumk-bri" class="pumk-section">
        <div class="container-site">
            <div class="pumk-report-frame pumk-bri-frame">
                <header class="pumk-report-header">
                    <img src="{{ asset('images/logo/danantara.png') }}" alt="Danantara Indonesia" class="report-logo danantara">
                    <h2 class="pumk-report-title pumk-bri-report-title">Dashboard Program PUMK BRI</h2>
                    <img src="{{ asset('images/logo/inka.png') }}" alt="PT INKA" class="report-logo inka">
                </header>
                <div class="pumk-bri-filter-bar">
                    <form method="GET" action="{{ route('home') }}#dashboard-pumk-bri" class="pumk-year-form">
                        <label for="pumk-dashboard-year">Tahun</label>
                        <select
                            id="pumk-dashboard-year"
                            name="pumk_year"
                            class="pumk-year-select"
                            onchange="this.form.submit()"
                        >
                            @forelse($pumkBriDashboard['years'] as $year)
                                <option value="{{ $year }}" @selected($year === $briYear)>{{ $year }}</option>
                            @empty
                                <option value="{{ $briYear }}">{{ $briYear }}</option>
                            @endforelse
                        </select>
                    </form>
                </div>
                @if(! $briHasSnapshot)
                    <p class="pumk-bri-validation-note">
                        Snapshot PUMK BRI untuk tahun {{ $briYear }} belum tersedia. RKA dan realisasi tetap mengikuti input resmi Admin PUMK.
                    </p>
                @elseif($pumkBriDashboard['verifikasi']['snapshot_belum_terverifikasi'] > 0 || $pumkBriDashboard['verifikasi']['identitas_menunggu_review'] > 0)
                    <p class="pumk-bri-validation-note">
                        Sebagian data {{ $briYear }} masih menunggu validasi identitas dari sumber:
                        {{ number_format($pumkBriDashboard['verifikasi']['snapshot_belum_terverifikasi'], 0, ',', '.') }} snapshot legacy dan
                        {{ number_format($pumkBriDashboard['verifikasi']['identitas_menunggu_review'], 0, ',', '.') }} kasus profil ambigu.
                        Total Mitra Binaan belum dianggap final sampai validasi selesai.
                        @unless($pumkBriDashboard['verifikasi']['breakdown_terverifikasi'])
                            Rincian sektor, kualitas, dan wilayah pada snapshot terakhir juga masih bersifat sementara.
                        @endunless
                    </p>
                @endif
                <div class="pumk-bri-summary" aria-label="Ringkasan Program PUMK BRI">
                    <article class="pumk-bri-summary-card">
                        <span>RKA Penyaluran {{ $briYear }}</span>
                        @if($briHasRka)
                            <strong>{{ $formatRupiah($pumkBriDashboard['ringkasan']['rka']) }}</strong>
                        @else
                            <strong class="neutral">Belum tersedia</strong>
                            <small>RKA tahunan belum diinput Admin PUMK.</small>
                        @endif
                    </article>
                    <article class="pumk-bri-summary-card">
                        <span>Realisasi s/d Desember {{ $briYear }}</span>
                        @if($briHasRealisasi)
                            <strong>{{ $formatRupiah($pumkBriDashboard['ringkasan']['realisasi']) }}</strong>
                            <small>Akumulasi input realisasi bulanan Admin PUMK.</small>
                        @else
                            <strong class="neutral">Belum tersedia</strong>
                            <small>Belum ada sumber realisasi untuk tahun ini.</small>
                        @endif
                    </article>
                    <article class="pumk-bri-summary-card">
                        <span>Jumlah Outstanding</span>
                        @if($briHasSnapshot)
                            <strong class="neutral">{{ $formatRupiah($pumkBriDashboard['ringkasan']['outstanding']) }}</strong>
                        @else
                            <strong class="neutral">Belum tersedia</strong>
                            <small>Menunggu snapshot bulan pertama.</small>
                        @endif
                    </article>
                    <article class="pumk-bri-summary-card">
                        <span>{{ ! $briHasSnapshot ? 'Total Mitra Binaan' : ($pumkBriDashboard['verifikasi']['identitas_final'] ? 'Total Mitra Binaan' : 'Total Mitra Binaan (sementara)') }}</span>
                        @if($briHasSnapshot)
                            <strong class="neutral">{{ number_format($pumkBriDashboard['ringkasan']['jumlah_mitra'], 0, ',', '.') }}</strong>
                        @else
                            <strong class="neutral">Belum tersedia</strong>
                        @endif
                        @unless($pumkBriDashboard['verifikasi']['identitas_final'])
                            <small>Menunggu validasi identitas sumber.</small>
                        @endunless
                    </article>
                </div>

                <div class="pumk-bri-primary">
                    <article class="pumk-bri-card">
                        <h4 class="pumk-bri-card-title">Progres Penyaluran</h4>
                        <div class="pumk-bri-gauge">
                            <svg viewBox="0 0 220 120" aria-hidden="true">
                                <path d="M20 110 A90 90 0 0 1 200 110" fill="none" stroke="#dbe4f0" stroke-width="29" pathLength="100" />
                                <path
                                    d="M20 110 A90 90 0 0 1 200 110"
                                    fill="none"
                                    stroke="#243b7a"
                                    stroke-width="29"
                                    pathLength="100"
                                    style="stroke-dasharray:{{ $pumkBriDashboard['ringkasan']['progres'] ?? 0 }} 100;"
                                />
                            </svg>
                            <div class="pumk-bri-gauge-center">
                                <span>Progres</span>
                                <strong>{{ $pumkBriDashboard['ringkasan']['progres'] === null ? 'Belum tersedia' : number_format($pumkBriDashboard['ringkasan']['progres'], 2, ',', '.').'%' }}</strong>
                            </div>
                        </div>
                        <div class="pumk-bri-gauge-scale"><span>0%</span><span>100%</span></div>
                        @if(! $briHasRka)
                            <p class="pumk-rka-note">Progres akan dihitung otomatis setelah nilai RKA penyaluran tersedia.</p>
                        @endif
                    </article>

                    <article class="pumk-bri-card">
                        <h4 class="pumk-bri-card-title">Sektor Ekonomi</h4>
                        <div class="pumk-bri-chart">
                            <canvas id="pumk-portfolio-chart" aria-label="Diagram outstanding berdasarkan sektor ekonomi"></canvas>
                            @if($pumkBriDashboard['sektor']->isEmpty())
                                <p class="pumk-bri-empty">Belum ada data sektor untuk periode ini.</p>
                            @endif
                        </div>
                    </article>

                    <article class="pumk-bri-card">
                        <h4 class="pumk-bri-card-title">Kualitas Piutang Mitra Binaan</h4>
                        <div class="pumk-bri-chart">
                            <canvas id="pumk-quality-chart" aria-label="Diagram kualitas piutang mitra binaan"></canvas>
                            @if($pumkBriDashboard['kualitas']->sum('nilai') <= 0)
                                <p class="pumk-bri-empty">Belum ada data kualitas piutang untuk periode ini.</p>
                            @endif
                        </div>
                    </article>
                </div>

                <div class="pumk-bri-lower">
                    <article class="pumk-bri-card">
                        <h4 class="pumk-bri-card-title">Sebaran Penyaluran Dana PUMK (BRI)</h4>
                        <div id="pumk-bri-map" class="pumk-bri-map" aria-label="Peta sebaran penyaluran PUMK BRI">
                            <div class="pumk-map-state" data-pumk-map-state>
                                {{ $pumkBriDashboard['peta']['available']
                                    ? 'Memuat peta wilayah...'
                                    : 'Belum tersedia data snapshot untuk tahun ini.' }}
                            </div>
                        </div>
                        <div class="pumk-map-card-footer">
                            <span data-pumk-map-summary>{{ $pumkBriDashboard['wilayah']->count() }} wilayah pada snapshot terakhir</span>
                            <div class="pumk-map-actions">
                                <button type="button" class="pumk-map-reset" data-pumk-map-reset hidden>Tampilkan semua</button>
                                <button
                                    type="button"
                                    class="pumk-map-toggle"
                                    data-pumk-map-toggle
                                    @disabled($pumkBriDashboard['wilayah']->isEmpty())
                                >Lihat data</button>
                            </div>
                        </div>
                        <p class="pumk-map-caption">
                            Peta menggunakan posisi snapshot terbaru
                            @if($pumkBriDashboard['peta']['bulan_snapshot_label'])
                                ({{ $pumkBriDashboard['peta']['bulan_snapshot_label'] }} {{ $briYear }}).
                            @else
                                pada tahun terpilih.
                            @endif
                            Klik wilayah untuk menyaring tabel detail.
                            @if($pumkBriDashboard['peta']['unmapped_wilayah'] > 0)
                                <span class="pumk-map-unmapped">
                                    {{ $pumkBriDashboard['peta']['unmapped_wilayah'] }} wilayah belum memiliki pasangan geometri dan tetap tercatat di tabel.
                                </span>
                            @endif
                        </p>
                    </article>

                    <article class="pumk-bri-card">
                        <h4 class="pumk-bri-card-title">
                            Outstanding Piutang Bulanan — {{ $briYear }}
                        </h4>
                        <div class="pumk-bri-chart">
                            <canvas id="pumk-outstanding-month-chart" aria-label="Grafik outstanding PUMK BRI setiap bulan"></canvas>
                            @if($pumkBriDashboard['tren_outstanding']->isEmpty())
                                <p class="pumk-bri-empty">Belum ada data outstanding bulanan untuk tahun ini.</p>
                            @endif
                        </div>
                    </article>
                </div>

                <div class="pumk-bri-data-grid" id="pumk-bri-data-mitra">
                    <article class="pumk-bri-card">
                        <h4 class="pumk-bri-card-title">Data Mitra</h4>
                        <p class="pumk-region-filter-note" data-pumk-region-filter-note hidden></p>
                        <div class="pumk-map-table-wrap">
                            <table class="pumk-map-table">
                                <thead>
                                    <tr>
                                        <th scope="col">No</th>
                                        <th scope="col">Kota/Kabupaten</th>
                                        <th scope="col">Mitra unik di wilayah</th>
                                        <th scope="col">Fasilitas</th>
                                        <th scope="col">Jumlah Penyaluran</th>
                                        <th scope="col">Jumlah Outstanding</th>
                                        <th scope="col">L</th>
                                        <th scope="col">KL</th>
                                        <th scope="col">D</th>
                                        <th scope="col">M</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pumkBriDashboard['wilayah'] as $wilayah)
                                        <tr data-pumk-region-row data-region-key="{{ $wilayah['geo_key'] }}">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $wilayah['nama'] }}</td>
                                            <td>{{ number_format($wilayah['jumlah_mitra'], 0, ',', '.') }}</td>
                                            <td>{{ number_format($wilayah['jumlah_fasilitas'], 0, ',', '.') }}</td>
                                            <td>{{ $formatRupiah($wilayah['jumlah_penyaluran']) }}</td>
                                            <td>{{ $formatRupiah($wilayah['outstanding']) }}</td>
                                            <td>{{ number_format($wilayah['lancar'], 0, ',', '.') }}</td>
                                            <td>{{ number_format($wilayah['kurang_lancar'], 0, ',', '.') }}</td>
                                            <td>{{ number_format($wilayah['diragukan'], 0, ',', '.') }}</td>
                                            <td>{{ number_format($wilayah['macet'], 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="10">Belum ada data wilayah untuk periode ini.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($pumkBriDashboard['ketersediaan']['wilayah_mitra_dapat_berulang'])
                            <p class="pumk-region-note">Jumlah Mitra per wilayah tidak dijumlahkan karena satu Mitra dapat memiliki fasilitas pada lebih dari satu wilayah.</p>
                        @endif
                    </article>

                    <article class="pumk-bri-card pumk-rka-panel">
                        <h4 class="pumk-bri-card-title">Input RKA Penyaluran</h4>
                        <div class="pumk-rka-list">
                            @foreach($pumkBriDashboard['rka_bulanan'] as $rkaBulan)
                                <div class="pumk-rka-row">
                                    <span>Penyaluran {{ $rkaBulan['label'] }}</span>
                                    <strong>{{ $rkaBulan['nilai'] === null ? 'Belum diinput' : $formatRupiah($rkaBulan['nilai']) }}</strong>
                                </div>
                            @endforeach
                        </div>
                        <p class="pumk-rka-empty-note">Nilai berasal dari input Admin PUMK, bukan dari tanggal pada workbook snapshot.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="pumk-section pumk-live-section">
        <div class="container-site">
            <h2 class="pumk-heading">Dashboard PUMK PT. INKA (Persero)</h2>

            <div class="pumk-report-frame pumk-live-frame">
                <header class="pumk-report-header">
                    <img src="{{ asset('images/logo/danantara.png') }}" alt="Danantara Indonesia" class="report-logo danantara">
                    <h3 class="pumk-report-title">Program PUMK PT. INKA (Persero)</h3>
                    <img src="{{ asset('images/logo/inka.png') }}" alt="PT INKA" class="report-logo inka">
                </header>

                <div class="pumk-meta">
                    <span>Update data: {{ $liveUpdatedAt->locale('id')->translatedFormat('d F Y H.i') }}</span>
                    <span>Sumber: database Kartu Piutang PUMK</span>
                </div>

                <div class="pumk-live-summary">
                    <article class="pumk-total-card"><span>Saldo Piutang Pinjaman (Pokok)</span><strong>{{ $formatRupiah($pumkLiveDashboard['saldo_pokok']) }}</strong></article>
                    <article class="pumk-total-card"><span>Saldo Piutang Pinjaman (Bunga)</span><strong>{{ $formatRupiah($pumkLiveDashboard['saldo_bunga']) }}</strong></article>
                    <article class="pumk-total-card"><span>Total Saldo Piutang Pinjaman</span><strong>{{ $formatRupiah($pumkLiveDashboard['total_saldo_piutang']) }}</strong></article>
                    <article class="pumk-total-card"><span>Total Binaan</span><strong>{{ number_format($pumkLiveDashboard['total_binaan'], 0, ',', '.') }}</strong></article>
                </div>

                <div class="pumk-primary-grid" style="grid-template-columns:1fr 1fr">
                    <article class="pumk-card">
                        <h4 class="pumk-card-title">Sektor Ekonomi Portofolio Mitra Binaan</h4>
                        <div class="pumk-chart">
                            <canvas id="pumk-live-sector-chart" aria-label="Distribusi pinjaman berdasarkan sektor"></canvas>
                            @if($pumkLiveDashboard['sektor']->isEmpty())
                                <p class="pumk-empty-note">Belum ada pinjaman aktif untuk ditampilkan.</p>
                            @endif
                        </div>
                    </article>
                    <article class="pumk-card">
                        <h4 class="pumk-card-title">Kualitas Piutang Mitra Binaan</h4>
                        <div class="pumk-chart">
                            <canvas id="pumk-live-quality-chart" aria-label="Distribusi piutang berdasarkan kolektibilitas"></canvas>
                            @if($pumkLiveDashboard['kolektibilitas']->isEmpty())
                                <p class="pumk-empty-note">Belum ada kolektibilitas aktif untuk ditampilkan.</p>
                            @endif
                        </div>
                    </article>
                </div>

                <div class="pumk-secondary-grid">
                    <article class="pumk-card">
                        <h4 class="pumk-card-title">Sebaran PUMK per Provinsi</h4>
                        <div class="pumk-wide-chart">
                            <canvas id="pumk-live-province-chart" aria-label="Sebaran saldo piutang PUMK per provinsi"></canvas>
                            @if($pumkLiveDashboard['sebaran_provinsi']->isEmpty())
                                <p class="pumk-empty-note">Belum ada data wilayah untuk ditampilkan.</p>
                            @endif
                        </div>
                    </article>
                    <article class="pumk-card">
                        <h4 class="pumk-card-title">Tren Saldo Piutang Berdasarkan Kolektibilitas</h4>
                        <div class="pumk-wide-chart">
                            <canvas id="pumk-live-quality-trend-chart" aria-label="Tren snapshot piutang per kolektibilitas"></canvas>
                            @if($pumkLiveDashboard['tren_kolektibilitas']['datasets'] === [])
                                <p class="pumk-empty-note">Riwayat sebelum fitur snapshot dibuat memang belum tersedia.</p>
                            @endif
                        </div>
                    </article>
                </div>
                <p class="pumk-source-note">Saldo pokok, bunga, dan total saldo piutang berasal dari posisi Kartu Piutang PUMK terbaru.</p>
            </div>
        </div>
    </section>

    <section class="faq-section">
        <div class="container-site">
            <h2 class="faq-heading">Frequently Asked Questions (FAQ)</h2>
            <x-faq-accordion :faqs="$faqs" />
        </div>
    </section>
</x-layouts.app>
