@extends('admin.layout')

@section('title', 'Kategori')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Kategori</h1>
            <p class="plate-desc">Kategori dibedakan per jenis dan muncul saat menambah ruangan atau aset.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($categories->count(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">kategori</span>
            </div>
            <button type="button" class="btn btn--onplate" data-open-dialog="createCategory">Tambah kategori</button>
        </div>
    </header>

    <section class="panel">
        @if($categories->isEmpty())
            <div class="empty">
                <p class="empty-title">Belum ada kategori.</p>
                <p>Tambahkan kategori ruangan atau aset sebelum mendaftarkan data.</p>
                <button type="button" class="btn btn--primary" data-open-dialog="createCategory">Tambah kategori pertama</button>
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Kategori</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Jumlah</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $category)
                            <tr>
                                <td data-label="Kategori">
                                    <div class="cell-title">{{ $category->name }}</div>
                                </td>
                                <td data-label="Jenis">
                                    @if($category->type->value === 'room')
                                        <span class="tag">Ruangan</span>
                                    @else
                                        <span class="tag tag--amber">Aset</span>
                                    @endif
                                </td>
                                <td data-label="Jumlah">{{ $category->resources_count }}</td>
                                <td data-label="Aksi" class="data-actions">
                                    <div class="cell-actions">
                                        <button type="button" class="btn btn--ghost btn--sm" data-open-dialog="editCategory{{ $category->id }}">Ubah</button>
                                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Hapus kategori {{ $category->name }}?">
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

    <dialog id="createCategory" class="modal" aria-labelledby="createCategoryTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createCategoryTitle">Tambah kategori</h2>
                <p class="modal-desc">Pilih jenis agar kategori hanya muncul di formulir yang sesuai.</p>
            </div>
            <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field field--wide">
                        <label class="field-label" for="category_name">Nama kategori</label>
                        <input class="input" type="text" id="category_name" name="name" value="{{ old('name') }}" placeholder="Contoh: Ruang rapat" required>
                    </div>
                    <div class="field field--wide">
                        <label class="field-label" for="category_type">Jenis</label>
                        <select class="input" id="category_type" name="type" required>
                            <option value="room" @selected(old('type') === 'room')>Ruangan</option>
                            <option value="asset" @selected(old('type') === 'asset')>Aset</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan</button>
                </div>
            </form>
        </div>
    </dialog>

    @foreach($categories as $category)
        <dialog id="editCategory{{ $category->id }}" class="modal" aria-labelledby="editCategoryTitle{{ $category->id }}">
            <div class="modal-head">
                <div>
                    <h2 class="modal-title" id="editCategoryTitle{{ $category->id }}">Ubah {{ $category->name }}</h2>
                    <p class="modal-desc">Perbarui nama atau jenis kategori.</p>
                </div>
                <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.categories.update', $category) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div class="field field--wide">
                            <label class="field-label" for="category_name_{{ $category->id }}">Nama kategori</label>
                            <input class="input" type="text" id="category_name_{{ $category->id }}" name="name" value="{{ $category->name }}" required>
                        </div>
                        <div class="field field--wide">
                            <label class="field-label" for="category_type_{{ $category->id }}">Jenis</label>
                            <select class="input" id="category_type_{{ $category->id }}" name="type" required>
                                <option value="room" @selected($category->type->value === 'room')>Ruangan</option>
                                <option value="asset" @selected($category->type->value === 'asset')>Aset</option>
                            </select>
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
