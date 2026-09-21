<a
    href="{{ route($detailRouteName, $item) }}"
    data-assistance-id="{{ $item->id }}"
    data-assistance-status="{{ $item->status }}"
    class="flex min-h-20 items-center justify-between gap-4 px-4 py-3 text-slate-950 transition hover:bg-slate-50"
>
    <span class="min-w-0">
        <strong class="block truncate text-lg">{{ $item->nama_program_bantuan }}</strong>
        <span class="mt-1 flex flex-wrap gap-x-8 gap-y-1 text-xs">
            <span>{{ $item->updated_at->format('H.i d/m/Y') }}</span>
            @if ($showCreator)
                <span>{{ $item->creator?->name ?? 'Admin tidak diketahui' }}</span>
            @endif
        </span>
    </span>

    @include('admin.partials.status-badge', ['status' => $item->status, 'phaseTwoRejected' => filled($item->fase2_rejected_reason)])
</a>
