@extends('admin.layout')

@section('title', 'Lokasi & geofence')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Lokasi &amp; geofence</h1>
            <p class="plate-desc">Atur titik pusat, radius, dan polygon area untuk validasi check-in berbasis GPS.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($locations->count(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">lokasi</span>
            </div>
            <button type="button" class="btn btn--onplate" data-open-dialog="createLocation">Tambah lokasi</button>
        </div>
    </header>

    <section class="panel">
        @if($locations->isEmpty())
            <div class="empty">
                <p class="empty-title">Belum ada lokasi.</p>
                <p>Tambahkan gedung atau area sebelum mendaftarkan ruangan dan aset.</p>
                <button type="button" class="btn btn--primary" data-open-dialog="createLocation">Tambah lokasi pertama</button>
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Koordinat</th>
                            <th scope="col">Radius</th>
                            <th scope="col">Polygon</th>
                            <th scope="col">Resource</th>
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
                                <td data-label="Radius"><span class="tag">{{ $location->radius }} m</span></td>
                                <td data-label="Polygon">
                                    @if($location->polygon)
                                        <span class="tag tag--amber">{{ count($location->polygon) }} titik</span>
                                    @else
                                        <span class="cell-muted">Tidak ada</span>
                                    @endif
                                </td>
                                <td data-label="Resource">{{ $location->resources_count }}</td>
                                <td data-label="Aksi" class="data-actions">
                                    <div class="cell-actions">
                                        <button type="button" class="btn btn--ghost btn--sm" data-open-dialog="editLocation{{ $location->id }}">Edit</button>
                                        <form method="POST" action="{{ route('admin.locations.destroy', $location) }}" data-confirm="Hapus lokasi {{ $location->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn--reject btn--sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <dialog id="createLocation" class="modal" aria-labelledby="createLocationTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createLocationTitle">Tambah lokasi</h2>
                <p class="modal-desc">Radius dipakai untuk validasi GPS. Polygon opsional untuk area yang tidak bundar.</p>
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
        <dialog id="editLocation{{ $location->id }}" class="modal" aria-labelledby="editLocationTitle{{ $location->id }}">
            <div class="modal-head">
                <div>
                    <h2 class="modal-title" id="editLocationTitle{{ $location->id }}">Edit {{ $location->name }}</h2>
                    <p class="modal-desc">Perbarui koordinat dan area geofence.</p>
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
