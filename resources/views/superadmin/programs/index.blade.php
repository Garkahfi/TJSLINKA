<x-layouts.admin title="Status Program">
    @include('admin.partials.status-badge-styles')

    <style>
        .sa-page{max-width:1100px;margin:auto}.sa-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:0 0 28px}.sa-page h1{margin:0;font-size:30px}.sa-category{margin:5px 0 0;color:#5d6470;font-size:14px}.sa-list{display:grid;gap:19px}.sa-item{display:flex;min-height:83px;box-sizing:border-box;align-items:center;justify-content:space-between;gap:20px;padding:12px 10px;border-radius:6px;color:#fff;text-decoration:none;box-shadow:0 3px 5px #0003}.sa-item strong{display:block;font-size:19px}.sa-meta{display:grid;grid-template-columns:280px minmax(0,1fr);gap:14px;margin-top:9px;font-size:13px}.sa-item-actions{display:flex;align-items:center;gap:12px}.sa-back{display:inline-block;margin-top:24px;color:#2653ff;font-size:14px;font-weight:600;text-decoration:none}.sa-empty{padding:32px;border:1px solid #ddd;border-radius:10px;background:#fff;color:#60636b;text-align:center}@media(max-width:700px){.sa-heading{align-items:flex-start;flex-direction:column}.sa-item{align-items:flex-start;flex-direction:column}.sa-meta{grid-template-columns:1fr;gap:3px}.sa-item-actions{width:100%;justify-content:space-between}}
    </style>

    <div class="sa-page">
        <div class="sa-heading">
            <div>
                <h1>Program TJSL INKA</h1>

                @if($selectedPillar)
                    <p class="sa-category">Kategori: {{ $selectedPillar->name }}</p>
                @endif
            </div>
        </div>

        <div class="sa-list">
            @forelse ($programs as $program)
                @php
                    $pillarColor = $program->pillar?->color_hex ?? '#64748b';
                @endphp

                <a
                    class="sa-item"
                    data-program-status="{{ $program->status }}"
                    href="{{ route('superadmin.programs.show', $program) }}"
                    @style(['background:'.$pillarColor])
                >
                    <span>
                        <strong>
                            {{ $program->nama_program }}
                        </strong>
                        <span class="sa-meta">
                            <span>{{ $program->updated_at->format('H.i d/m/Y') }}</span>
                            <span>{{ $program->creator?->name }}</span>
                        </span>
                    </span>

                    <span class="sa-item-actions">
                        @include('admin.partials.status-badge', ['status' => $program->status])
                    </span>
                </a>
            @empty
                <p class="sa-empty">
                    Belum ada program{{ $selectedPillar ? ' pada kategori '.$selectedPillar->name : '' }}.
                </p>
            @endforelse
        </div>

        @if($selectedPillar)
            <a href="{{ route('superadmin.programs.index') }}" class="sa-back">
                Lihat Semua Kategori
            </a>
        @endif
    </div>
</x-layouts.admin>
