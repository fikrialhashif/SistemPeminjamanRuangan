@php
    $prefix = $prefix ?? 'location';
    $polygonValue = old('polygon', $location?->polygon ? json_encode($location->polygon, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
@endphp

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
        <label class="field-label" for="{{ $prefix }}_latitude">Latitude</label>
        <input class="input" type="number" id="{{ $prefix }}_latitude" name="latitude" value="{{ old('latitude', $location?->latitude) }}" step="0.0000001" min="-90" max="90" required>
    </div>
    <div class="field">
        <label class="field-label" for="{{ $prefix }}_longitude">Longitude</label>
        <input class="input" type="number" id="{{ $prefix }}_longitude" name="longitude" value="{{ old('longitude', $location?->longitude) }}" step="0.0000001" min="-180" max="180" required>
    </div>
    <div class="field">
        <label class="field-label" for="{{ $prefix }}_radius">Radius geofence (meter)</label>
        <input class="input" type="number" id="{{ $prefix }}_radius" name="radius" value="{{ old('radius', $location?->radius ?? 100) }}" min="10">
        <span class="field-hint">Check-in di luar radius akan ditolak.</span>
    </div>
    <div class="field field--wide">
        <label class="field-label" for="{{ $prefix }}_polygon">Polygon (JSON, opsional)</label>
        <textarea class="input" id="{{ $prefix }}_polygon" name="polygon" rows="4" placeholder='[{"lat": -6.2, "lng": 106.8}]'>{{ $polygonValue }}</textarea>
        <span class="field-hint">Biarkan kosong untuk memakai radius saja.</span>
    </div>
</div>
