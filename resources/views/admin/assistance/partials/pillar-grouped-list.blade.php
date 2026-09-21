@php
    $showCreator = $showCreator ?? false;
    $emptyMessage = $emptyMessage ?? 'Belum ada Bantuan TJSL.';
    $hasItems = $bantuanTanpaPilar->isNotEmpty()
        || $bantuanPerPilar->contains(fn ($pillar) => $pillar->bantuanCsr->isNotEmpty());
@endphp

<div class="space-y-6" data-assistance-pillar-list>
    @foreach ($bantuanPerPilar as $pillar)
        @continue($pillar->bantuanCsr->isEmpty())

        @php
            $pillarColor = $pillar->color_hex ?: match ($pillar->slug) {
                'sosial' => '#2563eb',
                'ekonomi' => '#f59e0b',
                'lingkungan' => '#16a34a',
                'hukum-tata-kelola' => '#dc2626',
                default => '#64748b',
            };
        @endphp

        <section data-pillar-group="{{ $pillar->slug }}">
            <div
                class="flex min-h-11 items-center justify-between gap-4 rounded-t-md px-4 py-2 font-bold text-white"
                @style(['background-color: ' . $pillarColor])
            >
                <span>PILAR PEMBANGUNAN {{ strtoupper($pillar->name) }}</span>
                <span class="text-xs font-semibold">{{ $pillar->bantuanCsr->count() }} Bantuan</span>
            </div>

            <div class="divide-y divide-slate-200 overflow-hidden rounded-b-md border border-t-0 border-slate-900 bg-white">
                @foreach ($pillar->bantuanCsr as $item)
                    @include('admin.assistance.partials.pillar-grouped-row', [
                        'item' => $item,
                        'detailRouteName' => $detailRouteName,
                        'showCreator' => $showCreator,
                    ])
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($bantuanTanpaPilar->isNotEmpty())
        <section data-pillar-group="belum-ditentukan">
            <div class="flex min-h-11 items-center justify-between gap-4 rounded-t-md bg-slate-500 px-4 py-2 font-bold text-white">
                <span>PILAR BELUM DITENTUKAN</span>
                <span class="text-xs font-semibold">{{ $bantuanTanpaPilar->count() }} Bantuan</span>
            </div>

            <div class="divide-y divide-slate-200 overflow-hidden rounded-b-md border border-t-0 border-slate-900 bg-white">
                @foreach ($bantuanTanpaPilar as $item)
                    @include('admin.assistance.partials.pillar-grouped-row', [
                        'item' => $item,
                        'detailRouteName' => $detailRouteName,
                        'showCreator' => $showCreator,
                    ])
                @endforeach
            </div>
        </section>
    @endif

    @unless ($hasItems)
        <p class="rounded-xl border bg-white p-8 text-center text-slate-500">{{ $emptyMessage }}</p>
    @endunless
</div>
