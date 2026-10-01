<x-layouts.pumk-admin title="Upload Data Monitoring">
    @php($result = session('import_result'))
    @php($isBriResult = ($result['type'] ?? null) === 'pumk_bri_snapshot')

    <section class="pumk-page monitoring-upload-page">
        <header class="monitoring-page-header">
            <div>
                <h1 class="pumk-page-title">Upload Data Monitoring</h1>
                <p class="pumk-page-subtitle">Pilih jenis data agar workbook diproses pada dashboard yang sesuai.</p>
            </div>
        </header>

        @if($result)
            <div class="pumk-alert {{ in_array($result['level'], ['success', 'warning', 'error', 'info'], true) ? $result['level'] : 'info' }}" role="status">{{ $result['message'] }}</div>
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
                <label for="import_type" class="monitoring-upload-label">Jenis data yang akan diunggah</label>
                <select id="import_type" name="import_type" required class="monitoring-select">
                    <option value="">Pilih jenis data</option>
                    <option value="tjsl" @selected(old('import_type') === 'tjsl')>Monitoring TJSL</option>
                    <option value="pumk_bri_snapshot" @selected(old('import_type') === 'pumk_bri_snapshot')>Snapshot PUMK BRI</option>
                </select>
                <p id="tjsl_help" class="monitoring-help" @if(old('import_type') !== 'tjsl') hidden @endif>Unggah workbook monitoring TJSL. Hanya sheet TJSL yang akan diproses.</p>
                <p id="bri_help" class="monitoring-help" @if(old('import_type') !== 'pumk_bri_snapshot') hidden @endif>Unggah snapshot saldo piutang BRI per bulan. RKA dan realisasi penyaluran dikelola melalui menu RKA &amp; Realisasi BRI. Unggah daftar lengkap mitra untuk setiap bulan.</p>
                <div id="bri_template" class="monitoring-template" @if(old('import_type') !== 'pumk_bri_snapshot') hidden @endif>
                    <a href="{{ route('pumk-admin.monitoring.template.bri') }}" class="monitoring-template-button">Download Template PUMK BRI</a>
                    <p class="monitoring-help">Unduh template kosong, isi data sesuai petunjuk, lalu unggah kembali.</p>
                </div>
                <div id="default_year_field" class="monitoring-year-field">
                    <label for="default_year" class="monitoring-upload-label">Tahun default (untuk sheet tanpa tahun)</label>
                    <input id="default_year" name="default_year" type="number" min="1900" max="2100" step="1" value="{{ old('default_year', now()->year) }}" class="monitoring-year-input">
                    <p class="monitoring-help">Hanya dipakai untuk Snapshot PUMK BRI. Tahun yang tertulis pada nama sheet tetap berlaku.</p>
                </div>
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

        @if($result && ($result['summary'] ?? []) !== [])
            <article class="pumk-card monitoring-summary-card">
                <h2>Ringkasan {{ $isBriResult ? 'Snapshot PUMK BRI' : 'Monitoring TJSL' }}</h2>
                <div class="monitoring-table-wrap">
                    <table class="monitoring-table">
                        <thead>
                        <tr>
                            <th>Sheet</th>
                            <th>Status</th>
                            @if($isBriResult)
                                <th>Periode</th>
                                <th>Baris</th>
                                <th>Total saldo</th>
                            @else
                                <th>Berhasil</th>
                                <th>Gagal</th>
                            @endif
                            <th>Keterangan</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($result['summary'] as $sheet => $sheetResult)
                            <tr>
                                <td><strong>{{ $sheet }}</strong></td>
                                <td><span class="monitoring-status {{ in_array($sheetResult['status'], ['success', 'partial', 'failed', 'skipped', 'imported', 'ignored', 'needs_review'], true) ? $sheetResult['status'] : 'skipped' }}">{{ strtoupper(str_replace('_', ' ', $sheetResult['status'])) }}</span></td>
                                @if($isBriResult)
                                    <td>{{ $sheetResult['bulan'] && $sheetResult['tahun'] ? sprintf('%02d/%d', $sheetResult['bulan'], $sheetResult['tahun']) : '—' }}</td>
                                    <td>{{ $sheetResult['status'] === 'imported' ? $sheetResult['baris'] : '—' }}</td>
                                    <td>{{ $sheetResult['status'] === 'imported' ? 'Rp'.number_format((float) $sheetResult['total_saldo'], 2, ',', '.') : '—' }}</td>
                                @else
                                    <td>{{ $sheetResult['berhasil'] }}</td>
                                    <td>{{ $sheetResult['gagal'] }}</td>
                                @endif
                                <td>
                                    {{ $sheetResult['pesan'] }}
                                    @if(! $isBriResult && $sheetResult['errors'] !== [])
                                        <details>
                                            <summary>Lihat detail error</summary>
                                            <ul>
                                                @foreach($sheetResult['errors'] as $error)
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

    <script>
        (() => {
            const type = document.getElementById('import_type');
            const yearField = document.getElementById('default_year_field');
            const yearInput = document.getElementById('default_year');
            const tjslHelp = document.getElementById('tjsl_help');
            const briHelp = document.getElementById('bri_help');
            const briTemplate = document.getElementById('bri_template');
            const update = () => {
                const isBri = type.value === 'pumk_bri_snapshot';
                yearField.hidden = !isBri;
                yearInput.required = isBri;
                tjslHelp.hidden = type.value !== 'tjsl';
                briHelp.hidden = !isBri;
                briTemplate.hidden = !isBri;
            };
            type.addEventListener('change', update);
            update();
        })();
    </script>

    <style>
        .monitoring-page-header{display:flex;justify-content:space-between;gap:20px;margin-bottom:24px}.monitoring-upload-card,.monitoring-summary-card{margin-bottom:20px;padding:24px}.monitoring-upload-label{display:block;margin:16px 0 10px;color:#0f172a;font-size:14px;font-weight:600}.monitoring-select,.monitoring-year-input{width:100%;max-width:420px;border:1px solid #cbd5e1;border-radius:7px;padding:9px;background:#f8fafc}.monitoring-year-field{margin-bottom:18px}.monitoring-year-field[hidden],.monitoring-help[hidden],.monitoring-template[hidden]{display:none}.monitoring-template{margin:12px 0 4px}.monitoring-template-button{display:inline-flex;align-items:center;justify-content:center;max-width:100%;padding:9px 14px;border:1px solid #2563eb;border-radius:7px;color:#1d4ed8;font-size:14px;font-weight:600;text-align:center;text-decoration:none}.monitoring-template-button:hover,.monitoring-template-button:focus-visible{background:#eff6ff}.monitoring-template-button:focus-visible{outline:2px solid #1d4ed8;outline-offset:2px}.monitoring-file-row{display:flex;align-items:center;gap:14px}.monitoring-file-row input{min-width:0;flex:1;border:1px solid #cbd5e1;border-radius:7px;padding:9px;background:#f8fafc}.monitoring-help{margin:10px 0 0;color:#64748b;font-size:12px}.monitoring-summary-card h2{margin:0 0 14px;color:#0f172a;font-size:20px}.monitoring-table-wrap{overflow-x:auto}.monitoring-table{width:100%;border-collapse:collapse;font-size:13px}.monitoring-table th,.monitoring-table td{border-bottom:1px solid #e2e8f0;padding:11px 10px;text-align:left;vertical-align:top}.monitoring-table th{background:#0f1d3a;color:#fff}.monitoring-table details{margin-top:7px;color:#991b1b}.monitoring-table ul,.pumk-alert ul{margin:7px 0 0;padding-left:20px}.monitoring-status{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:10px;font-weight:700}.monitoring-status.success,.monitoring-status.imported{background:#dcfce7;color:#166534}.monitoring-status.partial,.monitoring-status.needs_review{background:#fef3c7;color:#92400e}.monitoring-status.failed{background:#fee2e2;color:#991b1b}.monitoring-status.skipped,.monitoring-status.ignored{background:#e2e8f0;color:#475569}.pumk-alert.warning{border:1px solid #f59e0b;background:#fffbeb;color:#92400e}.pumk-alert.info{border:1px solid #93c5fd;background:#eff6ff;color:#1e40af}@media(max-width:700px){.monitoring-file-row{align-items:stretch;flex-direction:column}}
    </style>
</x-layouts.pumk-admin>
