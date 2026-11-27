@extends('admin.layout')

@section('title', 'Ubah '.$resource->name)

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Ubah {{ $resource->name }}</h1>
            <p class="plate-desc">Perbarui keterangan dan aturan peminjaman. Jenis tidak bisa diubah.</p>
        </div>
        <div class="plate-aside">
            <a class="btn btn--onplate" href="{{ route('admin.resources') }}">Kembali ke daftar</a>
        </div>
    </header>

    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Keterangan ruangan atau aset</h2>
            <span class="tag {{ $resource->is_active ? '' : 'tag--plain' }}">{{ $resource->is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.resources.update', $resource) }}" data-confirm="Simpan perubahan ruangan atau aset ini?">
                @csrf
                @method('PUT')
                @include('admin.partials.resource-fields', ['resource' => $resource, 'locations' => $locations, 'roomCategories' => $roomCategories, 'assetCategories' => $assetCategories, 'facilities' => $facilities, 'isEdit' => true])

                <div class="form-actions">
                    <a class="btn btn--ghost" href="{{ route('admin.resources') }}">Batal</a>
                    <button type="submit" class="btn btn--primary">Simpan perubahan</button>
                </div>
            </form>
        </div>
    </section>
@endsection
