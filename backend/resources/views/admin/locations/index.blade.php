@extends('admin.layouts.app')

@section('title', 'Lokasi kerja')
@section('heading', 'Lokasi kerja')
@section('breadcrumb', 'Lokasi kerja')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        .location-page{display:grid;gap:20px}
        .location-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px}
        .location-toolbar p{margin:0;color:var(--color-bodytext);font-size:.875rem;line-height:1.55}
        .location-card{min-width:0;overflow:hidden;padding:0}
        .location-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-bordergray)}
        .location-card-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .location-card-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem}
        .location-count{display:inline-flex;flex-shrink:0;align-items:center;border:1px solid var(--color-bordergray);border-radius:999px;background:var(--color-lightprimary);padding:7px 12px;color:var(--color-primary);font-size:.8125rem;font-weight:600}
        .location-table-wrap{overflow-x:auto}
        .location-table{min-width:820px}
        .location-table th{padding:13px 16px;background:var(--color-lightgray);color:var(--color-bodytext);font-size:.7rem;letter-spacing:.04em;text-transform:uppercase}
        .location-table td{padding:16px;vertical-align:middle}
        .location-table tbody tr:last-child td{border-bottom:0}
        .location-table tbody tr:hover{background:var(--color-lightprimary)}
        .location-name{min-width:190px}
        .location-name strong{display:block;color:var(--color-dark);font-size:.875rem;font-weight:600}
        .location-name small{max-width:260px;line-height:1.5}
        .location-coordinates{min-width:150px;color:var(--color-bodytext);font-size:.8125rem;font-variant-numeric:tabular-nums}
        .location-col-name{width:26%}.location-col-coordinates{width:22%}.location-col-radius{width:12%}.location-col-employees{width:12%}.location-col-status{width:12%}.location-col-actions{width:16%}
        .location-table details{position:relative}
        .location-table details>summary{display:inline-flex;align-items:center;justify-content:center;cursor:pointer;list-style:none;white-space:nowrap}
        .location-table details>summary::-webkit-details-marker{display:none}
        .location-form{display:grid;gap:14px}
        .location-actions,.location-create-actions{display:flex;align-items:center;justify-content:flex-end;gap:14px}
        .location-create-card{padding:24px}
        .location-create-head{margin-bottom:20px}
        .location-create-head h2{margin:0;color:var(--color-dark);font-size:1.0625rem;font-weight:700}
        .location-create-head p{margin:5px 0 0;color:var(--color-bodytext);font-size:.8125rem;line-height:1.55}
        .location-create-note{margin:18px 0 0;border-radius:10px;background:var(--color-lightwarning);padding:12px 14px;color:var(--color-warning);font-size:.8125rem;line-height:1.55}
        .location-map-field{grid-column:1/-1}
        .location-map{height:260px;overflow:hidden;border:1px solid #dfe5ef;border-radius:12px;background:#edf2f5;z-index:0}
        .location-map-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 8px}
        .location-map-hint{margin:0;color:#6e7c98;font-size:11px;line-height:1.5}
        .location-map-status{min-height:17px;margin-top:7px;color:#6e7c98;font-size:10px}
        .location-map-status.is-error{color:#b42338}
        .location-coordinate-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
        .location-coordinate-fields>div{min-width:0}
        .location-map-label{display:flex;align-items:center;gap:7px;margin-bottom:5px;color:#6e7c98;font-size:10px;font-weight:650}
        .location-map-label span{display:grid;place-items:center;width:20px;height:20px;border-radius:7px;background:#eaf1ff;color:#1769ed;font-size:11px}
        .location-map-picker .leaflet-control-attribution{font-size:9px}
        .location-map-picker.leaflet-container{font:12px Inter,ui-sans-serif,system-ui,sans-serif}
        @media(max-width:700px){
            .location-toolbar{align-items:stretch;flex-direction:column}
            .location-toolbar .btn{width:100%}
            .location-card-head{align-items:flex-start;flex-direction:column;padding:18px 16px}
            .location-count{padding:6px 10px;font-size:.75rem}
            .location-create-card{padding:18px}
            .location-map-field{grid-column:auto}
            .location-map{height:230px}
            .location-map-toolbar{align-items:flex-start;flex-direction:column}
            .location-map-toolbar .btn{width:100%}
            .location-coordinate-fields{grid-template-columns:1fr}
            .location-actions,.location-create-actions{align-items:stretch;flex-direction:column}
            .location-actions .btn,.location-create-actions .btn{width:100%}
        }
    </style>
    <div class="location-page">
    <div class="location-toolbar">
        <p>Atur titik cabang lewat peta atau masukkan koordinat GPS secara manual.</p>
        <a class="btn" href="#tambah-lokasi">＋ Tambah lokasi</a>
    </div>
    <section class="card location-card">
        <div class="location-card-head"><div><h2>Daftar lokasi kerja</h2><p>Kelola titik kantor dan jangkauan geofence absensi.</p></div><span class="location-count">{{ $locations->count() }} lokasi</span></div>
        <div class="location-table-wrap">
            <table class="location-table">
                <colgroup>
                    <col class="location-col-name">
                    <col class="location-col-coordinates">
                    <col class="location-col-radius">
                    <col class="location-col-employees">
                    <col class="location-col-status">
                    <col class="location-col-actions">
                </colgroup>
                <thead><tr><th>Nama / alamat</th><th>Koordinat GPS</th><th>Radius</th><th>Karyawan</th><th>Status</th><th>Tindakan</th></tr></thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td class="location-name"><strong>{{ $location->name }}</strong><small>{{ $location->address ?: 'Alamat belum diisi' }}</small></td>
                            <td class="location-coordinates">{{ $location->geofenceEnabled() ? $location->latitude.', '.$location->longitude : 'Belum diatur' }}</td>
                            <td>{{ number_format($location->radius_meters) }} m</td>
                            <td>{{ number_format($location->employees_count) }}</td>
                            <td><span class="badge-md rounded-full text-xs font-medium {{ $location->is_active ? 'bg-lightsuccess text-success' : 'bg-lighterror text-error' }}">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td>
                                <details>
                                    <summary class="btn bg-lightprimary text-primary text-xs py-1 px-2">Kelola</summary>
                                    <form method="post" action="{{ route('admin.locations.update', $location) }}" class="location-form modal-form" data-dialog-title="Kelola lokasi · {{ $location->name }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div><label class="block mb-1 text-sm font-medium text-link" for="name-{{ $location->id }}">Nama lokasi</label><input class="form-control" id="name-{{ $location->id }}" name="name" value="{{ $location->name }}" maxlength="255" required></div>
                                            <div><label class="block mb-1 text-sm font-medium text-link" for="address-{{ $location->id }}">Alamat</label><input class="form-control" id="address-{{ $location->id }}" name="address" value="{{ $location->address }}" maxlength="1000"></div>
                                            <div class="location-map-field">
                                                <div class="location-map-toolbar">
                                                    <p class="location-map-hint">Klik peta untuk memilih titik kantor. Geser pin untuk menyesuaikan posisi.</p>
                                                    <button class="btn bg-lightprimary text-primary text-xs py-1 px-2" type="button" data-use-current-location>Gunakan lokasi saya</button>
                                                </div>
                                                <div class="location-map location-map-picker" id="location-map-{{ $location->id }}" data-location-map role="application" aria-label="Peta pilih titik {{ $location->name }}"></div>
                                                <div class="location-map-status" aria-live="polite"></div>
                                            </div>
                                            <div class="location-map-field location-coordinate-fields">
                                                <div>
                                                    <label class="location-map-label" for="latitude-{{ $location->id }}"><span>Y</span> Latitude</label>
                                                    <input class="form-control" id="latitude-{{ $location->id }}" name="latitude" type="number" step="any" min="-90" max="90" value="{{ $location->latitude }}">
                                                </div>
                                                <div>
                                                    <label class="location-map-label" for="longitude-{{ $location->id }}"><span>X</span> Longitude</label>
                                                    <input class="form-control" id="longitude-{{ $location->id }}" name="longitude" type="number" step="any" min="-180" max="180" value="{{ $location->longitude }}">
                                                </div>
                                            </div>
                                            <div><label class="block mb-1 text-sm font-medium text-link" for="radius-{{ $location->id }}">Radius GPS (meter)</label><input class="form-control" id="radius-{{ $location->id }}" name="radius_meters" type="number" min="20" max="2000" value="{{ $location->radius_meters }}" required></div>
                                            <div class="md:col-span-2 location-actions">
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="text-xs text-bodytext"><input type="checkbox" name="is_active" value="1" @checked($location->is_active)> Lokasi aktif</label>
                                                <button class="btn text-xs py-1 px-2" type="submit">Simpan perubahan</button>
                                            </div>
                                        </div>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-sm text-bodytext">Belum ada lokasi kerja. Tambahkan lokasi lalu masukkan koordinat yang benar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card p-6 modal-form location-create-card" id="tambah-lokasi">
        <div class="location-create-head panel-head">
            <h2>Tambah lokasi kerja</h2>
            <p>Tambahkan alamat dan titik GPS cabang untuk mengatur jangkauan absensi.</p>
        </div>
        <form method="post" action="{{ route('admin.locations.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 location-create-form">
            @csrf
            <div><label class="block mb-1 text-sm font-medium text-link" for="new-name">Nama lokasi</label><input class="form-control" id="new-name" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="Contoh: Kantor Cabang Bandung"></div>
            <div><label class="block mb-1 text-sm font-medium text-link" for="new-address">Alamat</label><input class="form-control" id="new-address" name="address" value="{{ old('address') }}" maxlength="1000"></div>
            <div class="location-map-field">
                <div class="location-map-toolbar">
                    <p class="location-map-hint">Klik peta untuk memilih titik kantor. Pin bisa digeser, atau koordinat dapat diisi manual.</p>
                    <button class="btn bg-lightprimary text-primary text-xs py-1 px-2" type="button" data-use-current-location>Gunakan lokasi saya</button>
                </div>
                <div class="location-map location-map-picker" id="location-map-create" data-location-map role="application" aria-label="Peta pilih titik lokasi baru"></div>
                <div class="location-map-status" aria-live="polite"></div>
            </div>
            <div class="location-map-field location-coordinate-fields">
                <div>
                    <label class="location-map-label" for="new-latitude"><span>Y</span> Latitude</label>
                    <input class="form-control" id="new-latitude" name="latitude" type="number" step="any" min="-90" max="90" value="{{ old('latitude') }}">
                </div>
                <div>
                    <label class="location-map-label" for="new-longitude"><span>X</span> Longitude</label>
                    <input class="form-control" id="new-longitude" name="longitude" type="number" step="any" min="-180" max="180" value="{{ old('longitude') }}">
                </div>
            </div>
            <div><label class="block mb-1 text-sm font-medium text-link" for="new-radius">Radius GPS (meter)</label><input class="form-control" id="new-radius" name="radius_meters" type="number" min="20" max="2000" value="{{ old('radius_meters', 100) }}" required></div>
            <input type="hidden" name="is_active" value="1">
            <div class="md:col-span-2 location-create-actions"><button class="btn" type="submit">Simpan lokasi</button></div>
        </form>
        <p class="location-create-note">Koordinat boleh dilengkapi nanti, tetapi absensi di lokasi tersebut tetap ditolak sampai geofence siap. Radius lingkaran di peta mengikuti nilai radius GPS.</p>
    </section>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (() => {
            const indonesiaCenter = [-2.5, 118];
            const maps = new Map();
            const formatCoordinate = (value) => Number(value).toFixed(7);

            const initializeMap = (container) => {
                if (maps.has(container)) {
                    maps.get(container).map.invalidateSize();
                    return;
                }
                if (!window.L) {
                    container.nextElementSibling.textContent = 'Peta gagal dimuat. Periksa koneksi internet, atau masukkan koordinat secara manual.';
                    container.nextElementSibling.classList.add('is-error');
                    return;
                }

                const form = container.closest('form');
                const latitude = form.querySelector('[name="latitude"]');
                const longitude = form.querySelector('[name="longitude"]');
                const radius = form.querySelector('[name="radius_meters"]');
                const status = container.nextElementSibling;
                const hasCoordinates = latitude.value.trim() !== '' && longitude.value.trim() !== '';
                const initialPoint = hasCoordinates
                    ? [Number(latitude.value), Number(longitude.value)]
                    : indonesiaCenter;
                const map = L.map(container, {scrollWheelZoom: false}).setView(
                    initialPoint,
                    hasCoordinates ? 16 : 5
                );
                const markerLayer = L.layerGroup().addTo(map);
                const radiusLayer = L.layerGroup().addTo(map);
                let marker = null;
                let circle = null;

                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'
                }).addTo(map);
                map.on('tileerror', () => {
                    status.textContent = 'Peta tidak dapat memuat ubin. Periksa koneksi; koordinat manual tetap bisa digunakan.';
                    status.classList.add('is-error');
                });
                map.on('tileload', () => {
                    status.textContent = '';
                    status.classList.remove('is-error');
                });

                const setPoint = (point, shouldCenter = false) => {
                    const [lat, lng] = point;
                    latitude.value = formatCoordinate(lat);
                    longitude.value = formatCoordinate(lng);
                    if (!marker) {
                        marker = L.marker(point, {draggable: true}).addTo(markerLayer);
                        marker.on('dragend', () => {
                            const moved = marker.getLatLng();
                            setPoint([moved.lat, moved.lng]);
                        });
                    } else {
                        marker.setLatLng(point);
                    }
                    const meters = Number(radius.value);
                    if (!circle) {
                        circle = L.circle(point, {
                            radius: Number.isFinite(meters) ? meters : 100,
                            color: '#1769ed',
                            weight: 2,
                            fillColor: '#3287ff',
                            fillOpacity: 0.13
                        }).addTo(radiusLayer);
                    } else {
                        circle.setLatLng(point);
                        circle.setRadius(Number.isFinite(meters) ? meters : 100);
                    }
                    status.textContent = `Titik dipilih: ${formatCoordinate(lat)}, ${formatCoordinate(lng)}`;
                    status.classList.remove('is-error');
                    if (shouldCenter) map.setView(point, Math.max(map.getZoom(), 16));
                };

                const clearPoint = () => {
                    markerLayer.clearLayers();
                    radiusLayer.clearLayers();
                    marker = null;
                    circle = null;
                    status.textContent = 'Pilih titik pada peta atau isi latitude dan longitude.';
                    status.classList.remove('is-error');
                };

                map.on('click', (event) => setPoint([event.latlng.lat, event.latlng.lng]));
                [latitude, longitude].forEach((input) => input.addEventListener('change', () => {
                    const lat = Number(latitude.value);
                    const lng = Number(longitude.value);
                    if (!latitude.value.trim() && !longitude.value.trim()) {
                        clearPoint();
                    } else if (
                        latitude.value.trim() && longitude.value.trim()
                        && Number.isFinite(lat) && Number.isFinite(lng)
                        && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
                    ) {
                        setPoint([lat, lng], true);
                    } else {
                        status.textContent = 'Isi latitude dan longitude dengan pasangan koordinat yang valid.';
                        status.classList.add('is-error');
                    }
                }));
                radius.addEventListener('input', () => {
                    if (circle && Number.isFinite(Number(radius.value))) {
                        circle.setRadius(Number(radius.value));
                    }
                });
                form.querySelector('[data-use-current-location]').addEventListener('click', () => {
                    if (!navigator.geolocation) {
                        status.textContent = 'Browser tidak mendukung akses lokasi. Silakan pilih titik langsung di peta.';
                        status.classList.add('is-error');
                        return;
                    }
                    status.textContent = 'Mencari lokasi perangkat...';
                    status.classList.remove('is-error');
                    navigator.geolocation.getCurrentPosition(
                        ({coords}) => setPoint([coords.latitude, coords.longitude], true),
                        (error) => {
                            status.textContent = error.code === error.PERMISSION_DENIED
                                ? 'Izin lokasi ditolak. Aktifkan izin lokasi atau pilih titik langsung di peta.'
                                : 'Lokasi perangkat tidak berhasil didapat. Pilih titik langsung di peta atau isi koordinat manual.';
                            status.classList.add('is-error');
                        },
                        {enableHighAccuracy: true, timeout: 15000, maximumAge: 0}
                    );
                });

                maps.set(container, {map, setPoint});
                if (hasCoordinates) {
                    setPoint(initialPoint);
                } else {
                    status.textContent = 'Klik peta untuk menentukan titik atau masukkan koordinat secara manual.';
                }
                requestAnimationFrame(() => map.invalidateSize());
            };

            document.querySelectorAll('details').forEach((details) => {
                details.addEventListener('toggle', () => {
                    if (details.open) {
                        const map = details.querySelector('[data-location-map]');
                        if (map) initializeMap(map);
                    }
                });
            });
            window.addEventListener('attendly:modal-open', (event) => {
                event.detail.dialog.querySelectorAll('[data-location-map]').forEach(initializeMap);
            });
            initializeMap(document.getElementById('location-map-create'));
        })();
    </script>
@endsection
