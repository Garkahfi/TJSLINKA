    @if($dashboardType === 'tjsl')
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
                        Realisasi Anggaran Program TJSL
                    </h2>
                    <img
                        src="{{ asset('images/logo/inka.png') }}"
                        alt="PT INKA"
                        class="report-logo inka"
                    >
                </header>

                <div class="report-subhead">
                    <span>Pembaruan terakhir: {{ $dashboardUpdatedAt ? $dashboardUpdatedAt->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y H.i').' WIB' : 'Belum tersedia' }}</span>
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
    @endif
