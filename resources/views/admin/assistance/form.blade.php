<x-layouts.admin :title="$item->exists ? 'Detail Bantuan TJSL' : 'Buat Bantuan TJSL'">
    @php
        $routePrefix = $routePrefix ?? 'admin';
        $isSuperAdmin = $isSuperAdmin ?? false;
        $readonly = ! $editable;
        $docTypes = \App\Models\BantuanCsr::PHASE_ONE_DOCUMENTS;
        $bastDocument = $item->documents?->firstWhere('document_type', 'bast');
        $additionalBastDocuments = ($item->documents ?? collect())
            ->where(
                'document_type',
                \App\Models\BantuanCsr::PHASE_TWO_ADDITIONAL_DOCUMENT_TYPE,
            )
            ->values();
        $bastPhotos = $item->relationLoaded('photos')
            ? $item->photos
            : collect();
        $detailFields = [
            'rincian_kegiatan' => 'Rincian Kegiatan',
            'penerima_bantuan' => 'Penerima Bantuan',
            'jenis_bantuan' => 'Jenis Bantuan',
            'quality' => 'Quality',
            'nominal_bantuan' => 'Nominal Bantuan',
        ];
        $targets = old(
            'targets',
            $item->exists ? $item->targets->pluck('target_text')->all() : [''],
        );
        $details = old(
            'details',
            $item->exists
                ? $item->details
                    ->map
                    ->only([
                        'rincian_kegiatan',
                        'penerima_bantuan',
                        'jenis_bantuan',
                        'quality',
                        'nominal_bantuan',
                    ])
                    ->all()
                : [[
                    'rincian_kegiatan' => '',
                    'penerima_bantuan' => '',
                    'jenis_bantuan' => '',
                    'quality' => '',
                    'nominal_bantuan' => '',
                ]],
        );
        $formAction = $isSuperAdmin
            ? route('superadmin.assistance.show', $item)
            : ($item->exists
                ? route('admin.assistance.update', $item)
                : route('admin.assistance.store'));
    @endphp

    <style>
        .assistance-review-gallery{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:14px 0 24px}
        .assistance-review-photo{overflow:hidden;border:1px solid #dbe2ea;border-radius:8px;background:#fff}
        .assistance-review-photo img{display:block;width:100%;height:145px;object-fit:cover}
        .assistance-review-photo p{margin:0;padding:9px 10px;color:#475569;font-size:12px}
        .assistance-review-gallery-title{margin:18px 0 8px;font-size:18px;font-weight:600;color:#0f172a}
        @media(max-width:800px){
            .assistance-review-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
    </style>

    <div class="reference-form-page">
        @include('admin.partials.status-badge-styles')

        <div class="reference-form-heading">
            <h1>Bantuan TJSL INKA</h1>

            @if ($item->exists)
                <span class="status-pill">
                    @include('admin.partials.status-badge', ['status' => $item->status])
                </span>
            @endif
        </div>

        @if (session('success'))
            <div class="form-alert success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="form-alert error">
                <strong>Periksa kembali isian:</strong> {{ $errors->first() }}
            </div>
        @endif

        @if ($item->status === 'rejected_fase1' && $item->fase1_rejected_reason)
            <div class="form-alert error">
                <strong>Pengajuan dihentikan:</strong> {{ $item->fase1_rejected_reason }}
                <p class="mt-2">Dokumen awal ditolak oleh Super Admin. Pengajuan ini tidak dapat diedit atau dilanjutkan ke BAST.</p>
            </div>
        @endif

        @if ($item->status === 'approved_fase1' && $item->fase2_rejected_reason)
            <div class="form-alert error">
                <strong>Alasan BAST ditolak:</strong> {{ $item->fase2_rejected_reason }}
            </div>
        @endif

        <form
            id="assistance-form"
            method="{{ $isSuperAdmin ? 'GET' : 'POST' }}"
            enctype="multipart/form-data"
            action="{{ $formAction }}"
        >
            @if (! $isSuperAdmin)
                @csrf

                @if ($item->exists)
                    @method('PUT')
                @endif
            @endif

            <label class="reference-row">
                <span>Nama Program Bantuan</span>
                <input
                    name="nama_program_bantuan"
                    value="{{ old('nama_program_bantuan', $item->nama_program_bantuan) }}"
                    placeholder="Masukkan nama program"
                    @readonly($readonly)
                >
            </label>

            <label class="reference-row">
                <span>Deskripsi Bantuan</span>
                <textarea name="deskripsi_bantuan" placeholder="Masukkan deskripsi program" @readonly($readonly)>{{ old('deskripsi_bantuan', $item->deskripsi_bantuan) }}</textarea>
            </label>

            @include('admin.partials.pillar-selector', [
                'pillars' => $pillars,
                'selectedPillarId' => $item->pillar_id,
                'readonly' => $readonly,
                'isSuperAdmin' => $isSuperAdmin,
                'required' => $editable,
            ])

            <label class="reference-row">
                <span>Rencana Anggaran</span>
                <input
                    type="number"
                    step="0.01"
                    name="rencana_anggaran"
                    value="{{ old('rencana_anggaran', $item->rencana_anggaran) }}"
                    placeholder="Masukkan rencana anggaran program"
                    @readonly($readonly)
                >
            </label>

            <label class="reference-row">
                <span>Realisasi Anggaran</span>
                <input
                    type="number"
                    step="0.01"
                    name="realisasi_anggaran"
                    value="{{ old('realisasi_anggaran', $item->realisasi_anggaran) }}"
                    placeholder="Masukkan realisasi anggaran program"
                    @readonly($readonly)
                >
            </label>

            @if (! $isSuperAdmin || ! $readonly)
                <div id="target-list">
                    @foreach ($targets as $target)
                        <label class="reference-row target-row">
                            <span>{{ $loop->first ? 'Tujuan Capaian Penerima Bantuan' : '' }}</span>
                            <input
                                name="targets[]"
                                value="{{ $target }}"
                                placeholder="Masukkan tujuan capaian penerima bantuan"
                                @readonly($readonly)
                            >
                        </label>
                    @endforeach
                </div>
            @endif

            @if ($editable)
                <button id="add-target-reference" type="button" class="wide-primary indented">
                    Tambah Tujuan Capaian
                </button>
            @endif

            <div id="assistance-documents">
                @foreach ($docTypes as $type => $label)
                    @php
                        $existing = $item->documents?->firstWhere('document_type', $type);
                    @endphp

                    <div class="reference-row document-row">
                        <span>{{ $label }}</span>
                        <input
                            name="documents[{{ $type }}][nama]"
                            value="{{ $existing?->nama_dokumen }}"
                            placeholder="Masukkan nama dokumen"
                            @readonly($readonly)
                        >
                        @if ($editable)
                            <input
                                type="file"
                                name="documents[{{ $type }}][file]"
                            >
                        @else
                            <span></span>
                        @endif

                        <div class="document-actions">
                            @if ($existing)
                                <a
                                    href="{{ route($routePrefix . '.assistance-documents.download', $existing) }}"
                                    class="action-view"
                                >
                                    Lihat
                                </a>
                            @endif

                            @if ($editable && $existing)
                                <button
                                    type="button"
                                    class="action-change"
                                    onclick="this.closest('.document-row').querySelector('input[type=file]').click()"
                                >
                                    Ganti
                                </button>
                                <button
                                    type="submit"
                                    form="delete-assistance-doc-{{ $existing->id }}"
                                    class="action-delete"
                                >
                                    Hapus
                                </button>
                            @elseif ($editable)
                                <button
                                    type="button"
                                    class="action-upload"
                                    onclick="this.closest('.document-row').querySelector('input[type=file]').click()"
                                >
                                    Unggah
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if (
                $item->exists
                && in_array($item->status, ['approved_fase1', 'pending_fase2', 'completed'], true)
            )
                <div class="phase-two-document">
                    <div class="reference-row document-row">
                        <span>Berita Acara Serah Terima (BAST)</span>
                        <span class="phase-document-name">
                            {{ $bastDocument?->nama_dokumen ?? 'Belum ada dokumen BAST.' }}
                        </span>
                        <span></span>
                        <div class="document-actions">
                            @if ($bastDocument)
                                <a
                                    href="{{ route($routePrefix . '.assistance-documents.download', $bastDocument) }}"
                                    class="action-view"
                                >
                                    Lihat BAST
                                </a>
                            @endif
                        </div>
                    </div>

                    @foreach ($additionalBastDocuments as $additionalDocument)
                        <div class="reference-row document-row">
                            <span>Dokumen Tambahan {{ $loop->iteration }}</span>
                            <span class="phase-document-name">
                                {{ $additionalDocument->nama_dokumen }}
                            </span>
                            <span></span>
                            <div class="document-actions">
                                <a
                                    href="{{ route($routePrefix . '.assistance-documents.download', $additionalDocument) }}"
                                    class="action-view"
                                >
                                    Lihat
                                </a>
                            </div>
                        </div>
                    @endforeach

                    @if ($bastPhotos->isNotEmpty())
                        <h2 class="assistance-review-gallery-title">
                            Dokumentasi Bantuan
                        </h2>
                        <div class="assistance-review-gallery">
                            @foreach ($bastPhotos as $photo)
                                <article class="assistance-review-photo">
                                    <a
                                        href="{{ Storage::url($photo->file_path) }}"
                                        target="_blank"
                                        rel="noopener"
                                    >
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
                </div>
            @endif

            @if ($isSuperAdmin && $readonly)
                <div id="target-list">
                    @foreach ($targets as $target)
                        <label class="reference-row target-row">
                            <span>{{ $loop->first ? 'Tujuan Capaian Penerima Bantuan' : '' }}</span>
                            <input name="targets[]" value="{{ $target }}" readonly>
                        </label>
                    @endforeach
                </div>
            @endif

            <div id="detail-list">
                @foreach ($details as $i => $detail)
                    <fieldset class="detail-card">
                        <legend>Rincian {{ $i + 1 }}</legend>

                        @foreach ($detailFields as $name => $label)
                            <label class="reference-row">
                                <span>{{ $label }}</span>
                                <input
                                    name="details[{{ $i }}][{{ $name }}]"
                                    value="{{ $detail[$name] ?? '' }}"
                                    type="{{ $name === 'nominal_bantuan' ? 'number' : 'text' }}"
                                    placeholder="Masukkan {{ strtolower($label) }}"
                                    @readonly($readonly)
                                >
                            </label>
                        @endforeach

                    </fieldset>
                @endforeach
            </div>

            @if ($editable)
                <button id="add-detail-reference" type="button" class="wide-primary indented">
                    Tambah Rincian Lainnya
                </button>
            @endif

            @if ($editable)
                <div class="outside-actions">
                    <button name="action" value="draft" class="wide-draft">
                        Simpan Draft
                    </button>
                    <button name="action" value="submit" class="wide-primary">
                        Simpan dan Ajukan Kepada Super Admin
                    </button>
                </div>
            @endif
        </form>
        @if (! $isSuperAdmin)
            @foreach ($item->documents ?? [] as $document)
                <form
                    id="delete-assistance-doc-{{ $document->id }}"
                    method="POST"
                    action="{{ route('admin.assistance-documents.destroy', $document) }}"
                    class="hidden-form"
                >
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @endif

        @if (
            ! $isSuperAdmin
            && $item->exists
            && in_array($item->status, ['approved_fase1', 'pending_fase2', 'completed'], true)
        )
            @php
                $bastLabel = match ($item->status) {
                    'approved_fase1' => $item->fase2_rejected_reason
                        ? 'Perbaiki dan Unggah Ulang BAST'
                        : 'Unggah Dokumen BAST',
                    'pending_fase2' => 'Lihat Pengajuan BAST',
                    'completed' => 'Lihat Dokumen BAST',
                };
            @endphp
            <a href="{{ route('admin.assistance.phase2', $item) }}" class="back-overview">
                {{ $bastLabel }}
            </a>
        @endif

        @if (! $isSuperAdmin && $item->status === 'pending_fase1')
            <form
                method="POST"
                action="{{ route('admin.assistance.cancel', $item) }}"
                class="standalone-action"
            >
                @csrf
                <button class="wide-light">Batal Ajukan Kepada Super Admin</button>
            </form>
        @endif

        @if (
            $isSuperAdmin
            && $item->exists
            && ! $item->is_archived
            && in_array($item->status, ['pending_fase1', 'pending_fase2'], true)
        )
            <div class="super-review-actions">
                @php($reviewTarget = $item->status === 'pending_fase1' ? 'Dokumen Awal' : 'BAST')
                <form
                    method="POST"
                    action="{{ route('superadmin.assistance.reject', $item) }}"
                    class="reject-form"
                >
                    @csrf
                    <button type="button" class="review-reject" data-reject-open>
                        Tolak {{ $reviewTarget }}
                    </button>
                    <div class="reject-reason" hidden>
                        <textarea
                            name="rejected_reason"
                            required
                            minlength="5"
                            placeholder="Tuliskan alasan penolakan {{ $reviewTarget }}"
                        ></textarea>
                        <button class="review-reject-confirm">Konfirmasi Penolakan</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('superadmin.assistance.approve', $item) }}">
                    @csrf
                    <button class="review-approve">Setujui {{ $reviewTarget }}</button>
                </form>
            </div>
        @endif

        <a href="{{ route($routePrefix . '.assistance.index') }}" class="back-overview">
            Kembali ke Overview
        </a>
    </div>

    <script>
        document.querySelectorAll('[data-reject-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                button.hidden = true;
                button.nextElementSibling.hidden = false;
                button.nextElementSibling.querySelector('textarea')?.focus();
            });
        });
    </script>

    @if ($editable)
        <script>
            document.getElementById('add-target-reference')?.addEventListener('click', () => {
                const row = document.createElement('label');

                row.className = 'reference-row target-row';
                row.innerHTML = '<span></span><input name="targets[]" placeholder="Masukkan tujuan capaian penerima bantuan" required>';
                document.getElementById('target-list').appendChild(row);
            });

            document.getElementById('add-detail-reference')?.addEventListener('click', () => {
                const list = document.getElementById('detail-list');
                const i = list.querySelectorAll('fieldset').length;
                const row = document.createElement('fieldset');
                const fields = [
                    ['rincian_kegiatan', 'Rincian Kegiatan'],
                    ['penerima_bantuan', 'Penerima Bantuan'],
                    ['jenis_bantuan', 'Jenis Bantuan'],
                    ['quality', 'Quality'],
                    ['nominal_bantuan', 'Nominal Bantuan'],
                ];

                row.className = 'detail-card';
                row.innerHTML = `
                    <legend>Rincian ${i + 1}</legend>
                    ${fields.map(([name, label]) => `
                        <label class="reference-row">
                            <span>${label}</span>
                            <input
                                name="details[${i}][${name}]"
                                type="${name === 'nominal_bantuan' ? 'number' : 'text'}"
                                placeholder="Masukkan ${label.toLowerCase()}"
                                required
                            >
                        </label>
                    `).join('')}
                `;

                list.appendChild(row);
            });
        </script>
    @endif

    @if ($editable)
        @include('admin.partials.file-validation', ['formId' => 'assistance-form'])
    @endif
</x-layouts.admin>
