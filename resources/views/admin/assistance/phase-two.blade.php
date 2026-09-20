<x-layouts.admin :title="'BAST - '.$item->nama_program_bantuan">
    <style>
        .assistance-bast-page{max-width:1100px;margin:0 auto;font-family:Poppins,sans-serif}
        .assistance-bast-card{border-radius:11px;background:#fff;padding:18px 20px;box-shadow:0 3px 4px rgba(0,0,0,.24)}
        .assistance-bast-card .document-row{grid-template-columns:250px minmax(0,1.4fr) 172px 260px}
        .assistance-bast-section-title{margin:20px 0 8px;font-size:21px;font-weight:600;color:#0f172a}
        .assistance-bast-gallery{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px}
        .assistance-bast-photo{overflow:hidden;border:1px solid #dbe2ea;border-radius:8px;background:#fff}
        .assistance-bast-photo img{display:block;width:100%;height:145px;object-fit:cover}
        .assistance-bast-photo p{margin:0;padding:9px 10px;color:#475569;font-size:12px}
        .legacy-bast-documents{margin-top:20px;border-top:1px solid #e2e8f0;padding-top:12px}
        .legacy-bast-documents h2{margin:0 0 8px;font-size:15px;color:#64748b}
        .assistance-bast-submit{margin-top:24px}
        .assistance-bast-navigation{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:22px}
        .assistance-bast-navigation a{border-radius:6px;padding:12px;text-align:center;text-decoration:none;font-weight:600}
        .assistance-bast-navigation .back-detail{background:#7fa4e4;color:#fff}
        .assistance-bast-navigation .back-overview-stage{background:#2653ff;color:#fff}
        @media(max-width:1100px){
            .assistance-bast-card .document-row{grid-template-columns:220px minmax(0,1fr) 160px 210px}
        }
        @media(max-width:800px){
            .assistance-bast-card .document-row{grid-template-columns:1fr}
            .assistance-bast-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}
            .assistance-bast-navigation{grid-template-columns:1fr}
        }
    </style>

    <main class="reference-form-page assistance-bast-page">
        @include('admin.partials.status-badge-styles')

        <header class="reference-form-heading">
            <h1>BAST Bantuan TJSL INKA</h1>
            <span class="status-pill">
                @include('admin.partials.status-badge', ['status' => $item->status])
            </span>
        </header>

        @if (session('success'))
            <div class="form-alert success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="form-alert error">
                <strong>Periksa kembali isian:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($item->fase2_rejected_reason)
            <div class="form-alert error">
                <strong>Alasan BAST ditolak:</strong> {{ $item->fase2_rejected_reason }}
            </div>
        @endif

        <section class="assistance-bast-card">
            @if ($editable)
                <form
                    id="assistance-bast-form"
                    method="POST"
                    enctype="multipart/form-data"
                    action="{{ route('admin.assistance.bast.store', $item) }}"
                >
                    @csrf

                    <div class="reference-row document-row">
                        <span>Berita Acara Serah Terima (BAST)</span>
                        <input
                            name="bast_document_name"
                            value="{{ old('bast_document_name', $bastDocument?->nama_dokumen) }}"
                            placeholder="Masukkan nama dokumen"
                        >
                        <input
                            id="assistance-bast-file"
                            type="file"
                            name="dokumen_f"
                            accept=".pdf,.doc,.docx,.xls,.xlsx"
                            required
                        >
                        <div class="document-actions">
                            @if ($bastDocument)
                                <a
                                    href="{{ route('admin.assistance-documents.download', $bastDocument) }}"
                                    class="action-view"
                                >
                                    Lihat
                                </a>
                            @endif
                            <button
                                type="button"
                                class="action-upload"
                                onclick="document.getElementById('assistance-bast-file').click()"
                            >
                                {{ $bastDocument ? 'Ganti' : 'Unggah' }}
                            </button>
                        </div>
                    </div>

                    <div id="assistance-phase-two-photo-rows">
                        <div class="reference-row document-row assistance-phase-two-photo-row">
                            <span>Dokumentasi Bantuan</span>
                            <input
                                name="phase2_photo_captions[]"
                                value="{{ old('phase2_photo_captions.0') }}"
                                placeholder="Masukkan keterangan gambar"
                            >
                            <input
                                id="assistance-phase-two-photo-0"
                                type="file"
                                name="phase2_photos[]"
                                accept="image/*"
                            >
                            <div class="document-actions">
                                <button
                                    type="button"
                                    class="action-upload"
                                    onclick="document.getElementById('assistance-phase-two-photo-0').click()"
                                >
                                    Unggah
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        id="add-assistance-phase-two-photo"
                        class="wide-primary inside-card"
                    >
                        Tambah Gambar
                    </button>

                    <button class="wide-primary assistance-bast-submit">
                        {{ $bastDocument ? 'Simpan Perbaikan dan Ajukan BAST' : 'Simpan dan Ajukan BAST' }}
                    </button>
                </form>
            @elseif ($bastDocument)
                <div class="reference-row document-row">
                    <span>Berita Acara Serah Terima (BAST)</span>
                    <input value="{{ $bastDocument->nama_dokumen }}" readonly>
                    <span></span>
                    <div class="document-actions">
                        <a
                            href="{{ route('admin.assistance-documents.download', $bastDocument) }}"
                            class="action-view"
                        >
                            Lihat BAST
                        </a>
                    </div>
                </div>
                @if ($item->status === 'pending_fase2')
                    <p class="phase-document-note">
                        Dokumen BAST sudah diajukan dan sedang menunggu keputusan Super Admin.
                    </p>
                @elseif ($item->status === 'completed')
                    <p class="phase-document-note">
                        Dokumen BAST telah disetujui dan bersifat final.
                    </p>
                @endif
            @else
                <p class="phase-document-note">
                    Belum ada dokumen BAST.
                </p>
            @endif

            @if ($item->photos->isNotEmpty())
                <h2 class="assistance-bast-section-title">Dokumentasi Bantuan Saat Ini</h2>
                <div class="assistance-bast-gallery">
                    @foreach ($item->photos as $photo)
                        <article class="assistance-bast-photo">
                            <a href="{{ Storage::url($photo->file_path) }}" target="_blank" rel="noopener">
                                <img
                                    src="{{ Storage::url($photo->file_path) }}"
                                    alt="{{ $photo->caption ?: 'Dokumentasi Bantuan TJSL' }}"
                                >
                            </a>
                            <p>{{ $photo->caption ?: 'Tanpa keterangan' }}</p>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($legacyAdditionalDocuments->isNotEmpty())
                <div class="legacy-bast-documents">
                    <h2>Lampiran dokumen lama</h2>
                    @foreach ($legacyAdditionalDocuments as $additionalDocument)
                        <div class="reference-row document-row">
                            <span>Lampiran {{ $loop->iteration }}</span>
                            <input value="{{ $additionalDocument->nama_dokumen }}" readonly>
                            <span></span>
                            <div class="document-actions">
                                <a
                                    href="{{ route('admin.assistance-documents.download', $additionalDocument) }}"
                                    class="action-view"
                                >
                                    Lihat
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <nav class="assistance-bast-navigation">
            <a class="back-detail" href="{{ route('admin.assistance.show', $item) }}">
                Kembali ke Detail Bantuan
            </a>
            <a class="back-overview-stage" href="{{ route('admin.assistance.index') }}">
                Kembali ke Overview
            </a>
        </nav>
    </main>

    @if ($editable)
        <script>
            (function () {
                const rows = document.getElementById('assistance-phase-two-photo-rows');
                const addButton = document.getElementById('add-assistance-phase-two-photo');
                let photoIndex = 1;

                addButton?.addEventListener('click', function () {
                    if (!rows || rows.children.length >= 10) return;

                    const inputId = `assistance-phase-two-photo-${photoIndex}`;
                    const row = document.createElement('div');

                    row.className = 'reference-row document-row assistance-phase-two-photo-row';
                    row.innerHTML = `
                        <span>Dokumentasi Tambahan ${photoIndex}</span>
                        <input name="phase2_photo_captions[]" placeholder="Masukkan keterangan gambar">
                        <input id="${inputId}" type="file" name="phase2_photos[]" accept="image/*">
                        <div class="document-actions">
                            <button type="button" class="action-upload" data-photo-input="${inputId}">Unggah</button>
                        </div>
                    `;
                    rows.appendChild(row);
                    photoIndex += 1;

                    if (rows.children.length >= 10) addButton.disabled = true;
                });

                rows?.addEventListener('click', function (event) {
                    const button = event.target.closest('[data-photo-input]');
                    if (button) document.getElementById(button.dataset.photoInput)?.click();
                });
            })();
        </script>
        @include('admin.partials.file-validation', ['formId' => 'assistance-bast-form'])
    @endif
</x-layouts.admin>
