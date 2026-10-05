    @if($monitoringPrograms->isEmpty())
        <p class="rounded-xl border bg-white p-8 text-center text-slate-500">
            @if($searchKeyword !== '')
                Tidak ada program yang cocok dengan kata kunci "{{ $searchKeyword }}".
            @else
                {{ $selectedYear ? 'Tidak ada program pada tahun '.$selectedYear.'.' : $emptyMessage }}
            @endif
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table class="w-full min-w-255 text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="p-4">No</th>
                        <th class="p-4 text-left">Nama Program</th>
                        <th class="p-4" colspan="6">Kelengkapan Dokumen</th>
                        @if($showRejectedInfo)
                            <th class="p-4">Alasan</th>
                        @endif
                    </tr>
                    <tr>
                        <th></th>
                        <th></th>
                        @foreach ($documentColumns as $column)
                            <th class="p-2">{{ $column['code'] }}</th>
                        @endforeach
                        @if($showRejectedInfo)
                            <th aria-label="Informasi penolakan"></th>
                        @endif
                    </tr>
                </thead>

                @foreach ($groups as $pillar => $group)
                    @php
                        $groupPrograms = $monitoringPrograms->where('pillar', $pillar)->values();
                    @endphp

                    <tbody data-pillar-group="{{ $pillar }}">
                        <tr>
                            <th
                                colspan="{{ $tableColumnCount }}"
                                class="p-2 text-white"
                                @style(['background:'.$group['color']])
                            >
                                {{ $group['label'] }}
                            </th>
                        </tr>

                        @foreach ($groupPrograms as $program)
                            @include('components.program-monitoring.program-row')
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif
