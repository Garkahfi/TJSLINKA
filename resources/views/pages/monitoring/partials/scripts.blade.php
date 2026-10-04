@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @if(in_array($dashboardType, ['tjsl', 'bri'], true))
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    @endif
    <script>
        (function () {
            const dashboardData = document.getElementById('home-dashboard-data');

            if (!dashboardData) {
                return;
            }

            const perPilar = JSON.parse(dashboardData.dataset.perPilar || '[]');
            const perWilayah = JSON.parse(dashboardData.dataset.perWilayah || '[]');
            const bidangPrioritas = JSON.parse(dashboardData.dataset.bidangPrioritas || '[]');
            const perTpb = JSON.parse(dashboardData.dataset.perTpb || '[]');
            const pumkBri = JSON.parse(dashboardData.dataset.pumkBri || '{}');
            const pumkLive = JSON.parse(dashboardData.dataset.pumkLive || '{}');
            const chartPixelRatio = Math.min(3, Math.max(2, window.devicePixelRatio || 1));

            if (window.Chart) {
                Chart.defaults.devicePixelRatio = chartPixelRatio;
                Chart.defaults.color = '#334155';
                Chart.defaults.font.family = "Poppins, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
                Chart.defaults.font.size = 10;
                Chart.defaults.font.weight = '500';
            }
            const rupiah = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            });
            const compactNumber = new Intl.NumberFormat('id-ID', {
                notation: 'compact',
                maximumFractionDigits: 1,
            });

            @include('pages.monitoring.scripts.tjsl-charts')
            @include('pages.monitoring.scripts.pumk-charts')
            @include('pages.monitoring.scripts.bri-charts')
            @include('pages.monitoring.scripts.inka-charts')
            @include('pages.monitoring.scripts.bri-map')
            @include('pages.monitoring.scripts.tjsl-map')
        })();
    </script>
@endpush
