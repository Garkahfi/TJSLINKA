            renderDistribution('pumk-live-sector-chart', pumkLive.sektor, 'doughnut', null, resolveSectorColor);
            renderDistribution('pumk-live-quality-chart', pumkLive.kolektibilitas, 'pie', null, resolveCollectibilityColor);
            renderTrend('pumk-live-province-chart', {
                labels: (pumkLive.sebaran_provinsi || []).map(function (item) { return item.label; }),
                datasets: [{
                    label: 'Saldo Piutang',
                    data: (pumkLive.sebaran_provinsi || []).map(function (item) { return item.nilai; }),
                }],
            }, 'bar', false);
            renderTrend('pumk-live-quality-trend-chart', pumkLive.tren_kolektibilitas, 'bar', false, resolveCollectibilityColor);
