@php
    $prefix = $prefix ?? 'location';
    $polygon = $location?->polygon;
    $geofenceType = old('geofence_type', is_array($polygon) && count($polygon) >= 3 ? 'polygon' : 'radius');
    $radiusValue = old('radius', $location?->radius ?? 100);
    $latitudeValue = old('latitude', $location?->latitude ?? 0.5201724);
    $longitudeValue = old('longitude', $location?->longitude ?? 101.4539465);
    $polygonValue = old('polygon', is_array($polygon) ? json_encode($polygon, JSON_UNESCAPED_SLASHES) : '');
    $openTimeValue = old('open_time', substr($location?->open_time ?? \App\Models\Location::DEFAULT_OPEN_TIME, 0, 5));
    $closeTimeValue = old('close_time', substr($location?->close_time ?? \App\Models\Location::DEFAULT_CLOSE_TIME, 0, 5));
@endphp

<div class="location-editor" data-location-editor>
    <div class="form-grid">
        <div class="field field--wide">
            <label class="field-label" for="{{ $prefix }}_name">Nama lokasi</label>
            <input class="input" type="text" id="{{ $prefix }}_name" name="name" value="{{ old('name', $location?->name) }}" placeholder="Contoh: Gedung A" required>
        </div>
        <div class="field field--wide">
            <label class="field-label" for="{{ $prefix }}_address">Alamat</label>
            <input class="input" type="text" id="{{ $prefix }}_address" name="address" value="{{ old('address', $location?->address) }}" placeholder="Lantai, gedung, atau patokan">
        </div>

        <div class="field">
            <label class="field-label" for="{{ $prefix }}_open_time">Jam buka (WIB)</label>
            <input class="input" type="time" id="{{ $prefix }}_open_time" name="open_time" value="{{ $openTimeValue }}">
        </div>
        <div class="field">
            <label class="field-label" for="{{ $prefix }}_close_time">Jam tutup (WIB)</label>
            <input class="input" type="time" id="{{ $prefix }}_close_time" name="close_time" value="{{ $closeTimeValue }}">
        </div>

        <div class="field field--wide">
            <span class="field-label">Bentuk area</span>
            <div class="segmented" role="radiogroup" aria-label="Bentuk area">
                <label class="segment">
                    <input type="radio" name="geofence_type" value="radius" @checked($geofenceType === 'radius')>
                    <span>
                        <strong>Radius</strong>
                        <small>Lingkaran dari satu titik pusat</small>
                    </span>
                </label>
                <label class="segment">
                    <input type="radio" name="geofence_type" value="polygon" @checked($geofenceType === 'polygon')>
                    <span>
                        <strong>Area bebas</strong>
                        <small>Area mengikuti bentuk bangunan</small>
                    </span>
                </label>
            </div>
        </div>

        <div class="field field--wide">
            <div class="map-frame">
                <div class="location-map" data-location-map aria-label="Peta pengaturan area"></div>
            </div>
            <div class="map-bar">
                <span class="map-status" data-map-status aria-live="polite">Menyiapkan peta...</span>
                <div class="map-actions" data-map-actions hidden>
                    <button type="button" class="btn btn--ghost btn--sm" data-map-undo>Hapus titik</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-map-clear>Bersihkan</button>
                </div>
            </div>
            <span class="field-hint" data-map-hint>Klik peta untuk memindahkan titik tengah area.</span>
        </div>

        <div class="field">
            <label class="field-label" for="{{ $prefix }}_latitude">Garis lintang</label>
            <input class="input" type="number" id="{{ $prefix }}_latitude" name="latitude" value="{{ $latitudeValue }}" step="0.0000001" min="-90" max="90" data-lat-input required>
        </div>
        <div class="field">
            <label class="field-label" for="{{ $prefix }}_longitude">Garis bujur</label>
            <input class="input" type="number" id="{{ $prefix }}_longitude" name="longitude" value="{{ $longitudeValue }}" step="0.0000001" min="-180" max="180" data-lng-input required>
        </div>
        <div class="field" data-radius-field>
            <label class="field-label" for="{{ $prefix }}_radius">Jangkauan area (meter)</label>
            <input class="input" type="number" id="{{ $prefix }}_radius" name="radius" value="{{ $radiusValue }}" min="10" step="1" data-radius-input>
            <span class="field-hint">Lingkaran di peta berubah mengikuti angka ini.</span>
        </div>
        <div class="field" data-polygon-field hidden>
            <span class="field-label">Titik area</span>
            <span class="polygon-count" data-polygon-count>0 titik</span>
            <span class="field-hint">Minimal 3 titik. Klik peta untuk menambah, lalu geser titik jika perlu.</span>
        </div>
    </div>

    <input type="hidden" name="polygon" value="{{ $polygonValue }}" data-polygon-input>
</div>
