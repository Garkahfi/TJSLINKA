<x-layouts.app :title="$program['title'].' — LENSA TJSL INKA'">
    <section class="relative isolate flex min-h-[430px] items-end overflow-hidden pb-12 text-white">
        @if($program['cover_image'])
            <img src="{{ $program['cover_image'] }}" alt="{{ $program['title'] }}" class="absolute inset-0 -z-20 h-full w-full object-cover">
        @else
            <div class="absolute inset-0 -z-20 bg-gradient-to-br from-slate-700 to-slate-950" aria-hidden="true"></div>
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black via-black/25 to-transparent"></div>
        <div class="container-site">
            <div class="mb-4 flex justify-center">
                <span class="rounded-full bg-[#7c3aed] px-4 py-1.5 text-sm font-bold shadow">Bantuan TJSL</span>
            </div>
            <h1 class="mx-auto max-w-5xl text-center text-3xl font-extrabold leading-tight md:text-5xl">{{ $program['title'] }}</h1>
        </div>
    </section>

    <x-realisasi-gallery :photos="$program['gallery']" label="Dokumentasi realisasi bantuan TJSL" />

    <section class="pb-16 pt-6">
        <div class="container-site">
            <p class="text-justify leading-8 text-slate-700">
                <strong>{{ $program['title'] }}</strong> {{ $program['description'] }}
            </p>

            <div class="mt-12">
                <x-progress-gauge :planned="$program['budget_planned']" :realized="$program['budget_realized']" />
            </div>

            @if($program['targets'] !== [])
                <h2 class="section-title mb-9 mt-16">Tujuan Capaian Penerima Bantuan</h2>
                <div class="space-y-6">
                    @foreach($program['targets'] as $target)
                        <article @class([
                            'panel grid min-h-32 overflow-hidden',
                            'md:grid-cols-[280px_1fr]' => $target['image'],
                        ])>
                            @if($target['image'])
                                <img src="{{ $target['image'] }}" alt="Dokumentasi tujuan bantuan {{ $loop->iteration }}" class="h-full min-h-32 w-full object-cover">
                            @endif
                            <p class="p-6 leading-7 text-slate-700">{{ $target['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            @endif

            <h2 class="section-title mb-9 mt-16">Rincian Penerimaan Bantuan TJSL</h2>
            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="bg-inka-navy text-white">
                        <tr>
                            <th class="p-4">No</th>
                            <th class="p-4 text-left">Rincian Kegiatan</th>
                            <th class="p-4 text-left">Penerima Bantuan</th>
                            <th class="p-4 text-left">Jenis Bantuan</th>
                            <th class="p-4 text-left">Kuantitas</th>
                            <th class="p-4 text-right">Nominal Bantuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($program['details'] as $detail)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="p-4 text-center">{{ $loop->iteration }}</td>
                                <td class="p-4">{{ $detail['activity'] }}</td>
                                <td class="p-4">{{ $detail['recipient'] }}</td>
                                <td class="p-4">{{ $detail['assistance_type'] }}</td>
                                <td class="p-4">{{ $detail['quantity'] }}</td>
                                <td class="p-4 text-right">Rp {{ number_format($detail['nominal'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-slate-500">Rincian bantuan belum tersedia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="section-title mb-9 mt-16">Kelengkapan Dokumen Bantuan TJSL</h2>
            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-inka-navy text-white">
                        <tr>
                            <th class="p-4">No</th>
                            <th class="p-4 text-left">Jenis Dokumen</th>
                            <th class="p-4 text-left">Nama Dokumen</th>
                            <th class="p-4">Checklist</th>
                            <th class="p-4">Lihat</th>
                            <th class="p-4">Unduh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($program['documents'] as $document)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="px-4 py-3 text-center">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3">{{ $document['type'] }}</td>
                                <td class="px-4 py-3">{{ $document['name'] }}</td>
                                <td class="px-4 py-3 text-center"><span class="inline-grid h-6 w-6 place-items-center rounded border-2 border-green-600 text-green-600">✓</span></td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ $document['view_url'] }}" target="_blank" rel="noopener" class="inline-grid h-8 w-8 place-items-center rounded border text-slate-700 hover:bg-slate-100" aria-label="Lihat {{ $document['name'] }}">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ $document['download_url'] }}" class="inline-grid h-8 w-8 place-items-center rounded border text-slate-700 hover:bg-slate-100" aria-label="Unduh {{ $document['name'] }}">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-slate-500">Dokumen bantuan belum tersedia.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.app>
