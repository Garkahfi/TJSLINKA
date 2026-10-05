    <div class="monitoring-document-legend panel grid gap-3 p-5 text-sm md:grid-cols-3">
        @foreach (array_chunk($documentColumns, 2) as $columnPair)
            <p>
                @foreach ($columnPair as $column)
                    {{ $column['code'] }} : {{ $column['label'] }}@if(! $loop->last)<br>@endif
                @endforeach
            </p>
        @endforeach
    </div>

    <div class="monitoring-kind-legend panel my-5 flex flex-wrap justify-center gap-8 p-4">
        <span class="flex items-center gap-2">
            <i class="h-6 w-6 rounded" style="background-color: #dc2626"></i>
            Program TJSL
        </span>
        @if($showCsrLegend)
            <span class="flex items-center gap-2">
                <i class="h-6 w-6 rounded" style="background-color: #7c3aed"></i>
                Bantuan TJSL
            </span>
        @endif
    </div>

    <div class="monitoring-year-legend panel mb-5 flex justify-center gap-3 p-3" aria-label="Filter tahun monitoring">
        @foreach ($yearOptions as $year)
            @php
                $isSelectedYear = $selectedYear === $year;
                $yearUrl = $isSelectedYear
                    ? $clearYearUrl
                    : $monitoringBaseUrl.'?'.http_build_query([...$filterQuery, 'year' => $year]);
            @endphp
            <a
                href="{{ $yearUrl }}"
                data-monitoring-year="{{ $year }}"
                aria-pressed="{{ $isSelectedYear ? 'true' : 'false' }}"
                title="{{ $isSelectedYear ? 'Tampilkan semua tahun' : 'Tampilkan tahun '.$year }}"
                @class([
                    'rounded-md border-2 border-slate-500 px-4 font-bold',
                    'bg-slate-200' => $isSelectedYear,
                ])
            >
                {{ $year }}
            </a>
        @endforeach
    </div>

    @if($showSearch)
        <form method="GET" action="{{ $monitoringBaseUrl }}" class="monitoring-search panel mb-5 p-4">
            @if($selectedYear)
                <input type="hidden" name="year" value="{{ $selectedYear }}">
            @endif
            <label class="sr-only" for="program-monitoring-search">Cari program berdasarkan kata kunci</label>
            <div class="monitoring-search-field">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path stroke-linecap="round" d="m16.2 16.2 4 4"></path>
                </svg>
                <input
                    id="program-monitoring-search"
                    name="keyword"
                    type="search"
                    inputmode="search"
                    autocomplete="off"
                    maxlength="100"
                    value="{{ $searchKeyword }}"
                    placeholder="Cari nama program atau nomor registrasi..."
                >
                @if($searchKeyword !== '')
                    <a
                        href="{{ $monitoringBaseUrl.($selectedYear ? '?year='.$selectedYear : '') }}"
                        class="monitoring-search-clear"
                    >
                        Hapus
                    </a>
                @endif
                <button type="submit" class="monitoring-search-submit">Cari</button>
            </div>
            <p class="monitoring-search-hint">
                Pencarian hanya dijalankan saat tombol Cari ditekan agar tidak membebani database pada setiap ketikan.
            </p>
        </form>
    @endif
