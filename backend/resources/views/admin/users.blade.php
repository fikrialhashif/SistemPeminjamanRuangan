@extends('admin.layout')

@section('title', 'Pengguna')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Pengguna</h1>
            <p class="plate-desc">Kelola akun, departemen, dan hak akses panel admin.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($users->total(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">akun</span>
            </div>
            @if(auth()->user()->hasPermission('users.create'))
                <button type="button" class="btn btn--onplate" data-open-dialog="createUser">Tambah pengguna</button>
            @endif
        </div>
    </header>

    <form method="GET" action="{{ route('admin.users') }}" class="toolbar">
        <div class="filter-grid">
            <div class="field">
                <label class="field-label" for="user_search">Cari</label>
                <input class="input" type="search" id="user_search" name="search" value="{{ request('search') }}" placeholder="Nama atau email">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn--primary">Cari</button>
                <a class="btn btn--ghost" href="{{ route('admin.users') }}">Atur ulang</a>
            </div>
        </div>
    </form>

    <section class="panel">
        @if($users->isEmpty())
            <div class="empty">
                <p class="empty-title">Tidak ada pengguna.</p>
                <p>Ubah kata kunci pencarian atau tambahkan akun baru.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Pengguna</th>
                            <th scope="col">Departemen</th>
                            <th scope="col">Hak akses</th>
                            <th scope="col">Dibuat</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td data-label="Pengguna">
                                    <div class="person">
                                        <span class="person-initial" aria-hidden="true">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                        <span>
                                            <span class="cell-title">{{ $user->name }}</span>
                                            <span class="cell-sub">{{ $user->email }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Departemen">{{ $user->department->name ?? '—' }}</td>
                                <td data-label="Hak akses">
                                    <div class="tag-row">
                                        @forelse($user->roles as $role)
                                            <span class="tag">{{ $roleLabels[$role->name->value] ?? 'Hak akses tidak dikenal' }}</span>
                                        @empty
                                            <span class="cell-muted">Belum ada hak akses</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td data-label="Dibuat">{{ $user->created_at->wib()->locale('id')->isoFormat('D MMM Y') }}</td>
                                <td data-label="Aksi" class="data-actions">
                                    @if(auth()->user()->hasPermission('users.update'))
                                        <button type="button" class="btn btn--ghost btn--sm" data-open-dialog="editUser{{ $user->id }}">Ubah</button>
                                    @else
                                        <span class="cell-muted">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{ $users->links() }}

    <dialog id="createUser" class="modal" aria-labelledby="createUserTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createUserTitle">Tambah pengguna</h2>
                <p class="modal-desc">Akun baru langsung bisa dipakai untuk masuk ke aplikasi atau panel.</p>
            </div>
            <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                @include('admin.partials.user-fields', ['user' => null, 'roles' => $roles, 'departments' => $departments, 'roleLabels' => $roleLabels, 'isEdit' => false, 'prefix' => 'createUser'])
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan</button>
                </div>
            </form>
        </div>
    </dialog>

    @foreach($users as $user)
        <dialog id="editUser{{ $user->id }}" class="modal" aria-labelledby="editUserTitle{{ $user->id }}">
            <div class="modal-head">
                <div>
                    <h2 class="modal-title" id="editUserTitle{{ $user->id }}">Ubah {{ $user->name }}</h2>
                    <p class="modal-desc">Perbarui data akun dan hak akses.</p>
                </div>
                <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" data-confirm="Simpan perubahan pengguna ini?">
                    @csrf
                    @method('PUT')
                    @include('admin.partials.user-fields', ['user' => $user, 'roles' => $roles, 'departments' => $departments, 'roleLabels' => $roleLabels, 'isEdit' => true, 'prefix' => 'editUser'.$user->id])
                    <div class="form-actions">
                        <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                        <button type="submit" class="btn btn--primary">Simpan perubahan</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endforeach
@endsection
