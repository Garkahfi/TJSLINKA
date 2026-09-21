@php
    $selectedPillarSlug = $selectedPillar?->slug ?? null;
    $resultLabel = $resultLabel ?? 'data';
@endphp

<style>
    .submission-filter{display:flex;align-items:end;justify-content:space-between;gap:16px;margin:0 0 24px;padding:14px 16px;border:1px solid #dbe3ef;border-radius:10px;background:#fff;box-shadow:0 2px 7px rgba(15,23,42,.07)}.submission-filter-form{display:flex;align-items:end;gap:10px;min-width:0}.submission-filter-field{display:grid;gap:6px}.submission-filter-field label{color:#475569;font-size:12px;font-weight:700}.submission-filter-field select{min-width:230px;min-height:41px;border:1px solid #94a3b8;border-radius:7px;background:#fff;padding:7px 36px 7px 11px;color:#0f172a;font:600 13px Poppins,sans-serif}.submission-filter-count{color:#475569;font-size:13px;font-weight:600;white-space:nowrap}.submission-filter-reset{display:inline-flex;min-height:41px;align-items:center;border:1px solid #94a3b8;border-radius:7px;padding:7px 12px;color:#334155;font-size:12px;font-weight:700;text-decoration:none}.submission-filter-reset:hover{background:#f1f5f9}@media(max-width:640px){.submission-filter{align-items:stretch;flex-direction:column}.submission-filter-form{align-items:stretch;flex-direction:column}.submission-filter-field select,.submission-filter-reset{width:100%;min-width:0}.submission-filter-count{white-space:normal}}
</style>

<div class="submission-filter" data-status-filter-toolbar>
    <form method="GET" action="{{ route($routeName) }}" class="submission-filter-form">
        @if($selectedPillarSlug)
            <input type="hidden" name="pillar" value="{{ $selectedPillarSlug }}">
        @endif
        <div class="submission-filter-field">
            <label for="{{ $filterId }}">Filter Status</label>
            <select id="{{ $filterId }}" name="status" onchange="this.form.submit()">
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button type="submit" class="submission-filter-reset">Terapkan</button></noscript>
        @if($statusFilter !== 'all')
            <a class="submission-filter-reset" href="{{ route($routeName, array_filter(['pillar' => $selectedPillarSlug])) }}">Reset</a>
        @endif
    </form>
    <span class="submission-filter-count">{{ number_format($resultCount, 0, ',', '.') }} {{ $resultLabel }}</span>
</div>
