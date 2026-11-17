@php
    $isEdit = $isEdit ?? false;
    $room = $resource?->room;
    $asset = $resource?->asset;
    $rule = $resource?->rules;
    $typeValue = old('type', $resource?->type?->value ?? 'room');
    $facilitiesValue = old('facilities_text', $room?->facilities ? implode(', ', $room->facilities) : '');
    $timeValue = fn ($value) => $value ? substr($value, 0, 5) : '';
@endphp

<div class="form-grid">
    <div class="field field--wide">
        <label class="field-label" for="name">Nama</label>
        <input class="input" type="text" id="name" name="name" value="{{ old('name', $resource?->name) }}" placeholder="Contoh: Ruang Rapat 2.04" required>
    </div>
    <div class="field">
        <label class="field-label" for="type">Jenis</label>
        <select class="input" id="type" name="type" data-resource-type required @disabled($isEdit)>
            <option value="room" @selected($typeValue === 'room')>Ruangan</option>
            <option value="asset" @selected($typeValue === 'asset')>Aset</option>
        </select>
        @if($isEdit)
            <input type="hidden" name="type" value="{{ $typeValue }}">
        @endif
    </div>
    <div class="field">
        <label class="field-label" for="location_id">Lokasi</label>
        <select class="input" id="location_id" name="location_id" required>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" @selected(old('location_id', $resource?->location_id) == $location->id)>{{ $location->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field field--wide">
        <label class="field-label" for="category">Kategori</label>
        <input class="input" type="text" id="category" name="category" value="{{ old('category', $resource?->category) }}" placeholder="Contoh: Ruang rapat, proyektor portabel">
    </div>
    <div class="field field--wide">
        <label class="field-label" for="description">Deskripsi</label>
        <textarea class="input" id="description" name="description" rows="3" placeholder="Kapasitas, fasilitas, atau catatan lokasi.">{{ old('description', $resource?->description) }}</textarea>
    </div>

    <div class="field field--wide" data-fields="room">
        <h3 class="panel-title">Detail ruangan</h3>
    </div>
    <div class="field" data-fields="room">
        <label class="field-label" for="capacity">Kapasitas orang</label>
        <input class="input" type="number" id="capacity" name="capacity" value="{{ old('capacity', $room?->capacity) }}" min="1" placeholder="20">
    </div>
    <div class="field" data-fields="room">
        <label class="field-label" for="facilities_text">Fasilitas</label>
        <input class="input" type="text" id="facilities_text" name="facilities_text" value="{{ $facilitiesValue }}" placeholder="proyektor, whiteboard, AC">
        <span class="field-hint">Pisahkan dengan koma.</span>
    </div>
    <div class="field" data-fields="room">
        <label class="field-label" for="open_time">Jam buka</label>
        <input class="input" type="time" id="open_time" name="open_time" value="{{ old('open_time', $timeValue($room?->open_time)) }}">
    </div>
    <div class="field" data-fields="room">
        <label class="field-label" for="close_time">Jam tutup</label>
        <input class="input" type="time" id="close_time" name="close_time" value="{{ old('close_time', $timeValue($room?->close_time)) }}">
    </div>

    <div class="field field--wide" data-fields="asset">
        <h3 class="panel-title">Detail aset</h3>
    </div>
    <div class="field" data-fields="asset">
        <label class="field-label" for="code">Kode aset</label>
        <input class="input" type="text" id="code" name="code" value="{{ old('code', $asset?->code) }}" placeholder="Contoh: CAM-001">
    </div>
    <div class="field" data-fields="asset">
        <label class="field-label" for="condition">Kondisi</label>
        <select class="input" id="condition" name="condition">
            <option value="good" @selected(old('condition', $asset?->condition ?? 'good') === 'good')>Baik</option>
            <option value="damaged" @selected(old('condition', $asset?->condition) === 'damaged')>Rusak</option>
            <option value="lost" @selected(old('condition', $asset?->condition) === 'lost')>Hilang</option>
            <option value="maintenance" @selected(old('condition', $asset?->condition) === 'maintenance')>Perawatan</option>
        </select>
    </div>

    <div class="field field--wide">
        <h3 class="panel-title">Aturan booking</h3>
    </div>
    <div class="field">
        <label class="field-label" for="buffer_minutes">Buffer antar booking (menit)</label>
        <input class="input" type="number" id="buffer_minutes" name="buffer_minutes" value="{{ old('buffer_minutes', $rule?->buffer_minutes ?? 0) }}" min="0">
    </div>
    <div class="field">
        <label class="field-label" for="advance_booking_days">Batas booking di muka (hari)</label>
        <input class="input" type="number" id="advance_booking_days" name="advance_booking_days" value="{{ old('advance_booking_days', $rule?->advance_booking_days ?? 30) }}" min="1">
    </div>
    <div class="field">
        <label class="field-label" for="min_duration_minutes">Durasi minimum (menit)</label>
        <input class="input" type="number" id="min_duration_minutes" name="min_duration_minutes" value="{{ old('min_duration_minutes', $rule?->min_duration_minutes ?? 30) }}" min="1">
    </div>
    <div class="field">
        <label class="field-label" for="max_duration_minutes">Durasi maksimum (menit)</label>
        <input class="input" type="number" id="max_duration_minutes" name="max_duration_minutes" value="{{ old('max_duration_minutes', $rule?->max_duration_minutes ?? 480) }}" min="1">
    </div>
    <div class="field">
        <label class="field-label" for="approval_path">Jalur persetujuan</label>
        <select class="input" id="approval_path" name="approval_path">
            <option value="none" @selected(old('approval_path', $rule?->approval_path?->value ?? 'none') === 'none')>Tanpa persetujuan</option>
            <option value="one_level" @selected(old('approval_path', $rule?->approval_path?->value) === 'one_level')>Satu tingkat</option>
            <option value="two_level" @selected(old('approval_path', $rule?->approval_path?->value) === 'two_level')>Dua tingkat</option>
        </select>
    </div>
    <div class="field">
        <label class="field-label" for="cancellation_minutes_before">Batas pembatalan (menit sebelum mulai)</label>
        <input class="input" type="number" id="cancellation_minutes_before" name="cancellation_minutes_before" value="{{ old('cancellation_minutes_before', $rule?->cancellation_minutes_before ?? 60) }}" min="0">
    </div>
</div>
