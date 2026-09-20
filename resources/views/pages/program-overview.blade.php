<x-layouts.app title="Overview Program — LENSA TJSL INKA">
    <x-hero-video video="videos/people-talking-bg.mp4" variant="animated-pillars" />

    <section class="py-16">
        <div class="container-site">
            <h2 class="section-title mb-8">Overview Program TJSL INKA</h2>

            <div
                id="program-monitoring-live"
                data-monitoring-endpoint="{{ route('program.overview.monitoring') }}"
            >
                @include('components.program-monitoring-table', [
                    'programs' => $programs,
                    'emptyMessage' => 'Belum ada program yang telah disetujui Super Admin.',
                    'showSearch' => true,
                    'showCsrLegend' => true,
                    'searchKeyword' => $searchKeyword,
                    'monitoringBaseUrl' => route('program.overview'),
                    'monitoringSignature' => $monitoringSignature,
                ])
            </div>

            <h2 class="section-title mb-9 mt-16">Berita TJSL INKA</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($news as $item)
                    <article class="panel overflow-hidden">
                        <img
                            src="{{ asset($item['image']) }}"
                            alt="{{ $item['title'] }}"
                            class="aspect-square w-full object-cover"
                            loading="lazy"
                        >
                        <div class="p-4">
                            <h3 class="font-bold leading-relaxed">{{ $item['title'] }}</h3>
                            <p class="mt-5 text-sm text-slate-500">{{ $item['source'] }}</p>
                            <p class="text-sm text-slate-500">{{ $item['date'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    @push('scripts')
        <script>
            (function () {
                const host = document.getElementById('program-monitoring-live');
                if (!host) return;

                const endpoint = host.dataset.monitoringEndpoint;
                const pollInterval = 10000;
                let inFlight = false;

                function monitoringRoot() {
                    return host.querySelector('[data-program-monitoring]');
                }

                function searchHasPendingInput(root) {
                    const input = root?.querySelector('#program-monitoring-search');
                    return input && (document.activeElement === input || input.value !== input.defaultValue);
                }

                async function refreshMonitoring() {
                    const root = monitoringRoot();

                    if (
                        inFlight
                        || document.visibilityState !== 'visible'
                        || searchHasPendingInput(root)
                    ) {
                        return;
                    }

                    const params = new URLSearchParams(window.location.search);
                    const signature = root?.dataset.monitoringSignature;
                    if (signature) params.set('signature', signature);

                    inFlight = true;

                    try {
                        const response = await fetch(`${endpoint}?${params.toString()}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            cache: 'no-store',
                        });

                        if (response.status === 204) return;
                        if (!response.ok) return;

                        const payload = await response.json();
                        if (!payload.html || payload.signature === signature) return;
                        if (searchHasPendingInput(root)) return;

                        host.innerHTML = payload.html;
                    } catch (error) {
                        // Gangguan koneksi sementara tidak boleh mengganggu halaman.
                    } finally {
                        inFlight = false;
                    }
                }

                window.setInterval(refreshMonitoring, pollInterval);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') refreshMonitoring();
                });
            })();
        </script>
    @endpush
</x-layouts.app>
