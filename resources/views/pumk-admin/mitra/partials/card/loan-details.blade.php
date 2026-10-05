            <details class="pumk-card installment-panel receivable-details">
                <summary>Informasi tambahan mitra, pinjaman, dan jaminan</summary>
                <div class="receivable-details-grid">
                    @foreach($contractDocumentFields as $type => $field)
                        @php($document = $contractDocuments->get($type))
                        <div class="receivable-detail">
                            <span>{{ str_replace('Dokumen ', '', $contractDocumentLabels[$type]) }}</span>
                            <strong>{{ $pinjaman->getAttribute($field) ?: '-' }}</strong>
                            @if(filled($pinjaman->getAttribute($field)))
                                @if($document)
                                    <small class="receivable-note">{{ $document->nama_file_asli }}</small>
                                    <div class="contract-document-actions">
                                        <a class="small-action" target="_blank" rel="noopener" href="{{ route('pumk-admin.mitra.dokumen.view', [$mitra, $pinjaman, $document]) }}">Lihat</a>
                                        <a class="small-action" href="{{ route('pumk-admin.mitra.dokumen.download', [$mitra, $pinjaman, $document]) }}">Unduh</a>
                                        @if($loanIsActive)
                                            <form method="POST" action="{{ route('pumk-admin.mitra.dokumen.destroy', [$mitra, $pinjaman, $document]) }}" onsubmit="return confirm('Hapus dokumen kontrak ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="small-action danger">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <small class="receivable-note">Belum ada dokumen</small>
                                @endif
                            @endif
                        </div>
                    @endforeach
                    <div class="receivable-detail"><span>Jenis / Sektor Usaha</span><strong>{{ $mitra->jenis_usaha ?: '-' }}{{ $mitra->sektorUsaha?->nama ? ' / '.$mitra->sektorUsaha->nama : '' }}</strong></div>
                    <div class="receivable-detail"><span>Alamat</span><strong>{{ $mitra->alamat ?: '-' }}</strong></div>
                    <div class="receivable-detail"><span>Jenis Jaminan</span><strong>{{ $pinjaman->jenis_jaminan ?: '-' }}</strong></div>
                    <div class="receivable-detail"><span>Nomor Polisi / BPKB</span><strong>{{ collect([$pinjaman->jaminan_no_pol, $pinjaman->jaminan_no_bpkb])->filter()->implode(' / ') ?: '-' }}</strong></div>
                    <div class="receivable-detail"><span>Sertifikat / Atas Nama</span><strong>{{ collect([$pinjaman->jaminan_no_sertifikat, $pinjaman->jaminan_atas_nama])->filter()->implode(' / ') ?: '-' }}</strong></div>
                </div>
            </details>
