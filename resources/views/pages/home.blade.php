@php
    $perPilar = $perPilar ?? collect();
    $perWilayah = $perWilayah ?? collect();
    $bidangPrioritas = $bidangPrioritas ?? collect();
    $tpbDashboard = $tpbDashboard ?? collect();
    $pumkBriDashboard = $pumkBriDashboard ?? [];
    $pumkLiveDashboard = $pumkLiveDashboard ?? [];
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

    $formatRupiah = fn ($value) => $value === null ? 'Belum tersedia' : 'Rp'.number_format((float) $value, 0, ',', '.');
    $briYear = (int) ($pumkBriDashboard['year'] ?? now()->year);
    $briMonth = (int) ($pumkBriDashboard['latest_month'] ?? 0);
    $briHasSnapshot = (bool) ($pumkBriDashboard['ketersediaan']['snapshot'] ?? false);
    $briHasRka = (bool) ($pumkBriDashboard['ketersediaan']['rka'] ?? false);
    $briHasRealisasi = (bool) ($pumkBriDashboard['ketersediaan']['realisasi'] ?? false);
    $briUpdatedAt = filled($pumkBriDashboard['updated_at'] ?? null)
        ? \Illuminate\Support\Carbon::parse($pumkBriDashboard['updated_at'])
        : null;
    $liveUpdatedAt = filled($pumkLiveDashboard['updated_at'] ?? null)
        ? \Illuminate\Support\Carbon::parse($pumkLiveDashboard['updated_at'])
        : null;
@endphp

@include('pages.monitoring.partials.head')

@include('pages.monitoring.partials.scripts')

<x-layouts.app title="Home — LENSA TJSL INKA">
    <template
        id="home-dashboard-data"
        @if($dashboardType === 'tjsl')
        data-per-pilar="{{ $perPilarChart->toJson() }}"
        data-per-wilayah="{{ $perWilayahMap->toJson() }}"
        data-bidang-prioritas="{{ $bidangPrioritasChart->toJson() }}"
        data-per-tpb="{{ $tpbChart->toJson() }}"
        @elseif($dashboardType === 'bri')
        data-pumk-bri="{{ collect($pumkBriDashboard)->except('updated_at')->toJson() }}"
        @elseif($dashboardType === 'inka')
        data-pumk-live="{{ collect($pumkLiveDashboard)->except('updated_at')->toJson() }}"
        @endif
    ></template>
    @include('pages.monitoring.partials.tjsl')

    @include('pages.monitoring.partials.bri')

    @include('pages.monitoring.partials.inka')

    <section class="faq-section">
        <div class="container-site">
            <h2 class="faq-heading">Frequently Asked Questions (FAQ)</h2>
            <x-faq-accordion :faqs="$faqs" />
        </div>
    </section>
</x-layouts.app>
