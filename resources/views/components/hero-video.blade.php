@props(['video', 'variant' => 'simple', 'title' => null, 'description' => null, 'logo' => false, 'compact' => false])
@php
$slides = [
 ['title'=>'PILAR PEMBANGUNAN SOSIAL','description'=>'Berfokus pada peningkatan kesejahteraan, kesehatan, dan kualitas sumber daya manusia secara komprehensif.'],
 ['title'=>'PILAR PEMBANGUNAN EKONOMI','description'=>'Mendorong pertumbuhan ekonomi inklusif melalui pemberdayaan UMKM dan penciptaan nilai bersama.'],
 ['title'=>'PILAR PEMBANGUNAN LINGKUNGAN','description'=>'Menjaga keberlanjutan lingkungan melalui efisiensi sumber daya dan aksi nyata untuk bumi.'],
 ['title'=>'PILAR HUKUM & TATA KELOLA','description'=>'Memperkuat tata kelola yang akuntabel, transparan, beretika, dan berkelanjutan.'],
];
@endphp
<section class="relative isolate flex {{ $compact ? 'min-h-[300px]' : 'min-h-[430px] md:min-h-[520px]' }} items-center justify-center overflow-hidden text-center text-white" @if($variant === 'animated-pillars') data-pillar-hero data-slides='@json($slides)' @endif>
    <video class="absolute inset-0 -z-20 h-full w-full object-cover" autoplay muted loop playsinline><source src="{{ asset($video) }}" type="video/mp4"></video>
    <div class="absolute inset-0 -z-10 bg-slate-950/50"></div>
    <div class="container-site transition-all duration-300">
        @if($logo)<img src="{{ asset('images/logo/lensa-tjsl-inka.png') }}" alt="LENSA TJSL INKA" class="mx-auto mb-6 w-40 brightness-0 invert md:w-52">@endif
        @if($variant === 'animated-pillars')
            <h1 data-pillar-title class="text-3xl font-extrabold md:text-5xl">{{ $slides[0]['title'] }}</h1>
            <p data-pillar-description class="mx-auto mt-8 max-w-4xl text-base font-semibold leading-relaxed text-white/80 md:text-xl">{{ $slides[0]['description'] }}</p>
        @else
            @if($title)<h1 class="text-4xl font-extrabold md:text-6xl">{{ $title }}</h1>@endif
            @if($description)<p class="mx-auto mt-5 max-w-5xl text-base font-semibold leading-relaxed text-white/85 md:text-xl">{{ $description }}</p>@endif
        @endif
    </div>
</section>
