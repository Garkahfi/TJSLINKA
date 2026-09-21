<x-layouts.admin title="Status Program">
    @include('admin.partials.status-badge-styles')

    <div class="mx-auto max-w-275">
        <div class="mb-7">
            <div>
                <h1 class="text-3xl font-bold">Program TJSL INKA</h1>

                @if ($selectedPillar)
                    <p class="mt-1 text-sm text-slate-600">Kategori: {{ $selectedPillar->name }}</p>
                @endif
            </div>
        </div>

        @include('admin.partials.status-filter', [
            'routeName' => 'admin.programs.index',
            'filterId' => 'admin-program-status-filter',
            'selectedPillar' => $selectedPillar,
            'resultCount' => $programs->count(),
            'resultLabel' => 'program',
        ])

        <div class="space-y-5">
            @forelse ($programs as $program)
                @php
                    $backgroundColor = $program->pillar?->color_hex ?? '#64748b';
                @endphp

                <a
                    href="{{ route('admin.programs.show', $program) }}"
                    data-program-status="{{ $program->status }}"
                    class="flex min-h-20.75 items-center justify-between gap-4 rounded-md px-3 py-3 text-white shadow-md"
                    @style(['background:'.$backgroundColor])
                >
                    <span>
                        <strong class="block text-lg">
                            {{ $program->nama_program }}
                        </strong>
                        <small>{{ $program->updated_at->format('H.i d/m/Y') }}</small>
                    </span>

                    <span class="flex items-center gap-3">
                        @include('admin.partials.status-badge', ['status' => $program->status, 'phaseTwoRejected' => filled($program->fase2_rejected_reason)])
                    </span>
                </a>
            @empty
                <p class="rounded-xl border bg-white p-8 text-center text-slate-500">
                    Belum ada program dengan status {{ $statusOptions[$statusFilter] }}{{ $selectedPillar ? ' pada kategori '.$selectedPillar->name : '' }}.
                </p>
            @endforelse
        </div>

        @if ($selectedPillar)
            <a href="{{ route('admin.programs.index') }}" class="text-action-primary mt-6 inline-block text-sm font-semibold">
                Lihat Semua Kategori
            </a>
        @endif
    </div>
</x-layouts.admin>
