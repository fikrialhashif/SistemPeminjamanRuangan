@extends('admin.layout')

@section('title', 'Ruangan & aset')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Ruangan &amp; aset</h1>
            <p class="plate-desc">Daftar ruangan dan aset yang bisa dipinjam, beserta statusnya.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($resources->total(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">terdaftar</span>
            </div>
            <button type="button" class="btn btn--onplate" data-open-dialog="createResource">Tambah ruangan atau aset</button>
        </div>
    </header>

    <section class="panel">
        @if($resources->isEmpty())
            <div class="empty">
                <p class="empty-title">Belum ada ruangan atau aset.</p>
                <p>Tambahkan data pertama supaya bisa dipinjam lewat aplikasi.</p>
                <button type="button" class="btn btn--primary" data-open-dialog="createResource">Tambah yang pertama</button>
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Detail</th>
                            <th scope="col">Status</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resources as $resource)
                            <tr>
                                <td data-label="Nama">
                                    <div class="cell-title">{{ $resource->name }}</div>
                                    @if($resource->category)
                                        <div class="cell-sub">{{ $resource->category }}</div>
                                    @endif
                                </td>
                                <td data-label="Jenis">
                                    @if($resource->type->value === 'room')
                                        <span class="tag">Ruangan</span>
                                    @else
                                        <span class="tag tag--amber">Aset</span>
                                    @endif
                                </td>
                                <td data-label="Lokasi">{{ $resource->location->name ?? 'Belum diatur' }}</td>
                                <td data-label="Detail">
                                    @if($resource->room)
                                        <span>Kapasitas {{ $resource->room->capacity }} orang</span>
                                    @elseif($resource->asset)
                                        <span>Kode {{ $resource->asset->code }}</span>
                                        <div class="cell-sub">Kondisi: {{ $resource->asset->condition }}</div>
                                    @else
                                        <span class="cell-muted">Belum ada detail</span>
                                    @endif
                                </td>
                                <td data-label="Status">
                                    <span class="status">
                                        <span class="dot {{ $resource->is_active ? '' : 'dot--muted' }}" aria-hidden="true"></span>
                                        {{ $resource->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td data-label="Aksi" class="data-actions">
                                    <div class="cell-actions">
                                        <a class="btn btn--ghost btn--sm" href="{{ route('admin.resources.edit', $resource) }}">Edit</a>
                                        <a class="btn btn--ghost btn--sm" href="{{ route('resources.qr', $resource) }}" target="_blank" rel="noopener">QR</a>
                                        <form method="POST" action="{{ route('admin.resources.toggle', $resource) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn--ghost btn--sm">{{ $resource->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                        @if(auth()->user()->isAdmin())
                                            <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}" data-confirm="Hapus {{ $resource->name }}? Data akan dihapus dari daftar.">
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

    {{ $resources->links() }}

    <dialog id="createResource" class="modal" aria-labelledby="createResourceTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createResourceTitle">Tambah ruangan atau aset</h2>
                <p class="modal-desc">Isi detail resource dan aturan booking agar langsung bisa dipinjam.</p>
            </div>
            <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.resources.store') }}">
                @csrf
                @include('admin.partials.resource-fields', ['resource' => null, 'locations' => $locations, 'isEdit' => false])

                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan</button>
                </div>
            </form>
        </div>
    </dialog>

    @if($errors->any())
        <script>
            document.getElementById('createResource').showModal();
        </script>
    @endif
@endsection
