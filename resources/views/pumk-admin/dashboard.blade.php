<x-layouts.pumk-admin title="Dashboard Admin PUMK">
    @php
        $statusCards = [
            ['key' => 'lancar', 'label' => 'Lancar', 'color' => '#16a34a'],
            ['key' => 'kurang_lancar', 'label' => 'Kurang Lancar', 'color' => '#eab308'],
            ['key' => 'diragukan', 'label' => 'Diragukan', 'color' => '#f97316'],
            ['key' => 'macet', 'label' => 'Macet', 'color' => '#dc2626'],
        ];
    @endphp

    <style>
        .dashboard-heading{margin-bottom:28px}.dashboard-heading h1{margin:0;color:#09132a;font-size:clamp(32px,4vw,48px);line-height:1.2;font-weight:700}.dashboard-heading p{margin:8px 0 0;color:#64748b}
        .summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:18px}.summary-card{min-height:145px;padding:22px;border-radius:10px;background:#fff;box-shadow:0 3px 5px rgba(0,0,0,.16)}.summary-card .label{color:#475569;font-size:15px;font-weight:600}.summary-card strong{display:block;margin-top:25px;color:#09132a;font-size:38px}.summary-card.attention{background:#64748b;color:#fff}.summary-card.attention .label,.summary-card.attention strong{color:#fff}
        .section-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:38px 0 16px}.section-heading h2{margin:0;font-size:28px;font-weight:600}.section-actions{display:flex;gap:10px}
        .status-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.status-card{min-height:124px;border-radius:9px;padding:18px;color:#fff;box-shadow:0 3px 5px rgba(0,0,0,.18)}.status-card span{font-size:14px;font-weight:600}.status-card strong{display:block;margin-top:20px;font-size:32px}
        .dashboard-help{margin-top:28px;padding:22px}.dashboard-help h3{margin:0 0 8px;font-size:18px}.dashboard-help p{margin:0;color:#64748b;line-height:1.7}
        @media(max-width:1150px){.summary-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:900px){.summary-grid,.status-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.summary-grid,.status-grid{grid-template-columns:1fr}.section-heading{align-items:flex-start;flex-direction:column}.section-actions{width:100%;flex-direction:column}.section-actions a{width:100%}}
    </style>

    <div class="pumk-page">
        <div class="dashboard-heading">
            <h1>Selamat datang di dashboard Admin PUMK</h1>
            <p>Kelola data mitra binaan dan kartu piutang pada satu tempat.</p>
        </div>

        <section class="summary-grid" aria-label="Ringkasan data PUMK">
            <article class="summary-card">
                <span class="label">Mitra Binaan Aktif</span>
                <strong>{{ number_format($totalMitra, 0, ',', '.') }}</strong>
            </article>
            <article class="summary-card">
                <span class="label">Pinjaman Aktif</span>
                <strong>{{ number_format($totalPinjaman, 0, ',', '.') }}</strong>
            </article>
            <article class="summary-card">
                <span class="label">Mitra Lunas</span>
                <strong>{{ number_format($totalMitraLunas, 0, ',', '.') }}</strong>
            </article>
            <article class="summary-card">
                <span class="label">Pinjaman Lunas</span>
                <strong>{{ number_format($totalPinjamanLunas, 0, ',', '.') }}</strong>
            </article>
            <article class="summary-card attention">
                <span class="label">Data Perlu Dilengkapi</span>
                <strong>{{ number_format($dataBelumLengkap, 0, ',', '.') }}</strong>
            </article>
        </section>

        <div class="section-heading">
            <h2>Kolektibilitas Pinjaman</h2>
            <div class="section-actions">
                <a href="{{ route('pumk-admin.mitra.index') }}" class="pumk-secondary-button">Lihat Daftar Mitra</a>
                <a href="{{ route('pumk-admin.mitra.create') }}" class="pumk-primary-button">Tambah Mitra Binaan</a>
            </div>
        </div>

        <section class="status-grid" aria-label="Ringkasan kolektibilitas">
            @foreach($statusCards as $card)
                <a href="{{ route('pumk-admin.mitra.index', ['kolektibilitas' => $card['key']]) }}" class="status-card" style="background:{{ $card['color'] }};text-decoration:none">
                    <span>{{ $card['label'] }}</span>
                    <strong>{{ number_format((int) ($collectibility[$card['key']] ?? 0), 0, ',', '.') }}</strong>
                </a>
            @endforeach
        </section>

        <section class="pumk-card dashboard-help">
            <h3>Catatan pengelolaan</h3>
            <p>Nilai sisa pinjaman dan kolektibilitas dihitung oleh sistem dari saldo awal dan riwayat angsuran. Data identitas sensitif hanya tersedia di halaman detail yang dilindungi akun Admin PUMK.</p>
        </section>
    </div>
</x-layouts.pumk-admin>
