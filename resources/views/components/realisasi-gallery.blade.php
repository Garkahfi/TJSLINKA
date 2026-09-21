@props([
    'photos' => [],
    'label' => 'Dokumentasi realisasi TJSL',
])

@php
    $galleryPhotos = collect($photos)
        ->map(function ($photo): array {
            if (is_array($photo)) {
                return [
                    'url' => $photo['url'] ?? null,
                    'caption' => $photo['caption'] ?? null,
                ];
            }

            return ['url' => asset($photo), 'caption' => null];
        })
        ->filter(fn (array $photo): bool => filled($photo['url']))
        ->unique('url')
        ->values();
    $duration = max(18, $galleryPhotos->count() * 6);
@endphp

@if($galleryPhotos->isNotEmpty())
    <section class="realisasi-gallery py-5" aria-label="{{ $label }}">
        <div class="realisasi-gallery-viewport" tabindex="0">
            <div class="realisasi-gallery-track" @style(['--realisasi-gallery-duration:'.$duration.'s'])>
                @foreach([false, true] as $duplicate)
                    <div class="realisasi-gallery-group" @if($duplicate) aria-hidden="true" @endif>
                        @foreach($galleryPhotos as $photo)
                            <figure class="realisasi-gallery-card">
                                <img
                                    src="{{ $photo['url'] }}"
                                    alt="{{ $duplicate ? '' : ($photo['caption'] ?: $label.' '.$loop->iteration) }}"
                                    class="realisasi-gallery-image"
                                    loading="lazy"
                                >
                                @if(filled($photo['caption']))
                                    <figcaption class="realisasi-gallery-caption">{{ $photo['caption'] }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
