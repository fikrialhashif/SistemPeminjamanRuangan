@php
    $isEdit = $isEdit ?? false;
    $room = $resource?->room;
    $asset = $resource?->asset;
    $rule = $resource?->rules;
    $typeValue = old('type', $resource?->type?->value ?? 'room');
    $categoryIdValue = old('category_id', $resource?->category_id);
    $selectedFacilities = array_map('strval', (array) old('facilities', $room?->facilities ?? []));
    $timeValue = fn ($value) => $value ? substr($value, 0, 5) : '';
    $approvalValue = old('approval_path', $rule?->approval_path?->value ?? 'one_level');
    $approvalValue = $approvalValue === 'none' ? 'one_level' : $approvalValue;
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
        <label class="field-label" for="description">Deskripsi</label>
        <textarea class="input" id="description" name="description" rows="3" placeholder="Kapasitas, fasilitas, atau catatan lokasi.">{{ old('description', $resource?->description) }}</textarea>
    </div>

    <div class="field field--wide" data-fields="room">
        <h3 class="panel-title">Keterangan ruangan</h3>
    </div>
    <div class="field" data-fields="room">
        <label class="field-label" for="category_room">Kategori ruangan</label>
        <select class="input" id="category_room" name="category_id">
            <option value="">Pilih kategori ruangan</option>
            @foreach($roomCategories as $category)
                <option value="{{ $category->id }}" @selected($categoryIdValue == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <span class="field-hint">Kelola daftar di menu Kategori.</span>
    </div>
    <div class="field" data-fields="room">
        <label class="field-label" for="capacity">Kapasitas orang</label>
        <input class="input" type="number" id="capacity" name="capacity" value="{{ old('capacity', $room?->capacity) }}" min="1" placeholder="20">
    </div>
    <div class="field field--wide" data-fields="room">
        <span class="field-label">Fasilitas</span>
        @if($facilities->isEmpty())
            <p class="field-hint">Belum ada fasilitas. Tambahkan dulu di menu Fasilitas.</p>
        @else
            <div class="check-grid">
                @foreach($facilities as $facility)
                    <label class="check check--inline">
                        <input type="checkbox" name="facilities[]" value="{{ $facility->name }}" @checked(in_array($facility->name, $selectedFacilities, true))>
                        <span class="field-label">{{ $facility->name }}</span>
                    </label>
                @endforeach
            </div>
            <span class="field-hint">Pilih satu atau lebih. Kelola daftar di menu Fasilitas.</span>
        @endif
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
        <h3 class="panel-title">Keterangan aset</h3>
    </div>
    <div class="field" data-fields="asset">
        <label class="field-label" for="category_asset">Kategori aset</label>
        <select class="input" id="category_asset" name="category_id">
            <option value="">Pilih kategori aset</option>
            @foreach($assetCategories as $category)
                <option value="{{ $category->id }}" @selected($categoryIdValue == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <span class="field-hint">Kelola daftar di menu Kategori.</span>
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
        <h3 class="panel-title">Aturan peminjaman</h3>
    </div>
    <div class="field">
        <label class="field-label" for="buffer_minutes">Jeda setelah selesai (menit)</label>
        <input class="input" type="number" id="buffer_minutes" name="buffer_minutes" value="{{ old('buffer_minutes', $rule?->buffer_minutes ?? 0) }}" min="0">
        <span class="field-hint">Waktu kosong sebelum ruangan atau aset bisa dipakai lagi.</span>
    </div>
    <div class="field">
        <label class="field-label" for="advance_booking_days">Boleh dipesan paling jauh (hari)</label>
        <input class="input" type="number" id="advance_booking_days" name="advance_booking_days" value="{{ old('advance_booking_days', $rule?->advance_booking_days ?? 30) }}" min="1">
        <span class="field-hint">Berapa hari ke depan pemesanan sudah bisa dibuat.</span>
    </div>
    <div class="field">
        <label class="field-label" for="min_duration_minutes">Paling singkat (menit)</label>
        <input class="input" type="number" id="min_duration_minutes" name="min_duration_minutes" value="{{ old('min_duration_minutes', $rule?->min_duration_minutes ?? 30) }}" min="1">
        <span class="field-hint">Lama pemakaian paling pendek dalam satu kali pesan.</span>
    </div>
    <div class="field">
        <label class="field-label" for="max_duration_minutes">Paling lama (menit)</label>
        <input class="input" type="number" id="max_duration_minutes" name="max_duration_minutes" value="{{ old('max_duration_minutes', $rule?->max_duration_minutes ?? 480) }}" min="1">
        <span class="field-hint">Lama pemakaian paling panjang dalam satu kali pesan.</span>
    </div>
    <div class="field">
        <label class="field-label" for="approval_path">Perlu persetujuan</label>
        <select class="input" id="approval_path" name="approval_path">
            <option value="one_level" @selected($approvalValue === 'one_level')>1 orang menyetujui</option>
            <option value="two_level" @selected($approvalValue === 'two_level')>2 orang menyetujui</option>
        </select>
    </div>
    <div class="field">
        <label class="field-label" for="cancellation_minutes_before">Batal paling lambat (menit sebelum mulai)</label>
        <input class="input" type="number" id="cancellation_minutes_before" name="cancellation_minutes_before" value="{{ old('cancellation_minutes_before', $rule?->cancellation_minutes_before ?? 60) }}" min="0">
        <span class="field-hint">Isi 0 jika pembatalan boleh dilakukan kapan saja.</span>
    </div>
</div>
