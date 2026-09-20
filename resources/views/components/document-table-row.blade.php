@props(['document', 'index'])

<tr class="border-b border-slate-100 last:border-0">
    <td class="px-4 py-3 text-center">{{ $index + 1 }}</td>
    <td class="px-4 py-3">{{ $document['name'] }}</td>
    <td class="px-4 py-3 text-center">
        <span class="inline-grid h-6 w-6 place-items-center rounded border-2 {{ $document['checked'] ? 'border-green-600 text-green-600' : 'border-slate-400 text-slate-400' }}">
            {{ $document['checked'] ? '✓' : '–' }}
        </span>
    </td>
    <td class="px-4 py-3 text-center">
        @if($document['view_url'] ?? null)
            <a
                href="{{ $document['view_url'] }}"
                target="_blank"
                rel="noopener"
                title="Lihat dokumen"
                class="inline-grid h-8 w-8 place-items-center rounded border text-slate-700 hover:bg-slate-100"
                aria-label="Lihat {{ $document['name'] }}"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                    <circle cx="12" cy="12" r="2.75"/>
                </svg>
            </a>
        @else
            <button disabled title="Dokumen belum tersedia" class="cursor-not-allowed rounded border px-2 py-1 text-slate-400">◉</button>
        @endif
    </td>
    <td class="px-4 py-3 text-center">
        @if($document['download_url'] ?? null)
            <a
                href="{{ $document['download_url'] }}"
                title="Unduh dokumen"
                class="inline-grid h-8 w-8 place-items-center rounded border text-slate-700 hover:bg-slate-100"
                aria-label="Unduh {{ $document['name'] }}"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/>
                </svg>
            </a>
        @else
            <button disabled title="Dokumen belum tersedia" class="cursor-not-allowed rounded border px-2 py-1 text-slate-400">⇩</button>
        @endif
    </td>
</tr>
