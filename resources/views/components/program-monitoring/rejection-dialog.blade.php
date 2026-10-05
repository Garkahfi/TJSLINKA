                                        <dialog
                                            id="{{ $rejectionDialogId }}"
                                            class="rejection-dialog"
                                            data-rejection-dialog="{{ $rejectionDialogId }}"
                                        >
                                            <div class="rejection-dialog-card">
                                                <div class="rejection-dialog-header">
                                                    <div class="text-left">
                                                        <strong class="block text-lg">Alasan Penolakan</strong>
                                                        <span class="text-xs">{{ $program['title'] }}</span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="rejection-dialog-close"
                                                        data-rejection-close
                                                        aria-label="Tutup informasi penolakan"
                                                    >
                                                        &times;
                                                    </button>
                                                </div>
                                                <div class="rejection-dialog-body text-left">
                                                    <div>
                                                        <span class="rejection-dialog-label">Alasan</span>
                                                        <div class="rejection-dialog-reason">
                                                            {{ $program['rejected_reason'] ?: 'Alasan penolakan belum dicatat.' }}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <span class="rejection-dialog-label">Ditolak oleh</span>
                                                        <span>{{ $program['reviewer_name'] ?: 'Belum tercatat' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="rejection-dialog-label">Waktu penolakan</span>
                                                        <span>{{ $program['reviewed_at'] ?: 'Belum tercatat' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </dialog>
