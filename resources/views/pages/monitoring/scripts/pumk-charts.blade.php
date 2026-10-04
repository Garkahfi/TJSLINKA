            const pumkColors = ['#b8313a', '#f3a24b', '#9f6edc', '#b4c966', '#24b4c5', '#f3c445', '#df5f87', '#2563eb'];
            const pumkLegend = {
                position: 'right',
                labels: { boxWidth: 10, boxHeight: 10, padding: 8, font: { size: 10 } },
            };
            const moneyTooltip = {
                callbacks: {
                    label: function (context) {
                        return context.label + ': ' + (context.raw == null ? 'Belum tersedia' : rupiah.format(context.raw));
                    },
                },
            };

            function categoryColor(label) {
                let hash = 0;
                for (const char of String(label)) hash = ((hash * 31) + char.charCodeAt(0)) >>> 0;
                return pumkColors[hash % pumkColors.length];
            }

            const collectibilityColors = {
                l: '#22C55E',
                lancar: '#22C55E',
                kl: '#EAB308',
                'kurang lancar': '#EAB308',
                d: '#F97316',
                diragukan: '#F97316',
                m: '#DC2626',
                macet: '#DC2626',
            };

            function resolveCollectibilityColor(value) {
                const candidates = value && typeof value === 'object' ? [value.kode, value.label] : [value];
                for (const candidate of candidates) {
                    const key = String(candidate ?? '').trim().toLowerCase().replace(/[\s_-]+/g, ' ');
                    if (Object.prototype.hasOwnProperty.call(collectibilityColors, key)) {
                        return collectibilityColors[key];
                    }
                }
                return '#94A3B8';
            }

            const sectorPalette = {
                perdagangan: '#3B82F6',
                jasa: '#14B8A6',
                industri: '#6366F1',
                'industri makanan minuman': '#6366F1',
                'industri meubel': '#6366F1',
                'industri bengkel pertukangan': '#6366F1',
                'industri pengolahan': '#6366F1',
                'industri kreatif': '#6366F1',
                'industri konveksi': '#6366F1',
                pertanian: '#38BDF8',
                peternakan: '#A78BFA',
                lainnya: '#94A3B8',
            };

            function resolveSectorColor(value) {
                const candidates = value && typeof value === 'object' ? [value.kode, value.label] : [value];
                for (const candidate of candidates) {
                    const key = String(candidate ?? '').trim().toLowerCase().replace(/[\s_\/-]+/g, ' ');
                    if (Object.prototype.hasOwnProperty.call(sectorPalette, key)) {
                        return sectorPalette[key];
                    }
                }
                return '#94A3B8';
            }

            function renderDistribution(canvasId, items, type, colors, colorResolver) {
                const canvas = document.getElementById(canvasId);
                if (!canvas || !window.Chart || !Array.isArray(items) || items.length === 0) return;

                new Chart(canvas, {
                    type: type,
                    data: {
                        labels: items.map(function (item) { return item.label; }),
                        datasets: [{
                            data: items.map(function (item) { return item.nilai; }),
                            backgroundColor: items.map(function (item, index) {
                                if (colorResolver) return colorResolver(item);
                                return Array.isArray(colors) && colors[index]
                                    ? colors[index]
                                    : categoryColor(item.label);
                            }),
                            borderColor: '#ffffff',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: type === 'doughnut' ? '38%' : 0,
                        plugins: { legend: pumkLegend, tooltip: moneyTooltip },
                    },
                });
            }

            function renderTrend(canvasId, trend, type, stacked, colorResolver) {
                const canvas = document.getElementById(canvasId);
                if (!canvas || !window.Chart || !trend || !trend.datasets || trend.datasets.length === 0) return;

                new Chart(canvas, {
                    type: type,
                    data: {
                        labels: trend.labels,
                        datasets: trend.datasets.map(function (dataset, index) {
                            const color = colorResolver ? colorResolver(dataset.label) : categoryColor(dataset.label);
                            return Object.assign({}, dataset, {
                                borderColor: color,
                                backgroundColor: type === 'bar' ? color : color + '24',
                                borderWidth: type === 'line' ? 2 : 1,
                                pointRadius: type === 'line' ? 2 : 0,
                                tension: .15,
                                spanGaps: false,
                            });
                        }),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { boxWidth: 10, boxHeight: 7, font: { size: 9 } } },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + (context.raw == null ? 'Belum tersedia' : rupiah.format(context.raw));
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                stacked: stacked,
                                ticks: { font: { size: 10 }, callback: function (value) { return compactNumber.format(value); } },
                            },
                            x: { stacked: stacked, ticks: { font: { size: 10 } } },
                        },
                    },
                });
            }
