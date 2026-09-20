<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="LENSA TJSL INKA — Monitoring program Tanggung Jawab Sosial dan Lingkungan PT INKA (Persero)">
    <title>{{ $title ?? 'LENSA TJSL INKA' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <a href="#content" class="sr-only focus:not-sr-only">Lewati ke konten utama</a>
    <x-navbar />
    <main id="content">{{ $slot }}</main>
    <x-footer />
    @stack('scripts')
</body>
</html>
