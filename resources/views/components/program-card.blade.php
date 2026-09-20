@props(['program'])

@php
    $colors = [
        'sosial' => '#2f6de9',
        'ekonomi' => '#f59e0b',
        'lingkungan' => '#16a34a',
        'hukum-tata-kelola' => '#ef272d',
    ];

    $borderColor = $colors[$program['pillar']] ?? '#64748b';
@endphp

<a
    href="{{ $program['detail_url'] ?? route('program.detail', $program['slug']) }}"
    class="program-rincian-card group relative min-h-60 overflow-hidden rounded-xl border-2 bg-slate-900 shadow-lg"
    data-pillar-card="{{ $program['pillar'] ?? 'belum-ditentukan' }}"
    @style(['--pillar-color:'.$borderColor, 'border-color:'.$borderColor])
>
    @if($program['cover_image'])
        <img
            src="{{ asset($program['cover_image']) }}"
            alt="{{ $program['title'] }}"
            class="program-rincian-card-image absolute inset-0 h-full w-full object-cover"
        >
    @else
        <div class="program-rincian-card-image absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-950" aria-hidden="true"></div>
    @endif
    <div class="program-rincian-card-overlay absolute inset-0 bg-gradient-to-t from-black via-black/15 to-transparent"></div>
    @if(($program['jenis'] ?? 'internal') === 'csr')
        <span class="program-rincian-card-badge absolute left-3 top-3 rounded-full bg-[#7c3aed] px-3 py-1 text-xs font-bold text-white shadow">
            CSR
        </span>
    @endif
    <h3 class="program-rincian-card-title absolute inset-x-0 bottom-0 p-4 text-sm font-semibold leading-relaxed text-white">
        {{ $program['title'] }}
    </h3>
</a>
