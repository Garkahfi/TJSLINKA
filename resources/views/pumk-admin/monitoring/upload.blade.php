<x-layouts.pumk-admin title="Upload Data Monitoring">
    @php($summary = session('import_summary', []))

    <section class="pumk-page monitoring-upload-page">
        <header class="monitoring-page-header">
            <div>
                <h1 class="pumk-page-title">Upload Data Monitoring</h1>
                <p class="pumk-page-subtitle">Perbarui data dashboard TJSL dan PUMK BRI dari satu workbook Excel.</p>
            </div>
        </header>

        @if(session('success'))
            <div class="pumk-alert success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="pumk-alert error" role="alert">
                <strong>File belum dapat diproses.</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <article class="pumk-card monitoring-upload-card">
            <form method="POST" action="{{ route('pumk-admin.monitoring.upload.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="monitoring_file" class="monitoring-upload-label">File Excel monitoring</label>
                <div class="monitoring-file-row">
                    <input
                        id="monitoring_file"
                        name="monitoring_file"
                        type="file"
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        required
                    >
                    <button type="submit" class="pumk-primary-button">Upload dan Proses</button>
                </div>
                <p class="monitoring-help">Format .xlsx, maksimal 10 MB. Sheet yang tidak tersedia akan dilewati tanpa menghapus data lama.</p>
            </form>
        </article>

        @if($summary !== [])
            <article class="pumk-card monitoring-summary-card">
                <h2>Ringkasan hasil import</h2>
                <div class="monitoring-table-wrap">
                    <table class="monitoring-table">
                        <thead>
                        <tr>
                            <th>Sheet</th>
                            <th>Status</th>
                            <th>Berhasil</th>
                            <th>Gagal</th>
                            <th>Keterangan</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($summary as $sheet => $result)
                            <tr>
                                <td><strong>{{ $sheet }}</strong></td>
                                <td><span class="monitoring-status {{ $result['status'] }}">{{ strtoupper($result['status']) }}</span></td>
                                <td>{{ $result['berhasil'] }}</td>
                                <td>{{ $result['gagal'] }}</td>
                                <td>
                                    {{ $result['pesan'] }}
                                    @if($result['errors'] !== [])
                                        <details>
                                            <summary>Lihat detail error</summary>
                                            <ul>
                                                @foreach($result['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        @endif

    </section>

    <style>
        .monitoring-page-header{display:flex;justify-content:space-between;gap:20px;margin-bottom:24px}.monitoring-upload-card,.monitoring-summary-card{margin-bottom:20px;padding:24px}.monitoring-upload-label{display:block;margin-bottom:10px;color:#0f172a;font-size:14px;font-weight:600}.monitoring-file-row{display:flex;align-items:center;gap:14px}.monitoring-file-row input{min-width:0;flex:1;border:1px solid #cbd5e1;border-radius:7px;padding:9px;background:#f8fafc}.monitoring-help{margin:10px 0 0;color:#64748b;font-size:12px}.monitoring-summary-card h2{margin:0 0 14px;color:#0f172a;font-size:20px}.monitoring-table-wrap{overflow-x:auto}.monitoring-table{width:100%;border-collapse:collapse;font-size:13px}.monitoring-table th,.monitoring-table td{border-bottom:1px solid #e2e8f0;padding:11px 10px;text-align:left;vertical-align:top}.monitoring-table th{background:#0f1d3a;color:#fff}.monitoring-table details{margin-top:7px;color:#991b1b}.monitoring-table ul,.pumk-alert ul{margin:7px 0 0;padding-left:20px}.monitoring-status{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:10px;font-weight:700}.monitoring-status.success{background:#dcfce7;color:#166534}.monitoring-status.partial{background:#fef3c7;color:#92400e}.monitoring-status.failed{background:#fee2e2;color:#991b1b}.monitoring-status.skipped{background:#e2e8f0;color:#475569}@media(max-width:700px){.monitoring-file-row{align-items:stretch;flex-direction:column}}
    </style>
</x-layouts.pumk-admin>
