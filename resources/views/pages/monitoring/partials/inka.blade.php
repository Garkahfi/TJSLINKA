    @if($dashboardType === 'inka')
    <section class="pumk-section pumk-live-section">
        <div class="container-site">
            <h2 class="pumk-heading">Dashboard PUMK PT. INKA (Persero)</h2>

            <div class="pumk-report-frame pumk-live-frame">
                <header class="pumk-report-header">
                    <img src="{{ asset('images/logo/danantara.png') }}" alt="Danantara Indonesia" class="report-logo danantara">
                    <h3 class="pumk-report-title">Program PUMK PT. INKA (Persero)</h3>
                    <img src="{{ asset('images/logo/inka.png') }}" alt="PT INKA" class="report-logo inka">
                </header>

                <form method="GET" action="{{ route('monitoring.inka') }}" class="monitoring-year-bar">
                    <label for="pumk-inka-year">Tahun laporan</label>
                    <select id="pumk-inka-year" name="year">
                        @if($pumkLiveDashboard['year'] !== null && ! in_array($pumkLiveDashboard['year'], $pumkLiveDashboard['years'], true))
                            <option value="{{ $pumkLiveDashboard['year'] }}" selected>{{ $pumkLiveDashboard['year'] }} (belum tersedia)</option>
                        @endif
                        @forelse($pumkLiveDashboard['years'] as $year)
                            <option value="{{ $year }}" @selected($year === $pumkLiveDashboard['year'])>{{ $year }}</option>
                        @empty
                            <option value="">Belum ada tahun data</option>
                        @endforelse
                    </select>
                    <button type="submit">Tampilkan</button>
                </form>

                <div class="pumk-meta">
                    <span>Tahun {{ $pumkLiveDashboard['year'] ?? '—' }} · {{ $pumkLiveDashboard['as_of_date'] ? 'Posisi saldo per '.\Illuminate\Support\Carbon::parse($pumkLiveDashboard['as_of_date'])->locale('id')->translatedFormat('d F Y') : 'Data untuk periode ini belum tersedia' }}</span>
                    <span>Pembaruan terakhir: {{ $liveUpdatedAt ? $liveUpdatedAt->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y H.i').' WIB' : 'Belum tersedia' }}</span>
                </div>
                <div class="pumk-meta">
                    <span>Sumber: database Kartu Piutang PUMK</span>
                </div>

                @if($pumkLiveDashboard['status'] === 'unavailable')
                    <p class="monitoring-data-note">Data untuk periode ini belum tersedia. Tahun jadwal angsuran yang belum berjalan tidak dihitung sebagai realisasi.</p>
                @elseif($pumkLiveDashboard['status'] === 'partial')
                    <p class="monitoring-data-note">Data periode ini belum lengkap: {{ $pumkLiveDashboard['known_loans'] }} pinjaman dapat dihitung, {{ $pumkLiveDashboard['unknown_loans'] }} belum memiliki dasar saldo yang cukup. Angka yang tampil adalah subtotal, bukan total portofolio final.</p>
                @endif
                @if($pumkLiveDashboard['carried_from_previous_year'])
                    <p class="monitoring-data-note">Saldo dibawa dari posisi terakhir {{ \Illuminate\Support\Carbon::parse($pumkLiveDashboard['as_of_date'])->locale('id')->translatedFormat('d F Y') }}; belum ada angsuran tercatat pada {{ $pumkLiveDashboard['year'] }}.</p>
                @endif
                @if($pumkLiveDashboard['status'] === 'available' && $pumkLiveDashboard['payment_count'] === 0)
                    <p class="monitoring-data-note">Belum ada angsuran tercatat pada {{ $pumkLiveDashboard['year'] }}. Posisi saldo mengikuti bukti kontrak atau baseline yang tersedia.</p>
                @endif
                @if($pumkLiveDashboard['classification_limited'])
                    <p class="monitoring-data-note">Sebagian sektor, wilayah, atau kolektibilitas historis belum terverifikasi dan dikelompokkan sebagai belum terverifikasi/belum dinilai.</p>
                @endif
                @if($pumkLiveDashboard['snapshot_stale'])
                    <p class="monitoring-data-note">Snapshot periode ini perlu direkonsiliasi setelah perubahan sumber atau penutupan pinjaman. Angka saldo dibaca ulang dari sumber, tetapi revisi snapshot belum dicatat.</p>
                @endif
                @if($pumkLiveDashboard['negative_loans'] > 0)
                    <p class="monitoring-data-note">{{ $pumkLiveDashboard['negative_loans'] }} saldo negatif terbuka ({{ $formatRupiah($pumkLiveDashboard['negative_total']) }}) ditampilkan terpisah untuk pemeriksaan kelebihan bayar. Nilainya tidak mengurangi piutang aktif positif dan tidak dimasukkan sebagai irisan grafik.</p>
                @elseif($pumkLiveDashboard['total_saldo_piutang'] === 0.0)
                    <p class="monitoring-data-note">
                        @if($pumkLiveDashboard['closed_loans'] > 0 && $pumkLiveDashboard['known_loans'] === 0 && $pumkLiveDashboard['unknown_loans'] === 0)
                            Semua pinjaman yang tercakup pada posisi ini telah ditutup; piutang aktif sah bernilai nol.
                        @else
                            Saldo piutang pada posisi ini sah bernilai nol; tidak ada saldo untuk didistribusikan pada grafik.
                        @endif
                    </p>
                @endif
                @if(\Illuminate\Support\Facades\Auth::guard('pumk')->check() || \Illuminate\Support\Facades\Auth::guard('superadmin')->check())
                    <p class="monitoring-data-note">
                        <a class="font-semibold underline" href="{{ \Illuminate\Support\Facades\Auth::guard('superadmin')->check() ? route('superadmin.pumk.diagnostics', ['year' => $pumkLiveDashboard['year']]) : route('pumk-admin.monitoring.diagnostics', ['year' => $pumkLiveDashboard['year']]) }}">Lihat rincian data yang perlu diperiksa</a>
                    </p>
                @endif

                <div class="pumk-live-summary">
                    <article class="pumk-total-card"><span>Saldo Piutang Aktif (Pokok)</span><strong>{{ $formatRupiah($pumkLiveDashboard['saldo_pokok']) }}</strong></article>
                    <article class="pumk-total-card"><span>Saldo Piutang Aktif (Bunga)</span><strong>{{ $formatRupiah($pumkLiveDashboard['saldo_bunga']) }}</strong></article>
                    <article class="pumk-total-card"><span>Total Piutang Aktif Positif{{ $pumkLiveDashboard['status'] === 'partial' ? ' (Subtotal)' : '' }}</span><strong>{{ $formatRupiah($pumkLiveDashboard['total_saldo_piutang']) }}</strong></article>
                    <article class="pumk-total-card"><span>Total Binaan dengan Piutang Aktif</span><strong>{{ $pumkLiveDashboard['total_binaan'] === null ? 'Belum tersedia' : number_format($pumkLiveDashboard['total_binaan'], 0, ',', '.') }}</strong></article>
                </div>

                <div class="pumk-primary-grid" style="grid-template-columns:1fr 1fr">
                    <article class="pumk-card">
                        <h4 class="pumk-card-title">Sektor Ekonomi Portofolio Mitra Binaan</h4>
                        <div class="pumk-chart">
                            <canvas id="pumk-live-sector-chart" aria-label="Distribusi pinjaman berdasarkan sektor"></canvas>
                            @if($pumkLiveDashboard['sektor']->isEmpty())
                                <p class="pumk-empty-note">Data sektor untuk periode ini belum tersedia.</p>
                            @endif
                        </div>
                    </article>
                    <article class="pumk-card">
                        <h4 class="pumk-card-title">Kualitas Piutang Mitra Binaan</h4>
                        <div class="pumk-chart">
                            <canvas id="pumk-live-quality-chart" aria-label="Distribusi piutang berdasarkan kolektibilitas"></canvas>
                            @if($pumkLiveDashboard['kolektibilitas']->isEmpty())
                                <p class="pumk-empty-note">Data kolektibilitas untuk periode ini belum tersedia.</p>
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
                                <p class="pumk-empty-note">Data wilayah untuk periode ini belum tersedia.</p>
                            @endif
                        </div>
                    </article>
                    <article class="pumk-card">
                        <h4 class="pumk-card-title">Tren Saldo Piutang Berdasarkan Kolektibilitas</h4>
                        <div class="pumk-wide-chart">
                            <canvas id="pumk-live-quality-trend-chart" aria-label="Tren snapshot piutang per kolektibilitas"></canvas>
                            @if($pumkLiveDashboard['tren_kolektibilitas']['datasets'] === [])
                                <p class="pumk-empty-note">Tren hanya menampilkan bulan dengan posisi yang dapat dihitung.</p>
                            @endif
                        </div>
                    </article>
                </div>
                <p class="pumk-source-note">Saldo pokok, bunga, dan total dihitung untuk tanggal posisi yang sama dari Kartu Piutang. Bulan tanpa bukti tidak diisi nol.</p>
            </div>
        </div>
    </section>
    @endif
