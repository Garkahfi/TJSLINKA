<x-layouts.admin :title="'BAST & Dokumentasi - '.$program->nama_program">
    @php
        $photos = $program->photos ?? collect();
    @endphp

    <style>
        .bast-page{max-width:1100px;margin:0 auto;font-family:Poppins,sans-serif}
        .bast-page .reference-form-heading{margin-bottom:34px}
        .bast-card{border-radius:11px;background:#fff;padding:10px 12px;box-shadow:0 3px 4px rgba(0,0,0,.24)}
        .bast-section-title{margin:16px 0 6px;font-size:21px;font-weight:600;color:#0f172a}
        .bast-card .document-row{grid-template-columns:250px minmax(0,1.4fr) 172px 260px}
        .bast-existing{margin:6px 0 12px 250px;border-radius:6px;background:#f3f4f6;padding:10px 13px;color:#475569;font-size:13px}
        .bast-existing a{margin-left:8px;color:#2653ff;font-weight:600}
        .bast-submit{margin-top:24px}
        .bast-gallery{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px}
        .bast-photo{overflow:hidden;border:1px solid #dbe2ea;border-radius:8px;background:#fff}
        .bast-photo img{display:block;width:100%;height:145px;object-fit:cover}
        .bast-photo p{margin:0;padding:9px 10px;color:#475569;font-size:12px}
        .bast-navigation{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:22px}
        .bast-navigation a{border-radius:6px;padding:12px;text-align:center;text-decoration:none;font-weight:600}
        .bast-navigation .back-detail{background:#7fa4e4;color:#fff}
        .bast-navigation .back-overview-stage{background:#2653ff;color:#fff}
        @media(max-width:1100px){
            .bast-card .document-row{grid-template-columns:220px minmax(0,1fr) 160px 210px}
            .bast-existing{margin-left:220px}
        }
        @media(max-width:800px){
            .bast-card .document-row{grid-template-columns:1fr}
            .bast-existing{margin-left:0}
            .bast-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}
            .bast-navigation{grid-template-columns:1fr}
        }
    </style>

    <main class="reference-form-page bast-page">
        @include('admin.partials.status-badge-styles')

        <header class="reference-form-heading">
            <h1>BAST & Dokumentasi Tambahan</h1>
            <span class="status-pill">
                @include('admin.partials.status-badge', ['status' => $program->status])
            </span>
        </header>

        @if(session('success'))
            <div class="form-alert success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="form-alert error">
                <strong>Periksa kembali isian:</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($program->fase2_rejected_reason)
            <div class="form-alert error">
                <strong>Alasan BAST ditolak:</strong> {{ $program->fase2_rejected_reason }}
            </div>
        @endif

        <section class="bast-card">
            @if($editable)
                <form
                    id="program-form"
                    method="POST"
                    enctype="multipart/form-data"
                    action="{{ route('admin.programs.bast.store', $program) }}"
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
                            id="bast-document-file"
                            type="file"
                            name="dokumen_f"
                            accept=".pdf,.doc,.docx,.xls,.xlsx"
                            required
                        >
                        <div class="document-actions">
                            @if($bastDocument)
                                <a href="{{ route('admin.program-documents.download', $bastDocument) }}" class="action-view">Lihat</a>
                            @endif
                            <button
                                type="button"
                                class="action-upload"
                                onclick="document.getElementById('bast-document-file').click()"
                            >
                                Unggah
                            </button>
                        </div>
                    </div>

                    <div id="phase-two-photo-rows">
                        <div class="reference-row document-row phase-two-photo-row">
                            <span>Dokumentasi Program</span>
                            <input
                                name="phase2_photo_captions[]"
                                value="{{ old('phase2_photo_captions.0') }}"
                                placeholder="Masukkan keterangan gambar"
                            >
                            <input
                                id="phase-two-photo-0"
                                type="file"
                                name="phase2_photos[]"
                                accept="image/*"
                            >
                            <div class="document-actions">
                                <button
                                    type="button"
                                    class="action-upload"
                                    onclick="document.getElementById('phase-two-photo-0').click()"
                                >
                                    Unggah
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="add-phase-two-photo" class="wide-primary inside-card">
                        Tambah Gambar
                    </button>

                    <button class="wide-primary bast-submit">
                        {{ $bastDocument ? 'Simpan Perbaikan dan Ajukan BAST' : 'Simpan dan Ajukan BAST' }}
                    </button>
                </form>
            @elseif($bastDocument)
                <div class="reference-row document-row">
                    <span>Berita Acara Serah Terima (BAST)</span>
                    <input value="{{ $bastDocument->nama_dokumen }}" readonly>
                    <span></span>
                    <div class="document-actions">
                        <a href="{{ route('admin.program-documents.download', $bastDocument) }}" class="action-view">Lihat BAST</a>
                    </div>
                </div>
            @endif

            @if($photos->isNotEmpty())
                <h2 class="bast-section-title">Dokumentasi Program Saat Ini</h2>
                <div class="bast-gallery">
                    @foreach($photos as $photo)
                        <article class="bast-photo">
                            <a href="{{ Storage::url($photo->file_path) }}" target="_blank" rel="noopener">
                                <img src="{{ Storage::url($photo->file_path) }}" alt="{{ $photo->caption ?: 'Dokumentasi program' }}">
                            </a>
                            <p>{{ $photo->caption ?: 'Tanpa keterangan' }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <nav class="bast-navigation">
            <a class="back-detail" href="{{ route('admin.programs.show', $program) }}">
                Kembali ke Detail Program
            </a>
            <a class="back-overview-stage" href="{{ route('admin.programs.index') }}">
                Kembali ke Overview
            </a>
        </nav>
    </main>

    @if($editable)
        <script>
            (function () {
                const rows = document.getElementById('phase-two-photo-rows');
                const addButton = document.getElementById('add-phase-two-photo');
                let photoIndex = 1;

                addButton?.addEventListener('click', function () {
                    if (!rows || rows.children.length >= 10) return;

                    const row = document.createElement('div');
                    const inputId = `phase-two-photo-${photoIndex}`;
                    row.className = 'reference-row document-row phase-two-photo-row';
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
        @include('admin.partials.file-validation', ['formId' => 'program-form'])
    @endif
</x-layouts.admin>
