            const mapElement = document.getElementById('peta-wilayah');

            if (mapElement && window.L) {
                const map = L.map(mapElement, {
                    scrollWheelZoom: false,
                }).setView([-7.7, 112.2], 8);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 18,
                }).addTo(map);

                const points = [];

                perWilayah.forEach(function (item) {
                    if (!Number.isFinite(item.lat) || !Number.isFinite(item.lng)) {
                        return;
                    }

                    const point = [item.lat, item.lng];
                    const popup = document.createElement('div');
                    const title = document.createElement('strong');
                    const amount = document.createElement('span');

                    title.textContent = item.nama;
                    amount.textContent = rupiah.format(item.realisasi);
                    popup.append(title, document.createElement('br'), amount);

                    L.marker(point).addTo(map).bindPopup(popup);
                    points.push(point);
                });

                if (points.length > 1) {
                    map.fitBounds(L.latLngBounds(points).pad(0.15), {
                        maxZoom: 9,
                    });
                }
            }
