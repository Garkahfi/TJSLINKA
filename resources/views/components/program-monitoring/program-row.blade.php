                            <tr
                                class="border-b align-middle hover:bg-slate-50"
                                data-program-kind="{{ $program['jenis'] }}"
                                data-program-id="{{ $program['id'] }}"
                                data-program-status="{{ $program['status'] }}"
                                data-program-year="{{ $program['monitoring_year'] ?? (! empty($program['sort_timestamp']) ? date('Y', $program['sort_timestamp']) : '') }}"
                                @if($program['jenis'] === 'internal')
                                    data-cooperation-kind="{{ $program['jenis_kerjasama'] }}"
                                @endif
                            >
                                <td class="p-3 text-center">{{ $loop->iteration }}</td>
                                <td class="p-3">
                                    <span class="program-kind-badge {{ $program['jenis'] }}">
                                        {{ match ($program['jenis']) {
                                            'internal' => 'Program TJSL',
                                            'csr' => 'Bantuan TJSL',
                                            default => ucfirst($program['jenis']),
                                        } }}
                                    </span>

                                    @if($program['jenis'] === 'internal')
                                        <span class="program-kind-badge cooperation">
                                            {{ $program['jenis_kerjasama'] === 'non_pks' ? 'NON-PKS' : 'PKS' }}
                                        </span>
                                    @endif

                                    @php
                                        $isRejected = in_array(
                                            $program['status'],
                                            ['rejected_fase1'],
                                            true,
                                        );
                                    @endphp

                                    @if($isRejected)
                                        <span class="program-kind-badge rejected">Ditolak</span>
                                    @endif

                                    @php
                                        // Setiap controller menentukan sendiri apakah baris boleh
                                        // dibuka. Nilai null sengaja dipertahankan supaya status yang
                                        // belum publik (termasuk yang ditolak) tidak menghasilkan
                                        // tautan detail yang berakhir 404.
                                        $detailUrl = $program['detail_url'] ?? null;
                                    @endphp

                                    @if($detailUrl)
                                        <a class="font-medium hover:text-inka-red" href="{{ $detailUrl }}">
                                            {{ $program['title'] }}
                                        </a>
                                    @else
                                        <span class="font-medium">{{ $program['title'] }}</span>
                                    @endif

                                    @if($showCreator && ! empty($program['creator_name']))
                                        <p class="monitoring-meta">Dibuat oleh {{ $program['creator_name'] }}</p>
                                    @endif
                                </td>

                                @foreach ($documentColumns as $column)
                                    @php
                                        $notApplicable = (
                                            $program['jenis'] === 'internal'
                                            && $program['jenis_kerjasama'] === 'non_pks'
                                            && in_array($column['code'], ['C', 'D', 'E'], true)
                                        ) || (
                                            $program['jenis'] === 'csr'
                                            && in_array($column['code'], ['C', 'D', 'E'], true)
                                        );
                                        $hasDocument = ! $notApplicable
                                            && (
                                                in_array($column['code'], $program['document_codes'] ?? [], true)
                                                || in_array($column['type'], $program['document_types'] ?? [], true)
                                            );
                                        $isBastInProgress = ! $notApplicable
                                            && in_array($program['jenis'], ['internal', 'csr'], true)
                                            && $column['code'] === 'F'
                                            && $program['status'] === 'approved_fase1';
                                        $documentState = $notApplicable
                                            ? 'na'
                                            : ($isBastInProgress ? 'in-progress' : ($hasDocument ? 'complete' : 'incomplete'));
                                    @endphp
                                    <td
                                        class="p-2 text-center"
                                        data-document-column="{{ $column['code'] }}"
                                        data-document-state="{{ $documentState }}"
                                    >
                                        @if($notApplicable)
                                            <span
                                                class="document-na"
                                                title="Dokumen tidak berlaku untuk jenis program ini"
                                                aria-label="{{ $column['code'] }}: tidak berlaku"
                                            >
                                                &ndash;
                                            </span>
                                        @elseif($isBastInProgress)
                                            <span
                                                class="document-check in-progress"
                                                title="{{ ! empty($program['rejected_reason'])
                                                    ? 'On Progress - menunggu perbaikan dan unggah ulang dokumen BAST'
                                                    : 'On Progress - menunggu upload dokumen BAST' }}"
                                                aria-label="{{ $column['code'] }}: on progress, menunggu upload dokumen BAST"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="9"></circle>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                                                </svg>
                                            </span>
                                        @else
                                            <span
                                                class="document-check {{ $hasDocument ? 'complete' : 'incomplete' }}"
                                                title="{{ $hasDocument ? 'Dokumen lengkap' : 'Dokumen belum lengkap' }}"
                                                aria-label="{{ $column['code'] }}: {{ $hasDocument ? 'lengkap' : 'belum lengkap' }}"
                                            >
                                                {!! $hasDocument ? '&#10003;' : '&times;' !!}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach

                                @if($showRejectedInfo)
                                    <td class="p-2 text-center">
                                        @if($isRejected)
                                            @php
                                                $rejectionDialogId = 'rejection-'.$program['jenis'].'-'.$program['id'];
                                            @endphp
                                        <button
                                            type="button"
                                            class="rejection-open"
                                            data-rejection-open="{{ $rejectionDialogId }}"
                                            title="{{ $program['rejected_reason'] ?: 'Lihat alasan penolakan' }}"
                                            aria-label="Lihat alasan penolakan {{ $program['title'] }}"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <circle cx="12" cy="12" r="9"></circle>
                                                <path stroke-linecap="round" d="M12 10.5v6M12 7.5h.01"></path>
                                            </svg>
                                        </button>

                                        @include('components.program-monitoring.rejection-dialog')
                                        @endif
                                    </td>
                                @endif
                            </tr>
