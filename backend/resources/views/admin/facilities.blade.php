@extends('admin.layout')

@section('title', 'Fasilitas')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Fasilitas</h1>
            <p class="plate-desc">Daftar fasilitas yang bisa dipilih saat menambahkan ruangan.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($facilities->count(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">fasilitas</span>
            </div>
            @if(auth()->user()->hasPermission('facilities.create'))
                <button type="button" class="btn btn--onplate" data-open-dialog="createFacility">Tambah fasilitas</button>
            @endif
        </div>
    </header>

    <section class="panel">
        @if($facilities->isEmpty())
            <div class="empty">
                <p class="empty-title">Belum ada fasilitas.</p>
                <p>Tambahkan fasilitas seperti proyektor, papan tulis, atau pendingin udara.</p>
                @if(auth()->user()->hasPermission('facilities.create'))
                    <button type="button" class="btn btn--primary" data-open-dialog="createFacility">Tambah fasilitas pertama</button>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Fasilitas</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($facilities as $facility)
                            <tr>
                                <td data-label="Fasilitas">
                                    <div class="cell-title">{{ $facility->name }}</div>
                                </td>
                                <td data-label="Aksi" class="data-actions">
                                    <div class="cell-actions">
                                        @if(auth()->user()->hasPermission('facilities.update'))
                                            <button type="button" class="btn btn--ghost btn--sm" data-open-dialog="editFacility{{ $facility->id }}">Ubah</button>
                                        @endif
                                        @if(auth()->user()->hasPermission('facilities.delete'))
                                            <form method="POST" action="{{ route('admin.facilities.destroy', $facility) }}" data-confirm="Hapus fasilitas {{ $facility->name }}?">
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

    <dialog id="createFacility" class="modal" aria-labelledby="createFacilityTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createFacilityTitle">Tambah fasilitas</h2>
                <p class="modal-desc">Nama fasilitas akan muncul sebagai pilihan di formulir ruangan.</p>
            </div>
            <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.facilities.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field field--wide">
                        <label class="field-label" for="facility_name">Nama fasilitas</label>
                        <input class="input" type="text" id="facility_name" name="name" value="{{ old('name') }}" placeholder="Contoh: Proyektor" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan</button>
                </div>
            </form>
        </div>
    </dialog>

    @foreach($facilities as $facility)
        <dialog id="editFacility{{ $facility->id }}" class="modal" aria-labelledby="editFacilityTitle{{ $facility->id }}">
            <div class="modal-head">
                <div>
                    <h2 class="modal-title" id="editFacilityTitle{{ $facility->id }}">Ubah {{ $facility->name }}</h2>
                    <p class="modal-desc">Perbarui nama fasilitas.</p>
                </div>
                <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.facilities.update', $facility) }}" data-confirm="Simpan perubahan fasilitas ini?">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div class="field field--wide">
                            <label class="field-label" for="facility_name_{{ $facility->id }}">Nama fasilitas</label>
                            <input class="input" type="text" id="facility_name_{{ $facility->id }}" name="name" value="{{ $facility->name }}" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                        <button type="submit" class="btn btn--primary">Simpan perubahan</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endforeach
@endsection
