@extends('admin.layout')

@section('title', 'Lokasi & area')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Lokasi &amp; area</h1>
            <p class="plate-desc">Tentukan area di peta dengan radius atau gambar area. Area ini dipakai untuk memeriksa lokasi saat masuk.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($locations->count(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">lokasi</span>
            </div>
            @if(auth()->user()->hasPermission('locations.create'))
                <button type="button" class="btn btn--onplate" data-open-dialog="createLocation">Tambah lokasi</button>
            @endif
        </div>
    </header>

    <section class="panel">
        @if($locations->isEmpty())
            <div class="empty">
                <p class="empty-title">Belum ada lokasi.</p>
                <p>Tambahkan gedung atau area sebelum mendaftarkan ruangan dan aset.</p>
                @if(auth()->user()->hasPermission('locations.create'))
                    <button type="button" class="btn btn--primary" data-open-dialog="createLocation">Tambah lokasi pertama</button>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Koordinat</th>
                            <th scope="col">Area</th>
                            <th scope="col">Ruangan &amp; aset</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($locations as $location)
                            <tr>
                                <td data-label="Lokasi">
                                    <div class="cell-title">{{ $location->name }}</div>
                                    <div class="cell-sub">{{ $location->address ?: 'Alamat belum diisi' }}</div>
                                </td>
                                <td data-label="Koordinat">
                                    <span class="cell-ip">{{ $location->latitude }}, {{ $location->longitude }}</span>
                                </td>
                                <td data-label="Area">
                                    @if(is_array($location->polygon) && count($location->polygon) >= 3)
                                        <span class="tag tag--amber">Area {{ count($location->polygon) }} titik</span>
                                    @else
                                        <span class="tag">Radius {{ $location->radius }} m</span>
                                    @endif
                                </td>
                                <td data-label="Ruangan &amp; aset">{{ $location->resources_count }}</td>
                                <td data-label="Aksi" class="data-actions">
                                    <div class="cell-actions">
                                        @if(auth()->user()->hasPermission('locations.update'))
                                            <button type="button" class="btn btn--ghost btn--sm" data-open-dialog="editLocation{{ $location->id }}">Ubah</button>
                                        @endif
                                        @if(auth()->user()->hasPermission('locations.delete'))
                                            <form method="POST" action="{{ route('admin.locations.destroy', $location) }}" data-confirm="Hapus lokasi {{ $location->name }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--reject btn--sm">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <dialog id="createLocation" class="modal modal--map" aria-labelledby="createLocationTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createLocationTitle">Tambah lokasi</h2>
                <p class="modal-desc">Pilih radius atau gambar area langsung di peta.</p>
            </div>
            <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.locations.store') }}">
                @csrf
                @include('admin.partials.location-fields', ['location' => null, 'prefix' => 'createLocation'])
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan</button>
                </div>
            </form>
        </div>
    </dialog>

    @foreach($locations as $location)
        <dialog id="editLocation{{ $location->id }}" class="modal modal--map" aria-labelledby="editLocationTitle{{ $location->id }}">
            <div class="modal-head">
                <div>
                    <h2 class="modal-title" id="editLocationTitle{{ $location->id }}">Ubah {{ $location->name }}</h2>
                    <p class="modal-desc">Perbarui radius atau gambar ulang area.</p>
                </div>
                <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.locations.update', $location) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.partials.location-fields', ['location' => $location, 'prefix' => 'editLocation'.$location->id])
                    <div class="form-actions">
                        <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                        <button type="submit" class="btn btn--primary">Simpan perubahan</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endforeach

@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/location-map.js') }}?v={{ filemtime(public_path('js/location-map.js')) }}"></script>
@endpush
