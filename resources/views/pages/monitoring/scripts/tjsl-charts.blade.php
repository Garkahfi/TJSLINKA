            const chartCanvas = document.getElementById('per-pilar-chart');

            if (chartCanvas && window.Chart && perPilar.length) {
                new Chart(chartCanvas, {
                    type: 'bar',
                    data: {
                        labels: perPilar.map(function (item) {
                            return item.nama;
                        }),
                        datasets: [
                            {
                                label: 'Rencana',
                                data: perPilar.map(function (item) {
                                    return item.rencana;
                                }),
                                backgroundColor: '#ad3032',
                                borderColor: '#ad3032',
                                borderWidth: 1,
                            },
                            {
                                label: 'Realisasi',
                                data: perPilar.map(function (item) {
                                    return item.realisasi;
                                }),
                                backgroundColor: '#f2a341',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'start',
                                labels: {
                                    boxWidth: 14,
                                    boxHeight: 8,
                                    font: {
                                        size: 10,
                                    },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.label + ': ' + rupiah.format(context.parsed.y);
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 10,
                                    },
                                    callback: function (value) {
                                        return compactNumber.format(value);
                                    },
                                },
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 9,
                                    },
                                },
                            },
                        },
                    },
                });
            }

            const tpbCanvas = document.getElementById('tpb-chart');

            if (tpbCanvas && window.Chart && perTpb.length) {
                new Chart(tpbCanvas, {
                    type: 'bar',
                    data: {
                        labels: perTpb.map(function (item) {
                            return item.nomor;
                        }),
                        datasets: [
                            {
                                label: 'Rencana',
                                data: perTpb.map(function (item) {
                                    return item.rencana;
                                }),
                                backgroundColor: '#0c2856',
                            },
                            {
                                label: 'Realisasi',
                                data: perTpb.map(function (item) {
                                    return item.realisasi;
                                }),
                                backgroundColor: '#65b9b7',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'start',
                                labels: {
                                    boxWidth: 14,
                                    boxHeight: 8,
                                    font: {
                                        size: 10,
                                    },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    title: function (items) {
                                        const item = perTpb[items[0].dataIndex];

                                        return item.nama
                                            ? item.nomor + ' - ' + item.nama
                                            : item.nomor;
                                    },
                                    label: function (context) {
                                        return context.dataset.label + ': ' + rupiah.format(context.parsed.y);
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 10,
                                    },
                                    callback: function (value) {
                                        return compactNumber.format(value);
                                    },
                                },
                            },
                            x: {
                                ticks: {
                                    maxRotation: 35,
                                    minRotation: 35,
                                    font: {
                                        size: 9,
                                    },
                                },
                            },
                        },
                    },
                });
            }

            const priorityCanvas = document.getElementById('priority-chart');

            if (priorityCanvas && window.Chart && bidangPrioritas.length) {
                new Chart(priorityCanvas, {
                    type: 'bar',
                    data: {
                        labels: bidangPrioritas.map(function (item) {
                            return item.nama.replace(/^Bidang\s+/i, '');
                        }),
                        datasets: [{
                            label: 'Penyerapan',
                            data: bidangPrioritas.map(function (item) {
                                return item.penyerapan;
                            }),
                            backgroundColor: bidangPrioritas.map(function (item) {
                                if (item.penyerapan >= 70) return '#84c788';
                                if (item.penyerapan >= 40) return '#f4d15c';
                                return '#e96d70';
                            }),
                            borderWidth: 0,
                            borderRadius: 3,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return 'Penyerapan: ' + context.parsed.y.toLocaleString('id-ID') + '%';
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    font: { size: 10 },
                                    callback: function (value) { return value + '%'; },
                                },
                            },
                            x: {
                                ticks: {
                                    maxRotation: 0,
                                    minRotation: 0,
                                    font: { size: 10 },
                                },
                            },
                        },
                    },
                });
            }

            const regionCanvas = document.getElementById('region-chart');

            if (regionCanvas && window.Chart && perWilayah.length) {
                new Chart(regionCanvas, {
                    type: 'bar',
                    data: {
                        labels: perWilayah.map(function (item) { return item.nama; }),
                        datasets: [{
                            label: 'Realisasi Anggaran',
                            data: perWilayah.map(function (item) { return item.realisasi; }),
                            backgroundColor: '#0c2856',
                            borderWidth: 0,
                            borderRadius: 2,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return rupiah.format(context.parsed.x);
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    font: { size: 10 },
                                    callback: function (value) { return compactNumber.format(value); },
                                },
                            },
                            y: {
                                ticks: { font: { size: 10 } },
                            },
                        },
                    },
                });
            }
