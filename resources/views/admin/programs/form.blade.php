<x-layouts.admin :title="$program->exists ? 'Detail Program' : 'Buat Program'">
@php
    $routePrefix = $routePrefix ?? 'admin';
    $isSuperAdmin = $isSuperAdmin ?? false;
    $readonly = !$editable;
    $docs = $program->phaseOneDocuments();
    $bastDocument = $program->documents?->firstWhere('document_type', 'bast');
    $storedGoals = $program->relationLoaded('tujuan') ? $program->tujuan : collect();
    $storedGoalText = filled($program->tujuan_program)
        ? $program->tujuan_program
        : $storedGoals->pluck('deskripsi')->filter()->implode("\n\n");
    $formAction = $isSuperAdmin
        ? route('superadmin.programs.show', $program)
        : ($program->exists
            ? route('admin.programs.update', $program)
            : route('admin.programs.store'));
@endphp
<div class="reference-form-page">
    @include('admin.partials.status-badge-styles')
    <div class="reference-form-heading">
        <h1>Program TJSL INKA</h1>
        @if($program->exists)<span class="status-pill">@include('admin.partials.status-badge',['status'=>$program->status])</span>@endif
    </div>
    @if(session('success'))<div class="form-alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="form-alert error"><strong>Periksa kembali isian:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($program->status === 'rejected_fase1' && $program->fase1_rejected_reason)
        <div class="form-alert error">
            <strong>Alasan program ditolak:</strong> {{ $program->fase1_rejected_reason }}
        </div>
    @endif
    @if($isSuperAdmin && $program->status === 'approved_fase1' && $program->fase2_rejected_reason)
        <div class="form-alert error">
            <strong>Alasan BAST ditolak:</strong> {{ $program->fase2_rejected_reason }}
        </div>
    @endif

    <form id="program-form" method="{{ $isSuperAdmin ? 'GET' : 'POST' }}" enctype="multipart/form-data" action="{{ $formAction }}">
        @if(! $isSuperAdmin)
            @csrf
            @if($program->exists) @method('PUT') @endif
        @endif
        <input
            type="hidden"
            name="jenis_kerjasama"
            value="{{ old('jenis_kerjasama', $program->jenis_kerjasama ?: 'pks') }}"
        >
        @foreach(['nama_program'=>'Nama Program','deskripsi_program'=>'Deskripsi Program','sasaran_program'=>'Sasaran Program','lokasi_program'=>'Lokasi Program','mitra_program'=>'Mitra Program','rencana_anggaran'=>'Rencana Anggaran','realisasi_anggaran'=>'Realisasi Anggaran','tujuan_program'=>'Tujuan Program'] as $name=>$label)
            <label class="reference-row">
                <span>{{ $label }}</span>
                @if(in_array($name,['deskripsi_program','sasaran_program','tujuan_program']))
                    <textarea name="{{ $name }}" placeholder="Masukkan {{ $label }}" @readonly($readonly)>{{ old($name, $name === 'tujuan_program' ? $storedGoalText : $program->$name) }}</textarea>
                @else
                    <input name="{{ $name }}" type="{{ str_contains($name,'anggaran')?'number':'text' }}" step="{{ str_contains($name,'anggaran')?'0.01':'1' }}" value="{{ old($name,$program->$name) }}" placeholder="Masukkan {{ $label }}" @readonly($readonly)>
                @endif
            </label>

        @endforeach

        <div id="program-documents">
            @foreach($docs as $type=>$label)
                @php
                    $existing = $program->documents?->firstWhere('document_type', $type);
                @endphp

                <div class="reference-row document-row">
                    <span>{{ $label }}</span>
                    <input name="documents[{{ $type }}][nama]" value="{{ $existing?->nama_dokumen }}" placeholder="Masukkan nama dokumen" @readonly($readonly)>
                    @if($editable)
                        <input type="file" name="documents[{{ $type }}][file]">
                    @else
                        <span></span>
                    @endif
                    <div class="document-actions">
                        @if($existing)<a href="{{ route($routePrefix.'.program-documents.download',$existing) }}" class="action-view">Lihat</a>@endif
                        @if($editable && $existing)<button type="button" class="action-change" onclick="this.closest('.document-row').querySelector('input[type=file]').click()">Ganti</button><button type="submit" form="delete-program-doc-{{ $existing->id }}" class="action-delete">Hapus</button>@elseif($editable)<button type="button" class="action-upload" onclick="this.closest('.document-row').querySelector('input[type=file]').click()">Unggah</button>@endif
                    </div>
                </div>
            @endforeach
        </div>
        @if($editable)<button type="button" id="add-program-document" class="wide-primary inside-card">Tambah Dokumen</button>@endif

        @if($isSuperAdmin)
        <div class="phase-two-document">
            <div class="reference-row document-row">
                <span>Berita Acara Serah Terima (BAST)</span>

                @if(! $program->exists || in_array($program->status, ['draft', 'pending_fase1', 'rejected_fase1'], true))
                    <p class="phase-document-note">
                        Dokumen BAST baru bisa diunggah setelah dokumen awal disetujui Super Admin.
                    </p>
                @elseif($program->status === 'approved_fase1')
                    @if($bastDocument)
                        <span class="phase-document-name">{{ $bastDocument->nama_dokumen }}</span>
                    @else
                        <span class="phase-document-name">Belum ada dokumen BAST.</span>
                    @endif

                    @if(! $isSuperAdmin)
                        <input
                            id="dokumen-f"
                            type="file"
                            name="dokumen_f"
                            form="bast-upload-form"
                            accept=".pdf,.doc,.docx,.xls,.xlsx"
                            required
                        >
                        <div class="document-actions">
                            @if($bastDocument)
                                <a href="{{ route('admin.program-documents.download', $bastDocument) }}" class="action-view">Lihat BAST Ditolak</a>
                            @endif
                            <button type="submit" form="bast-upload-form" class="action-upload">
                                {{ $bastDocument ? 'Unggah Ulang BAST' : 'Unggah BAST' }}
                            </button>
                        </div>
                    @else
                        <p class="phase-document-note">Menunggu Admin mengunggah dokumen BAST.</p>
                    @endif
                @elseif($program->status === 'pending_fase2')
                    <span class="phase-document-name">{{ $bastDocument?->nama_dokumen }}</span>
                    <p class="phase-document-note">Dokumen BAST sudah diunggah dan menunggu review Super Admin.</p>
                    @if($bastDocument)
                        <div class="document-actions">
                            <a href="{{ route($routePrefix.'.program-documents.download', $bastDocument) }}" class="action-view">Lihat BAST</a>
                        </div>
                    @endif
                @elseif($program->status === 'completed')
                    <span class="phase-document-name">{{ $bastDocument?->nama_dokumen }}</span>
                    <p class="phase-document-note">Dokumen BAST telah disetujui dan bersifat final.</p>
                    @if($bastDocument)
                        <div class="document-actions">
                            <a href="{{ route($routePrefix.'.program-documents.download', $bastDocument) }}" class="action-view">Lihat BAST</a>
                        </div>
                    @endif
                @endif
            </div>
        </div>
        @endif

        @include('admin.partials.pillar-selector', [
            'pillars' => $pillars,
            'selectedPillarId' => $program->pillar_id,
            'readonly' => $readonly,
            'isSuperAdmin' => $isSuperAdmin,
            'required' => $editable,
        ])

        @if($editable)
            <div class="outside-actions">
                <button name="action" value="draft" class="wide-draft">Simpan Draft</button>
                <button name="action" value="submit" class="wide-primary">
                    Simpan dan Ajukan Kepada Super Admin
                </button>
            </div>
        @endif
    </form>
    @if(! $isSuperAdmin && $program->exists && in_array($program->status, ['approved_fase1', 'pending_fase2', 'completed'], true))
        @php
            $phaseTwoLabel = match ($program->status) {
                'approved_fase1' => $program->fase2_rejected_reason
                    ? 'Perbaiki BAST & Dokumentasi Tambahan'
                    : 'BAST & Dokumentasi Tambahan',
                'pending_fase2' => 'Lihat Pengajuan BAST & Dokumentasi Tambahan',
                'completed' => 'Lihat BAST & Dokumentasi Program',
            };
        @endphp
        <a href="{{ route('admin.programs.phase2', $program) }}" class="back-overview">
            {{ $phaseTwoLabel }}
        </a>
    @endif
    @if(! $isSuperAdmin)
        @foreach($program->documents??[] as $document)<form id="delete-program-doc-{{ $document->id }}" method="POST" action="{{ route('admin.program-documents.destroy',$document) }}" class="hidden-form">@csrf @method('DELETE')</form>@endforeach
    @endif
    @if(!$isSuperAdmin && $program->status==='pending_fase1')<form method="POST" action="{{ route('admin.programs.cancel',$program) }}" class="standalone-action">@csrf<button class="wide-light">Batal Ajukan Kepada Super Admin</button></form>@endif
    @if($isSuperAdmin && $program->exists && !$program->is_archived && in_array($program->status, ['pending_fase1', 'pending_fase2'], true))
        <div class="super-review-actions">
            @php($reviewTarget = $program->status === 'pending_fase1' ? 'Program' : 'BAST')
            <form method="POST" action="{{ route('superadmin.programs.reject',$program) }}" class="reject-form">@csrf<button type="button" class="review-reject" data-reject-open>Reject {{ $reviewTarget }}</button><div class="reject-reason" hidden><textarea name="rejected_reason" required minlength="5" placeholder="Tuliskan alasan penolakan {{ $reviewTarget }}"></textarea><button class="review-reject-confirm">Konfirmasi Penolakan</button></div></form>
            <form method="POST" action="{{ route('superadmin.programs.approve',$program) }}">@csrf<button class="review-approve">Approve {{ $reviewTarget }}</button></form>
        </div>
    @endif
    <a href="{{ route($routePrefix.'.programs.index') }}" class="back-overview">Kembali ke Overview</a>
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
@if($editable)<script>document.getElementById('add-program-document')?.addEventListener('click',function(){const key='lainnya_'+Date.now();const row=document.createElement('div');row.className='reference-row document-row';row.innerHTML=`<span>Dokumen Lainnya</span><input name="documents[${key}][nama]" placeholder="Masukkan nama dokumen"><input type="file" name="documents[${key}][file]"><div class="document-actions"><button type="button" class="action-upload" onclick="this.closest('.document-row').querySelector('input[type=file]').click()">Unggah</button></div>`;document.getElementById('program-documents').appendChild(row)});</script>@endif
@if($editable)@include('admin.partials.file-validation',['formId'=>'program-form'])@endif
</x-layouts.admin>
