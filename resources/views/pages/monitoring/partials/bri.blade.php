    @if($dashboardType === 'bri')
    <section id="dashboard-pumk-bri" class="pumk-section">
        <div class="container-site">
            <div class="pumk-report-frame pumk-bri-frame">
                <header class="pumk-report-header">
                    <img src="{{ asset('images/logo/danantara.png') }}" alt="Danantara Indonesia" class="report-logo danantara">
                    <h2 class="pumk-report-title pumk-bri-report-title">Dashboard Program PUMK BRI</h2>
                    <img src="{{ asset('images/logo/inka.png') }}" alt="PT INKA" class="report-logo inka">
                </header>
                <div class="pumk-meta">
                    <span>Periode data: {{ $briHasSnapshot ? ($pumkBriDashboard['latest_month_label'].' '.$briYear) : 'Belum tersedia' }}</span>
                    <span>Pembaruan terakhir: {{ $briUpdatedAt ? $briUpdatedAt->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y H.i').' WIB' : 'Belum tersedia' }}</span>
                </div>
                <div class="pumk-bri-filter-bar">
                    <form method="GET" action="{{ route('monitoring.bri') }}" class="pumk-year-form">
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
                                <path d="M20 110 A90 90 0 0 1 200 110" fill="none" stroke="#E2E8F0" stroke-width="29" pathLength="100" />
                                <path
                                    d="M20 110 A90 90 0 0 1 200 110"
                                    fill="none"
                                    stroke="#2563EB"
                                    stroke-width="29"
                                    pathLength="100"
                                    @style(['stroke-dasharray:'.($pumkBriDashboard['ringkasan']['progres'] ?? 0).' 100'])
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
    @endif
