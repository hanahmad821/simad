export function schoolLocationPicker(L, env = window) {
    // Keep Leaflet instances outside Alpine's reactive proxies.
    let map, marker, radiusCircle, accuracyCircle, tiles, observer, timer;
    let requestId = 0;
    let initial, form, fieldListener;

    return {
        locating: false,
        locationMessage: '',
        locationError: false,
        mapError: '',
        hasPoint: false,
        mapUrl: '',

        init() {
            form = this.$el;
            initial = ['latitude', 'longitude', 'attendance_radius'].map(name => form.elements[name].value);
            this.$nextTick(() => {
                if (!form.isConnected) return;
                try {
                    map = L.map(this.$refs.locationMap, { scrollWheelZoom: false }).setView([-2.5, 118], 5);
                    tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    }).addTo(map);
                    tiles.on('tileerror', () => { this.mapError = 'Peta dasar gagal dimuat. Periksa koneksi internet atau coba muat ulang peta. Koordinat tetap dapat diisi manual.'; });
                    tiles.on('tileload', () => { this.mapError = ''; });
                    map.on('click', event => this.selectPoint(event.latlng.lat, event.latlng.lng));
                    if (env.ResizeObserver) {
                        observer = new env.ResizeObserver(() => map?.invalidateSize());
                        observer.observe(this.$refs.locationMap);
                    }
                    this.syncFields(true);
                    map.invalidateSize();
                } catch (_) {
                    this.mapError = 'Peta tidak dapat dibuka. Isi koordinat secara manual atau muat ulang halaman.';
                }
            });
            fieldListener = event => {
                if (['latitude', 'longitude', 'attendance_radius'].includes(event.target.name)) {
                    this.cancelLocate(false);
                    this.syncFields(event.target.name !== 'attendance_radius');
                    this.locationError = false;
                    this.locationMessage = this.hasPoint
                        ? 'Koordinat dan radius diperbarui. Periksa pin, lalu simpan profil madrasah.'
                        : 'Isi latitude dan longitude yang valid untuk menampilkan pin sekolah.';
                }
            };
            form.addEventListener('input', fieldListener);
        },

        coordinates() {
            const lat = form.elements.latitude.value.trim();
            const lng = form.elements.longitude.value.trim();
            if (lat === '' || lng === '') return null;
            const point = [Number(lat), Number(lng)];
            return point.every(Number.isFinite) && Math.abs(point[0]) <= 90 && Math.abs(point[1]) <= 180 ? point : null;
        },

        syncFields(center = false) {
            const point = this.coordinates();
            this.hasPoint = !!point;
            this.mapUrl = point ? 'https://www.openstreetmap.org/?mlat=' + point[0] + '&mlon=' + point[1] + '#map=18/' + point.join('/') : '';
            accuracyCircle?.remove();
            accuracyCircle = null;
            if (!map) return;
            if (!point) {
                marker?.remove(); radiusCircle?.remove();
                marker = radiusCircle = null;
                return;
            }
            if (!marker) {
                marker = L.marker(point, {
                    draggable: true, autoPan: true, title: 'Lokasi sekolah (geser untuk mengubah)',
                    icon: L.divIcon({ className: 'school-location-pin', html: '<span aria-hidden="true">📍</span>', iconSize: [36, 44], iconAnchor: [18, 40] }),
                }).addTo(map);
                marker.on('dragstart', () => this.cancelLocate(false));
                marker.on('dragend', () => { const point = marker.getLatLng(); this.selectPoint(point.lat, point.lng, false); });
            } else marker.setLatLng(point);
            const radius = Number(form.elements.attendance_radius.value);
            if (Number.isInteger(radius) && radius >= 1 && radius <= 100000) {
                if (!radiusCircle) radiusCircle = L.circle(point, { radius, color: '#16a34a', fillOpacity: 0.12 }).addTo(map);
                else radiusCircle.setLatLng(point).setRadius(radius);
            } else { radiusCircle?.remove(); radiusCircle = null; }
            if (center) map.setView(point, 17);
        },

        selectPoint(lat, lng, center = false) {
            if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90) return;
            this.cancelLocate(false);
            form.elements.latitude.value = lat.toFixed(7);
            form.elements.longitude.value = (((lng + 180) % 360 + 360) % 360 - 180).toFixed(7);
            this.syncFields(center);
            this.locationError = false;
            this.locationMessage = 'Titik sekolah diperbarui. Periksa pin dan radius, lalu simpan profil madrasah.';
        },

        fitRadius() {
            if (radiusCircle && map) map.fitBounds(radiusCircle.getBounds(), { padding: [30, 30], maxZoom: 18 });
            else this.syncFields(true);
        },

        resetPoint() {
            this.cancelLocate(false);
            ['latitude', 'longitude', 'attendance_radius'].forEach((name, index) => { form.elements[name].value = initial[index]; });
            this.syncFields(true);
            if (!this.hasPoint) map?.setView([-2.5, 118], 5);
            this.locationError = false;
            this.locationMessage = 'Lokasi dan radius dikembalikan ke nilai saat halaman dibuka.';
        },

        retryTiles() {
            this.mapError = '';
            tiles?.redraw();
            map?.invalidateSize();
        },

        cancelLocate(showMessage = true) {
            requestId++;
            env.clearTimeout(timer);
            timer = null;
            const wasLocating = this.locating;
            this.locating = false;
            if (showMessage && wasLocating) {
                this.locationError = false;
                this.locationMessage = 'Pencarian lokasi dibatalkan. Anda dapat memilih titik pada peta.';
            }
        },

        locate() {
            if (this.locating) return;
            this.locationError = false;
            if (!env.isSecureContext || !env.navigator.geolocation) {
                this.locationError = true;
                this.locationMessage = 'Lokasi perangkat tidak tersedia. Gunakan HTTPS, pilih titik pada peta, atau isi koordinat manual.';
                return;
            }
            this.cancelLocate(false);
            const id = requestId;
            this.locating = true;
            this.locationMessage = 'Mencari lokasi perangkat. Izinkan akses lokasi saat diminta (maksimal 20 detik).';
            const fail = message => {
                if (id !== requestId || !form.isConnected) return;
                this.cancelLocate(false);
                this.locationError = true;
                this.locationMessage = message;
            };
            // Browser timeouts may exclude time spent waiting for a permission prompt.
            timer = env.setTimeout(() => fail('Pencarian lokasi melewati 20 detik. Periksa izin lokasi, coba lagi, atau pilih pin pada peta.'), 20000);
            try {
                env.navigator.geolocation.getCurrentPosition(position => {
                    if (id !== requestId || !form.isConnected) return;
                    const { latitude, longitude, accuracy } = position.coords;
                    if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
                        fail('Koordinat perangkat tidak valid. Pilih titik pada peta atau coba lagi.');
                        return;
                    }
                    this.selectPoint(latitude, longitude, true);
                    if (map && Number.isFinite(accuracy) && accuracy > 0) {
                        accuracyCircle = L.circle([latitude, longitude], { radius: accuracy, color: '#2563eb', dashArray: '5 5', fillOpacity: 0.04 }).addTo(map);
                    }
                    this.locationMessage = 'Lokasi ditemukan' + (Number.isFinite(accuracy) ? ' (akurasi ±' + Math.ceil(accuracy) + ' meter)' : '') + '. Geser pin ke titik sekolah yang tepat, lalu simpan profil.';
                }, error => fail(({
                    1: 'Izin lokasi ditolak. Aktifkan izin lokasi di browser atau pilih pin pada peta.',
                    2: 'Lokasi belum tersedia. Aktifkan layanan lokasi perangkat atau pilih pin pada peta.',
                    3: 'Pencarian lokasi terlalu lama. Coba lagi atau pilih pin pada peta.',
                })[error.code] || 'Gagal mengambil lokasi. Pilih pin pada peta atau isi koordinat manual.'),
                { enableHighAccuracy: false, timeout: 12000, maximumAge: 60000 });
            } catch (_) {
                fail('Layanan lokasi tidak dapat dijalankan. Pilih pin pada peta atau isi koordinat manual.');
            }
        },

        destroy() {
            this.cancelLocate(false);
            observer?.disconnect();
            form?.removeEventListener('input', fieldListener);
            map?.remove();
            map = marker = radiusCircle = accuracyCircle = null;
        },
    };
}
