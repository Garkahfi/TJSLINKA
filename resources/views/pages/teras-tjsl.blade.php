<x-layouts.app title="Teras TJSL — LENSA TJSL INKA">
    <x-hero-video
        video="videos/waterfall-bg.mp4"
        variant="simple"
        title="TERAS TJSL"
        description="Tanggung Jawab Sosial dan Lingkungan PT INKA (Persero)"
        compact
    />

    @php
        $regularPackages = $packages->filter(fn ($package) => blank($package->catatan_khusus));
        $specialPackages = $packages->filter(fn ($package) => filled($package->catatan_khusus));
    @endphp

    <style>
        .teras-package-showcase {
            width: min(100%, 980px);
            margin-inline: auto;
            font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .teras-package-intro {
            margin-bottom: 24px;
            padding: 16px 20px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background: #fff;
            box-shadow: 0 2px 4px rgb(15 23 42 / 8%);
            color: #475569;
            font-size: 12px;
            line-height: 1.55;
        }

        .teras-package-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .teras-package-card {
            overflow: hidden;
            border: 1px solid #d4d9e1;
            border-radius: 5px;
            background: #fff;
            box-shadow: 0 2px 4px rgb(15 23 42 / 12%);
        }

        .teras-package-card__photo {
            display: flex;
            height: 225px;
            align-items: flex-end;
            justify-content: center;
            padding: 12px 12px 0;
            background: #fff;
        }

        .teras-package-card__photo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center bottom;
        }

        .teras-package-card__body {
            min-height: 194px;
            padding: 12px 16px 14px;
            border-top: 1px solid #eef0f3;
        }

        .teras-package-card__heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            font-size: 12px;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }

        .teras-package-card__heading h3,
        .teras-package-card__heading strong {
            font-weight: 700;
        }

        .teras-package-list {
            margin-top: 9px;
            color: #172033;
            font-size: 10px;
            font-weight: 400;
            line-height: 1.22;
            letter-spacing: -0.005em;
        }

        .teras-package-list li {
            display: flex;
            gap: 5px;
            margin: 0;
            padding: 0;
        }

        .teras-package-list li + li {
            margin-top: 2px;
        }

        .teras-package-d {
            display: grid;
            grid-template-columns: 29% 45% 26%;
            margin-top: 22px;
            overflow: hidden;
            border: 1px solid #d4d9e1;
            border-radius: 5px;
            background: #fff;
            box-shadow: 0 2px 4px rgb(15 23 42 / 12%);
        }

        .teras-package-d__photo {
            display: flex;
            min-height: 290px;
            align-items: flex-end;
            justify-content: center;
            padding: 16px 12px 0;
        }

        .teras-package-d__photo img {
            width: 100%;
            height: 100%;
            max-height: 285px;
            object-fit: contain;
            object-position: center bottom;
        }

        .teras-package-d__details,
        .teras-package-d__contact {
            padding: 17px 18px;
            border-left: 1px solid #1e293b;
        }

        .teras-package-d__columns {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-top: 13px;
            color: #172033;
            font-size: 9px;
            line-height: 1.25;
            letter-spacing: -0.005em;
        }

        .teras-package-d__columns li + li { margin-top: 2px; }

        .teras-package-d__contact {
            font-size: 9px;
            line-height: 1.32;
        }

        .teras-package-d__contact > p {
            margin: 0;
        }

        .teras-package-d__contact > p + p {
            margin-top: 8px !important;
        }

        .teras-package-d__contact address {
            margin-top: 12px !important;
        }

        .teras-package-d__contact address p {
            margin: 0;
            align-items: center;
            gap: 8px;
            line-height: 1.25;
        }

        .teras-package-d__contact address p + p {
            margin-top: 7px;
        }

        .teras-product-catalog {
            width: min(100%, 1080px);
            margin: 36px auto 0;
        }

        .teras-product-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px 14px;
        }

        .teras-product-grid article {
            min-width: 0;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgb(15 23 42 / 18%);
        }

        .teras-product-grid article img,
        .teras-product-grid article [role="img"] {
            aspect-ratio: 1 / 1;
        }

        .teras-content-section {
            padding-block: 48px 32px;
        }

        @media (min-width: 761px) {
            .teras-content-section {
                padding-block: 64px 40px;
            }
        }

        @media (max-width: 760px) {
            .teras-package-grid { gap: 10px; }
            .teras-package-card__photo { height: 160px; padding-inline: 6px; }
            .teras-package-card__body { min-height: 165px; padding: 10px; }
            .teras-package-card__heading { display: block; font-size: 10px; }
            .teras-package-card__heading strong { display: block; margin-top: 3px; font-size: 9px; }
            .teras-package-list { margin-top: 7px; font-size: 8px; line-height: 1.18; }
            .teras-package-d { grid-template-columns: 29% 45% 26%; }
            .teras-package-d__photo { min-height: 225px; padding-inline: 7px; }
            .teras-package-d__details, .teras-package-d__contact { padding: 10px; }
            .teras-package-d__columns { gap: 8px; font-size: 7px; }
            .teras-package-d__contact { font-size: 7px; }
            .teras-product-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px 10px; }
            .teras-product-grid article > div:last-child { padding: 7px; }
            .teras-product-grid article h3 { font-size: 8px; }
            .teras-product-grid article p { font-size: 7px; }
        }

        @media (max-width: 520px) {
            .teras-package-showcase { width: 100%; }
            .teras-package-grid { grid-template-columns: 1fr; }
            .teras-package-card__photo { height: 250px; padding-inline: 15px; }
            .teras-package-card__body { min-height: 0; padding: 16px; }
            .teras-package-card__heading { display: flex; font-size: 13px; }
            .teras-package-card__heading strong { display: inline; margin-top: 0; font-size: 12px; }
            .teras-package-list { font-size: 11px; }
            .teras-package-d { grid-template-columns: 1fr; }
            .teras-package-d__photo { min-height: 270px; }
            .teras-package-d__details, .teras-package-d__contact { border-top: 1px solid #1e293b; border-left: 0; padding: 16px; }
            .teras-package-d__columns, .teras-package-d__contact { font-size: 10px; }
            .teras-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .teras-product-grid article > div:last-child { padding: 10px; }
            .teras-product-grid article h3 { font-size: 12px; }
            .teras-product-grid article p { font-size: 10px; }
        }
    </style>

    <section class="teras-content-section">
        <div class="container-site">
            <div class="teras-package-showcase">
            <h2 class="mb-8 text-center text-2xl font-extrabold tracking-tight md:text-3xl">Paket Produk Teras TJSL</h2>

            <p class="teras-package-intro">
                Teras TJSL menghadirkan produk-produk unggulan mitra binaan PT INKA. Pilih paket siap kirim
                atau susun paket khusus sesuai kebutuhan untuk mendukung pertumbuhan UMKM lokal.
            </p>

            @if ($packages->isEmpty())
                <p class="teras-package-intro text-center">Belum ada paket aktif yang dapat ditampilkan.</p>
            @endif

            <div class="teras-package-grid">
                @foreach ($regularPackages as $package)
                    <article class="teras-package-card">
                        <div class="teras-package-card__photo">
                            @if ($package->photo_url)
                                <img src="{{ $package->photo_url }}" alt="{{ $package->nama_paket }} produk UMKM Teras TJSL">
                            @else
                                <span class="text-xs text-slate-400">Foto paket belum tersedia</span>
                            @endif
                        </div>

                        <div class="teras-package-card__body">
                            <div class="teras-package-card__heading">
                                <h3 class="font-extrabold">{{ $package->nama_paket }}</h3>
                                <strong class="shrink-0 text-inka-red">{{ $package->formatted_harga }}</strong>
                            </div>

                            <ul class="teras-package-list">
                                @foreach ($package->isi_paket as $item)
                                    <li class="flex gap-2"><span aria-hidden="true">•</span><span>{{ $item }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </article>
                @endforeach
            </div>

            @foreach ($specialPackages as $package)
                @php
                    $packageItems = collect($package->isi_paket ?? []);
                    $packageColumns = $packageItems->chunk(max(1, (int) ceil($packageItems->count() / 2)));
                @endphp

                <article class="teras-package-d">
                    <div class="teras-package-d__photo">
                        @if ($package->photo_url)
                            <img src="{{ $package->photo_url }}" alt="{{ $package->nama_paket }} produk UMKM Teras TJSL">
                        @else
                            <span class="text-xs text-slate-400">Foto paket belum tersedia</span>
                        @endif
                    </div>

                    <div class="teras-package-d__details">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <h3 class="font-extrabold">{{ $package->nama_paket }}</h3>
                            <strong class="text-sm text-inka-red">{{ $package->formatted_harga }}</strong>
                        </div>

                        <div class="teras-package-d__columns">
                            @foreach ($packageColumns as $column)
                                <ul>
                                    @foreach ($column as $item)
                                        <li class="flex gap-2"><span aria-hidden="true">•</span><span>{{ $item }}</span></li>
                                    @endforeach
                                </ul>
                            @endforeach
                        </div>
                    </div>

                    <aside class="teras-package-d__contact">
                        <p class="whitespace-pre-line">{{ $package->catatan_khusus }}</p>
                        <p class="mt-5">Untuk melakukan pemesanan, bisa menghubungi kami melalui media sosial yang tertera.</p>

                        <address class="mt-6 space-y-3 not-italic">
                            <p class="flex gap-3"><span aria-hidden="true">@</span><span>@cafegerumadalamadiun</span></p>
                            <p class="flex gap-3"><span aria-hidden="true">☎</span><span>085188317028</span></p>
                            <p class="flex gap-3"><span aria-hidden="true">▣</span><span>https://padiUMKM.id</span></p>
                        </address>
                    </aside>
                </article>
            @endforeach
            </div>

            <div class="teras-product-catalog">
                <div class="teras-product-grid">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                @if ($products->isEmpty())
                    <p class="teras-package-intro text-center">Belum ada produk aktif yang dapat ditampilkan.</p>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
