<x-layouts.pumk-admin title="Kartu Piutang Mitra">
    @include('pumk-admin.partials.module-styles')

    @php
        $rupiah = static fn ($value) => 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
        $angka = static fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.');
        $statusLabels = [
            'lancar' => 'Lancar',
            'kurang_lancar' => 'Kurang Lancar',
            'diragukan' => 'Diragukan',
            'macet' => 'Macet',
        ];
        $status = array_key_exists('kolektibilitas', $calculation ?? [])
            ? $calculation['kolektibilitas'] : $pinjaman?->kolektibilitas;
        $isRescheduled = filled($pinjaman?->reschedule_ke1)
            || filled($pinjaman?->reschedule_ke2)
            || filled($pinjaman?->reschedule_ke3)
            || filled($pinjaman?->reschedule_ke4);
        $saldoAwalOverlap = session('saldo_awal_overlap');
        $loanIsActive = $pinjaman?->status === \App\Models\PumkPinjaman::STATUS_AKTIF && (bool) $pinjaman?->is_active;
        $contractDocumentFields = \App\Models\PumkPinjamanDokumen::CONTRACT_FIELDS;
        $contractDocumentLabels = \App\Models\PumkPinjamanDokumen::TYPES;
        $contractDocuments = $pinjaman?->dokumenKontrak?->keyBy('jenis_dokumen') ?? collect();
    @endphp

    @include('pumk-admin.mitra.partials.card.styles')
    <div class="pumk-page receivable-page">
        @include('pumk-admin.mitra.partials.card.toolbar')

        @include('pumk-admin.mitra.partials.card.year-filter')

        @if(session('success'))<div class="pumk-alert success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="pumk-alert error" role="alert">
                <strong>Periksa kembali isian:</strong>
                <ul style="margin:8px 0 0;padding-left:20px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @include('pumk-admin.mitra.partials.card.loan-selector')
        @include('pumk-admin.mitra.partials.card.status-history')
        @include('pumk-admin.mitra.partials.card.opening-balance-conflict')

        @if($pinjaman && $kartu)
            @include('pumk-admin.mitra.partials.card.receivable-card')

            @include('pumk-admin.mitra.partials.card.installment-form')

            @include('pumk-admin.mitra.partials.card.loan-details')

            @if($loanIsActive)
            @include('pumk-admin.mitra.partials.card.edit-installment-dialog')
            @include('pumk-admin.mitra.partials.card.settlement-dialog')
            @endif
        @else
            <section class="pumk-card pumk-empty">Data pinjaman belum tersedia. Gunakan tombol Edit Data untuk melengkapinya.</section>
        @endif
    </div>

    @include('pumk-admin.mitra.partials.card.scripts')
</x-layouts.pumk-admin>
