@php
    $statusValue = $status ?? 'draft';
    $statusLabel = [
        'draft' => 'Draft',
        'pending_fase1' => 'Waiting',
        'rejected_fase1' => 'Rejected',
        'approved_fase1' => 'Approved',
        'pending_fase2' => 'Waiting Fase 2',
        'completed' => 'Completed',
    ][$statusValue] ?? ucfirst(str_replace('_', ' ', $statusValue));
@endphp
<span class="submission-status-badge" data-status="{{ $statusValue }}">
    <span>{{ $statusLabel }}</span>
    @switch($statusValue)
        @case('draft')
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16.5-.7 4.2 4.2-.7L19 8.5 15.5 5 4 16.5Z"/><path d="m13.8 6.7 3.5 3.5M3.3 20.7h17.4"/></svg>
            @break
        @case('pending_fase1')
        @case('pending_fase2')
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v5h4M9 2h6M12 2v3M18.5 6.5l1.5-1.5"/></svg>
            @break
        @case('rejected_fase1')
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16"/><path d="m8 8 8 8m0-8-8 8"/></svg>
            @break
        @case('approved_fase1')
        @case('completed')
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.7 2.7L16.5 9"/></svg>
            @break
    @endswitch
</span>
