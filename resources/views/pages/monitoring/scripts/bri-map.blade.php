            const pumkMapData = pumkBri.peta || {};
            const pumkMapElement = document.getElementById('pumk-bri-map');
            const pumkMapState = document.querySelector('[data-pumk-map-state]');
            const mapToggle = document.querySelector('[data-pumk-map-toggle]');
            const mapReset = document.querySelector('[data-pumk-map-reset]');
            const mapSummary = document.querySelector('[data-pumk-map-summary]');
            const regionFilterNote = document.querySelector('[data-pumk-region-filter-note]');
            const regionRows = Array.from(document.querySelectorAll('[data-pumk-region-row]'));
            const regionItems = Array.isArray(pumkMapData.items) ? pumkMapData.items : [];
            let selectedRegion = null;
            let selectedRegionLabel = null;
            let selectedRegionLayer = null;

            function normalizePumkRegion(value) {
                let normalized = String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim()
                    .replace(/\s+/g, ' ');

                if (normalized.startsWith('kota ')) {
                    return normalized;
                }

                normalized = normalized.replace(/^(kab|kabupaten)\s+/, '');
                normalized = normalized.replace(/\s+(kab|kabupaten)$/, '');

                return normalized || 'belum ditentukan';
            }

            function applyRegionFilter(regionKey, regionLabel) {
                selectedRegion = regionKey || null;
                selectedRegionLabel = regionLabel || null;

                regionRows.forEach(function (row) {
                    const matches = !selectedRegion || row.dataset.regionKey === selectedRegion;
                    row.classList.toggle('is-filtered-out', !matches);
                    row.classList.toggle('is-selected', Boolean(selectedRegion && matches));
                });

                if (regionFilterNote) {
                    regionFilterNote.hidden = !selectedRegion;
                    regionFilterNote.textContent = selectedRegion
                        ? 'Menampilkan data wilayah: ' + selectedRegionLabel
                        : '';
                }

                if (mapReset) {
                    mapReset.hidden = !selectedRegion;
                }

                if (mapToggle) {
                    mapToggle.textContent = selectedRegion
                        ? 'Lihat data ' + selectedRegionLabel
                        : 'Lihat data';
                }
            }

            mapToggle?.addEventListener('click', function () {
                applyRegionFilter(selectedRegion, selectedRegionLabel);
                document.getElementById('pumk-bri-data-mitra')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });

            mapReset?.addEventListener('click', function () {
                applyRegionFilter(null, null);

                if (selectedRegionLayer?.setStyle) {
                    selectedRegionLayer.setStyle({ color: '#ffffff', weight: 1 });
                }

                selectedRegionLayer = null;
            });

            if (pumkMapElement && pumkMapData.available && window.L && regionItems.length) {
                const itemByKey = new Map(regionItems.map(function (item) {
                    return [item.geo_key, item];
                }));
                const maxMitra = Math.max(1, ...regionItems.map(function (item) {
                    return Number(item.jumlah_mitra || 0);
                }));
                const choroplethColors = ['#dbeafe', '#93c5fd', '#60a5fa', '#2563eb', '#0f3d91'];
                const fillColor = function (value) {
                    const ratio = Number(value || 0) / maxMitra;
                    const index = Math.min(choroplethColors.length - 1, Math.floor(ratio * choroplethColors.length));

                    return choroplethColors[index];
                };
                const map = L.map(pumkMapElement, {
                    scrollWheelZoom: false,
                    zoomControl: true,
                }).setView([-2.5, 118], 5);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 18,
                }).addTo(map);

                fetch(pumkMapData.geojson_url, { credentials: 'same-origin' })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('GeoJSON tidak dapat dimuat.');
                        }

                        return response.json();
                    })
                    .then(function (geojson) {
                        const geoLayer = L.geoJSON(geojson, {
                            filter: function (feature) {
                                return Boolean(feature?.properties?.WADMKK);
                            },
                            style: function (feature) {
                                const item = itemByKey.get(normalizePumkRegion(feature?.properties?.WADMKK));

                                return {
                                    color: item ? '#ffffff' : '#cbd5e1',
                                    weight: item ? 1 : .5,
                                    fillColor: item ? fillColor(item.jumlah_mitra) : '#e5e7eb',
                                    fillOpacity: item ? .88 : .28,
                                };
                            },
                            onEachFeature: function (feature, layer) {
                                const key = normalizePumkRegion(feature?.properties?.WADMKK);
                                const item = itemByKey.get(key);

                                if (!item) {
                                    return;
                                }

                                const tooltip = document.createElement('div');
                                tooltip.className = 'pumk-map-tooltip';
                                const title = document.createElement('strong');
                                title.textContent = item.nama;
                                tooltip.appendChild(title);

                                [
                                    ['Mitra unik', Number(item.jumlah_mitra || 0).toLocaleString('id-ID')],
                                    ['Fasilitas', Number(item.jumlah_fasilitas || 0).toLocaleString('id-ID')],
                                    ['Outstanding', rupiah.format(Number(item.outstanding || 0))],
                                    ['L / KL / D / M', [item.lancar, item.kurang_lancar, item.diragukan, item.macet].map(Number).join(' / ')],
                                ].forEach(function (entry) {
                                    const row = document.createElement('div');
                                    row.className = 'pumk-map-tooltip-row';
                                    const label = document.createElement('span');
                                    const value = document.createElement('b');
                                    label.textContent = entry[0];
                                    value.textContent = entry[1];
                                    row.append(label, value);
                                    tooltip.appendChild(row);
                                });

                                layer.bindTooltip(tooltip, { sticky: true, direction: 'top' });
                                layer.on('mouseover', function () {
                                    layer.setStyle({ color: '#0f2855', weight: 2 });
                                    layer.bringToFront();
                                });
                                layer.on('mouseout', function () {
                                    if (layer !== selectedRegionLayer) {
                                        geoLayer.resetStyle(layer);
                                    }
                                });
                                layer.on('click', function () {
                                    if (selectedRegionLayer && selectedRegionLayer !== layer) {
                                        geoLayer.resetStyle(selectedRegionLayer);
                                    }

                                    selectedRegionLayer = layer;
                                    layer.setStyle({ color: '#f59e0b', weight: 3 });
                                    applyRegionFilter(key, item.nama);
                                });
                            },
                        }).addTo(map);

                        if (geoLayer.getBounds().isValid()) {
                            map.fitBounds(geoLayer.getBounds().pad(.18), { maxZoom: 8 });
                        }

                        const legend = L.control({ position: 'bottomright' });
                        legend.onAdd = function () {
                            const element = L.DomUtil.create('div', 'pumk-map-legend');
                            const title = document.createElement('strong');
                            const scale = document.createElement('div');
                            const minimum = document.createElement('span');
                            const maximum = document.createElement('span');
                            title.textContent = 'Jumlah Mitra';
                            scale.className = 'pumk-map-legend-scale';
                            minimum.textContent = '1';
                            maximum.textContent = maxMitra.toLocaleString('id-ID');
                            scale.appendChild(minimum);

                            choroplethColors.forEach(function (color) {
                                const swatch = document.createElement('span');
                                swatch.className = 'pumk-map-legend-swatch';
                                swatch.style.backgroundColor = color;
                                scale.appendChild(swatch);
                            });
                            scale.appendChild(maximum);

                            element.append(title, scale);

                            return element;
                        };
                        legend.addTo(map);

                        if (pumkMapState) {
                            pumkMapState.hidden = true;
                        }

                        if (mapSummary) {
                            mapSummary.textContent = regionItems.length.toLocaleString('id-ID')
                                + ' wilayah · '
                                + Number(pumkMapData.total_mitra_snapshot || 0).toLocaleString('id-ID')
                                + ' mitra unik';
                        }
                    })
                    .catch(function () {
                        if (pumkMapState) {
                            pumkMapState.hidden = false;
                            pumkMapState.textContent = 'Data persebaran belum dapat dimuat.';
                        }
                    });
            } else if (pumkMapState && !pumkMapData.available) {
                pumkMapState.hidden = false;
                pumkMapState.textContent = 'Belum tersedia data snapshot untuk tahun ini.';
            } else if (pumkMapState) {
                pumkMapState.hidden = false;
                pumkMapState.textContent = 'Peta belum dapat ditampilkan pada perangkat ini.';
            }
