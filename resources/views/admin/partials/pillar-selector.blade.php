@php
    $pillarSelectorSelectedId = $selectedPillarId ?? null;
    $pillarSelectorReadonly = $readonly ?? false;
    $pillarSelectorSuperAdmin = $isSuperAdmin ?? false;
    $pillarSelectorRequired = $required ?? false;
    $pillarSelectorLabel = $fieldLabel ?? 'Kategori Program';
    $pillarSelectorColors = [
        'sosial' => '#0a4e6f',
        'ekonomi' => '#8f8321',
        'lingkungan' => '#2c691c',
        'hukum-tata-kelola' => '#850e0d',
    ];
@endphp

<div class="reference-row category-row">
    <span>{{ $pillarSelectorLabel }}</span>

    <div class="category-options {{ $pillarSelectorSuperAdmin && $pillarSelectorReadonly ? 'single-category' : '' }}">
        @foreach ($pillars as $pillar)
            @php
                $pillarSelectorBackground = $pillarSelectorSuperAdmin && $pillarSelectorReadonly
                    ? $pillar->color_hex
                    : ($pillarSelectorColors[$pillar->slug] ?? $pillar->color_hex);
            @endphp

            @if (! ($pillarSelectorSuperAdmin && $pillarSelectorReadonly) || $pillarSelectorSelectedId == $pillar->id)
                <label @style(['background-color: ' . $pillarSelectorBackground])>
                    <input
                        type="radio"
                        name="pillar_id"
                        value="{{ $pillar->id }}"
                        @checked(old('pillar_id', $pillarSelectorSelectedId) == $pillar->id)
                        @disabled($pillarSelectorReadonly)
                        @required($pillarSelectorRequired && ! $pillarSelectorReadonly)
                    >
                    <span>{{ $pillar->name }}</span>
                </label>
            @endif
        @endforeach
    </div>
</div>
