@extends('admin.layout')

@section('title', 'Edit '.$resource->name)

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Edit {{ $resource->name }}</h1>
            <p class="plate-desc">Perbarui detail resource dan aturan booking. Jenis resource tidak bisa diubah.</p>
        </div>
        <div class="plate-aside">
            <a class="btn btn--onplate" href="{{ route('admin.resources') }}">Kembali ke daftar</a>
        </div>
    </header>

    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Detail resource</h2>
            <span class="tag {{ $resource->is_active ? '' : 'tag--plain' }}">{{ $resource->is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.resources.update', $resource) }}">
                @csrf
                @method('PUT')
                @include('admin.partials.resource-fields', ['resource' => $resource, 'locations' => $locations, 'isEdit' => true])

                <div class="form-actions">
                    <a class="btn btn--ghost" href="{{ route('admin.resources') }}">Batal</a>
                    <button type="submit" class="btn btn--primary">Simpan perubahan</button>
                </div>
            </form>
        </div>
    </section>
@endsection
