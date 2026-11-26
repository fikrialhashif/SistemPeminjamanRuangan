@extends('admin.layout')

@section('title', 'Pelanggaran & pembatasan akun')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Pelanggaran &amp; pembatasan akun</h1>
            <p class="plate-desc">Catat pelanggaran, batalkan catatan pelanggaran, dan kelola pembatasan akun pengguna.</p>
        </div>
        <div class="plate-aside">
            @if(auth()->user()->hasPermission('penalties.create'))
                <button type="button" class="btn btn--onplate" data-open-dialog="createStrike">Catat pelanggaran</button>
                <button type="button" class="btn btn--onplate" data-open-dialog="createSuspension">Batasi akun</button>
            @endif
        </div>
    </header>

    <div class="stack">
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Riwayat pelanggaran</h2>
                <span class="tag">{{ number_format($strikes->total(), 0, ',', '.') }}</span>
            </div>
            @if($strikes->isEmpty())
                <div class="empty">
                    <p class="empty-title">Belum ada pelanggaran.</p>
                    <p>Pelanggaran bisa dicatat manual dari halaman ini atau otomatis saat verifikasi kehadiran gagal.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data data--stack">
                        <thead>
                            <tr>
                                <th scope="col">Waktu</th>
                                <th scope="col">Pengguna</th>
                                <th scope="col">Alasan</th>
                                <th scope="col">Status</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($strikes as $strike)
                                <tr>
                                    <td data-label="Waktu">
                                        <div class="cell-time">
                                            {{ $strike->created_at->wib()->locale('id')->isoFormat('D MMM Y') }}
                                            <span class="cell-time-sub">{{ $strike->created_at->wib()->format('H:i') }}</span>
                                        </div>
                                    </td>
                                    <td data-label="Pengguna">
                                        <div class="cell-title">{{ $strike->user->name ?? 'Tidak dikenal' }}</div>
                                        <div class="cell-sub">{{ $strike->user->email ?? '' }}</div>
                                    </td>
                                    <td data-label="Alasan">
                                        {{ $strike->reason }}
                                        @if($strike->cancelled_at)
                                            <div class="cell-sub">Dibatalkan: {{ $strike->cancel_reason }}</div>
                                        @endif
                                    </td>
                                    <td data-label="Status">
                                        @if($strike->cancelled_at)
                                            <span class="tag tag--plain">Dibatalkan</span>
                                        @else
                                            <span class="tag tag--amber">Aktif</span>
                                        @endif
                                    </td>
                                    <td data-label="Aksi" class="data-actions">
                                        @if(! $strike->cancelled_at && auth()->user()->hasPermission('penalties.update'))
                                            <form method="POST" action="{{ route('admin.strikes.cancel', $strike) }}" class="inline-form">
                                                @csrf
                                                <input class="input input--sm" type="text" name="reason" placeholder="Alasan pembatalan" required>
                                                <button type="submit" class="btn btn--ghost btn--sm">Batalkan</button>
                                            </form>
                                        @elseif($strike->cancelled_at)
                                            <span class="cell-muted">Oleh {{ $strike->cancelledBy->name ?? 'sistem' }}</span>
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

        {{ $strikes->links() }}

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Pembatasan akun</h2>
                <span class="tag">{{ number_format($suspensions->total(), 0, ',', '.') }}</span>
            </div>
            @if($suspensions->isEmpty())
                <div class="empty">
                    <p class="empty-title">Belum ada pembatasan akun.</p>
                    <p>Pembatasan akun otomatis dibuat saat jumlah pelanggaran melewati batas.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data data--stack">
                        <thead>
                            <tr>
                                <th scope="col">Pengguna</th>
                                <th scope="col">Mulai</th>
                                <th scope="col">Selesai</th>
                                <th scope="col">Alasan</th>
                                <th scope="col">Status</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($suspensions as $suspension)
                                <tr>
                                    <td data-label="Pengguna">
                                        <div class="cell-title">{{ $suspension->user->name ?? 'Tidak dikenal' }}</div>
                                        <div class="cell-sub">{{ $suspension->user->email ?? '' }}</div>
                                    </td>
                                    <td data-label="Mulai">{{ $suspension->starts_at->wib()->locale('id')->isoFormat('D MMM Y H:i') }}</td>
                                    <td data-label="Selesai">{{ $suspension->ends_at->wib()->locale('id')->isoFormat('D MMM Y H:i') }}</td>
                                    <td data-label="Alasan">{{ $suspension->reason ?: '—' }}</td>
                                    <td data-label="Status">
                                        @if($suspension->ends_at->isFuture())
                                            <span class="tag tag--amber">Aktif</span>
                                        @else
                                            <span class="tag tag--plain">Selesai</span>
                                        @endif
                                    </td>
                                    <td data-label="Aksi" class="data-actions">
                                        @if($suspension->ends_at->isFuture() && auth()->user()->hasPermission('penalties.update'))
                                            <form method="POST" action="{{ route('admin.suspensions.end', $suspension) }}" data-confirm="Akhiri pembatasan akun {{ $suspension->user->name ?? '' }} sekarang?">
                                                @csrf
                                                <button type="submit" class="btn btn--ghost btn--sm">Akhiri</button>
                                            </form>
                                        @elseif($suspension->ends_at->isFuture())
                                            <span class="cell-muted">&mdash;</span>
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

        {{ $suspensions->links() }}
    </div>

    <dialog id="createStrike" class="modal" aria-labelledby="createStrikeTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createStrikeTitle">Catat pelanggaran</h2>
                <p class="modal-desc">Pelanggaran otomatis memicu pembatasan akun jika melewati batas di pengaturan.</p>
            </div>
            <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.strikes.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field field--wide">
                        <label class="field-label" for="strike_user">Pengguna</label>
                        <select class="input" id="strike_user" name="user_id" required>
                            <option value="">Pilih pengguna</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field field--wide">
                        <label class="field-label" for="strike_reason">Alasan</label>
                        <input class="input" type="text" id="strike_reason" name="reason" value="{{ old('reason') }}" placeholder="Contoh: Tidak melakukan verifikasi tanpa kabar" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan pelanggaran</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="createSuspension" class="modal" aria-labelledby="createSuspensionTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createSuspensionTitle">Batasi akun</h2>
                <p class="modal-desc">Pengguna tidak bisa meminjam selama masa pembatasan akun.</p>
            </div>
            <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.suspensions.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field field--wide">
                        <label class="field-label" for="suspension_user">Pengguna</label>
                        <select class="input" id="suspension_user" name="user_id" required>
                            <option value="">Pilih pengguna</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="suspension_start">Mulai</label>
                        <input class="input" type="datetime-local" id="suspension_start" name="starts_at" value="{{ old('starts_at', now()->wib()->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="suspension_end">Selesai</label>
                        <input class="input" type="datetime-local" id="suspension_end" name="ends_at" value="{{ old('ends_at', now()->wib()->addDays(7)->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="field field--wide">
                        <label class="field-label" for="suspension_reason">Alasan</label>
                        <textarea class="input" id="suspension_reason" name="reason" rows="3" placeholder="Alasan pembatasan akun">{{ old('reason') }}</textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan pembatasan</button>
                </div>
            </form>
        </div>
    </dialog>
@endsection
