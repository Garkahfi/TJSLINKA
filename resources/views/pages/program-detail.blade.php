<x-layouts.app :title="$program['title'].' — LENSA TJSL INKA'">
    <section class="relative isolate flex min-h-107.5 items-end overflow-hidden pb-12 text-white">
        <img src="{{ asset($program['cover_image']) }}" alt="{{ $program['title'] }}" class="absolute inset-0 -z-20 h-full w-full object-cover">
        <div class="absolute inset-0 -z-10 bg-linear-to-t from-black via-black/20 to-transparent"></div>
        <div class="container-site">
            <h1 class="mx-auto max-w-5xl text-center text-3xl font-extrabold leading-tight md:text-5xl">{{ $program['title'] }}</h1>
        </div>
    </section>

    <x-realisasi-gallery :photos="$program['gallery']" label="Dokumentasi realisasi program" />

    <section class="pb-16 pt-6">
        <div class="container-site">
            <p class="text-justify leading-8 text-slate-700">
                <strong>{{ $program['title'] }}</strong> {{ $program['description'] }}
            </p>

            <div class="mt-10 grid items-center gap-10 lg:grid-cols-2">
                <div class="mx-auto w-full max-w-md rounded-xl border-2 border-slate-500 p-2">
                    <div class="rounded-lg bg-white p-4 text-xl font-semibold">✓ &nbsp; Sasaran <span class="mt-1 block pl-8 text-sm font-normal text-slate-500">{{ $program['sasaran'] }}</span></div>
                    <div class="my-2 rounded-lg bg-white p-4 text-xl font-semibold">⌖ &nbsp; Lokasi <span class="mt-1 block pl-8 text-sm font-normal text-slate-500">{{ $program['lokasi'] }}</span></div>
                    <div class="rounded-lg bg-white p-4 text-xl font-semibold">♢ &nbsp; Mitra <span class="mt-1 block pl-8 text-sm font-normal text-slate-500">{{ $program['mitra'] }}</span></div>
                </div>
                <x-progress-gauge :planned="$program['budget_planned']" :realized="$program['budget_realized']" />
            </div>

            @if($program['goals'] !== [])
                <h2 class="section-title mb-9 mt-16">Tujuan Program</h2>
                @foreach($program['goals'] as $goal)
                    <article @class([
                        'panel mb-6 grid overflow-hidden',
                        'md:grid-cols-[280px_1fr]' => $goal['image'],
                    ])>
                        @if($goal['image'])
                            <img src="{{ asset($goal['image']) }}" alt="Tujuan program {{ $loop->iteration }}" class="h-full min-h-48 w-full object-cover">
                        @endif
                        <p class="p-6 text-justify leading-7 text-slate-700">{{ $goal['description'] }}</p>
                    </article>
                @endforeach
            @endif

            <h2 class="section-title mb-9 mt-16">Kelengkapan Dokumen Program</h2>
            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="w-full min-w-175 text-sm">
                    <thead class="bg-inka-navy text-white">
                        <tr><th class="p-4">No</th><th class="p-4 text-left">Nama Dokumen</th><th class="p-4">Checklist</th><th class="p-4">Lihat</th><th class="p-4">Unduh</th></tr>
                    </thead>
                    <tbody>
                        @forelse($program['documents'] as $document)
                            <x-document-table-row :document="$document" :index="$loop->index" />
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-slate-500">Dokumen belum tersedia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.app>
