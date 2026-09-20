@props(['pillar', 'label' => null])

@php
    $pillars = [
        'sosial' => ['#2f6de9', 'Sosial'],
        'ekonomi' => ['#f59e0b', 'Ekonomi'],
        'lingkungan' => ['#16a34a', 'Lingkungan'],
        'hukum-tata-kelola' => ['#ef272d', 'Hukum & Tata Kelola'],
    ];

    [$color, $name] = $pillars[$pillar] ?? ['#64748b', ucfirst($pillar)];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 font-semibold']) }}>
    <i class="h-4 w-4 rounded-full" @style(['background:'.$color])></i>
    {{ $label ?? $name }}
</span>
