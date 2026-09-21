@props(['program'])

@php
    $colors = [
        'sosial' => '#1d4ed8',
        'ekonomi' => '#c46a05',
        'lingkungan' => '#15803d',
        'hukum-tata-kelola' => '#dc2626',
    ];

    $borderColor = $colors[$program['pillar']] ?? '#64748b';
    $kind = ($program['jenis'] ?? 'internal') === 'csr'
        ? 'bantuan-tjsl'
        : (($program['jenis_kerjasama'] ?? 'pks') === 'non_pks' ? 'non-pks' : 'pks');
    $kindLabel = [
        'pks' => 'PKS',
        'non-pks' => 'Non-PKS',
        'bantuan-tjsl' => 'Bantuan TJSL',
    ][$kind];
@endphp

<a
    href="{{ $program['detail_url'] ?? route('program.detail', $program['slug']) }}"
    class="program-rincian-card group relative min-h-60 overflow-hidden rounded-xl bg-slate-900"
    data-pillar-card="{{ $program['pillar'] ?? 'belum-ditentukan' }}"
    data-program-kind="{{ $kind }}"
    @style(['--pillar-color:'.$borderColor])
>
    @if($program['cover_image'])
        <img
            src="{{ asset($program['cover_image']) }}"
            alt="{{ $program['title'] }}"
            class="program-rincian-card-image absolute inset-0 h-full w-full object-cover"
        >
    @else
        <div class="program-rincian-card-image absolute inset-0 bg-linear-to-br from-slate-700 to-slate-950" aria-hidden="true"></div>
    @endif
    <div class="program-rincian-card-overlay absolute inset-0 bg-linear-to-t from-black via-black/15 to-transparent"></div>
    <span class="program-rincian-card-badge program-rincian-card-badge--{{ $kind }} absolute left-3 top-3 rounded-full px-3 py-1 text-[11px] font-extrabold text-white">
        {{ $kindLabel }}
    </span>
    <h3 class="program-rincian-card-title absolute inset-x-0 bottom-0 p-4 text-sm font-semibold leading-relaxed text-white">
        {{ $program['title'] }}
    </h3>
</a>
