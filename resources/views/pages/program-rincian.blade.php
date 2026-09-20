<x-layouts.app title="Realisasi Program — LENSA TJSL INKA">
    <x-hero-video video="videos/people-talking-bg.mp4" variant="animated-pillars" />

    <section class="py-16">
        <div class="container-site">
            <h2 class="section-title mb-8">Realisasi Program TJSL INKA</h2>

            <nav class="panel mb-14 grid grid-cols-2 gap-4 p-5 md:grid-cols-4" aria-label="Filter pilar program">
                @foreach($pillars as $pillar)
                    <a
                        href="{{ route('program.rincian', ['pilar' => $pillar->slug]) }}"
                        @if($selectedPillar?->is($pillar)) aria-current="page" @endif
                        @class([
                            'program-pillar-filter rounded-lg p-2 text-left',
                            'is-active' => $selectedPillar?->is($pillar),
                        ])
                        @style(['--pillar-color:'.$pillar->color_hex])
                    >
                        <x-pillar-badge :pillar="$pillar->slug" />
                    </a>
                @endforeach
            </nav>

            <div class="grid gap-7 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($programs as $program)
                    <x-program-card :program="$program" />
                @empty
                    <p class="panel col-span-full p-8 text-center text-slate-500">
                        Belum ada program yang telah disetujui untuk pilar ini.
                    </p>
                @endforelse
            </div>

            @if($programs->hasPages())
                <div class="mt-14">
                    {{ $programs->appends(['pilar' => request('pilar')])->links() }}
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
