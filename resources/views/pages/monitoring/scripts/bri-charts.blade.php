            renderDistribution('pumk-portfolio-chart', pumkBri.sektor, 'pie', null, resolveSectorColor);
            renderDistribution('pumk-quality-chart', pumkBri.kualitas, 'pie', null, resolveCollectibilityColor);

            const outstandingMonthCanvas = document.getElementById('pumk-outstanding-month-chart');
            if (outstandingMonthCanvas && window.Chart && Array.isArray(pumkBri.tren_outstanding) && pumkBri.tren_outstanding.length) {
                new Chart(outstandingMonthCanvas, {
                    type: 'bar',
                    data: {
                        labels: pumkBri.tren_outstanding.map(function (item) { return item.label; }),
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Outstanding',
                                data: pumkBri.tren_outstanding.map(function (item) { return item.nilai; }),
                                backgroundColor: 'rgba(108, 169, 230, .42)',
                                borderColor: '#69a7e8',
                                borderWidth: 1,
                                borderRadius: 5,
                                maxBarThickness: 44,
                            },
                            {
                                type: 'line',
                                label: 'Tren Outstanding',
                                data: pumkBri.tren_outstanding.map(function (item) { return item.nilai; }),
                                borderColor: '#076ee8',
                                backgroundColor: '#076ee8',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                tension: .25,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { boxWidth: 11, boxHeight: 8, font: { size: 9 } } },
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
                                ticks: {
                                    font: { size: 9 },
                                    callback: function (value) { return compactNumber.format(value); },
                                },
                            },
                            x: { ticks: { font: { size: 9 } } },
                        },
                    },
                });
            }
