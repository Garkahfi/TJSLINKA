@props(['planned', 'realized'])

@php
    $percent = $planned > 0 ? min(100, round($realized / $planned * 100)) : 0;
    $remainingPercent = 100 - $percent;
@endphp

<div class="text-center">
    <div class="relative mx-auto h-32 w-64 overflow-hidden">
        <div class="absolute inset-0 rounded-t-full border-34 border-b-0 border-slate-200"></div>
        <div
            class="absolute inset-0 origin-bottom rounded-t-full border-34 border-b-0 border-inka-red"
            @style(['clip-path:inset(0 '.$remainingPercent.'% 0 0)'])
        ></div>
        <strong class="absolute inset-x-0 bottom-0 text-5xl">{{ $percent }}%</strong>
    </div>
    <p class="mt-3 font-bold">Progres Penyerapan Anggaran Program</p>
    <p class="mt-2 text-sm font-semibold">
        Rp {{ number_format($realized, 0, ',', '.') }} dari Rp {{ number_format($planned, 0, ',', '.') }}
    </p>
</div>
