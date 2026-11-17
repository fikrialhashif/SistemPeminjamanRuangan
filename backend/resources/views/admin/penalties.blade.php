@extends('admin.layout')

@section('title', 'Strike & suspensi')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Strike &amp; suspensi</h1>
            <p class="plate-desc">Catat pelanggaran, batalkan strike, dan kelola pembatasan akun pengguna.</p>
        </div>
        <div class="plate-aside">
            <button type="button" class="btn btn--onplate" data-open-dialog="createStrike">Catat strike</button>
            <button type="button" class="btn btn--onplate" data-open-dialog="createSuspension">Buat suspensi</button>
        </div>
    </header>

    <div class="stack">
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Riwayat strike</h2>
                <span class="tag">{{ number_format($strikes->total(), 0, ',', '.') }}</span>
            </div>
            @if($strikes->isEmpty())
                <div class="empty">
                    <p class="empty-title">Belum ada strike.</p>
                    <p>Strike bisa dicatat manual dari halaman ini atau otomatis dari check-in gagal.</p>
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
                                            {{ $strike->created_at->locale('id')->isoFormat('D MMM Y') }}
                                            <span class="cell-time-sub">{{ $strike->created_at->format('H:i') }}</span>
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
                                        @if(! $strike->cancelled_at)
                                            <form method="POST" action="{{ route('admin.strikes.cancel', $strike) }}" class="inline-form">
                                                @csrf
                                                <input class="input input--sm" type="text" name="reason" placeholder="Alasan pembatalan" required>
                                                <button type="submit" class="btn btn--ghost btn--sm">Batalkan</button>
                                            </form>
                                        @else
                                            <span class="cell-muted">Oleh {{ $strike->cancelledBy->name ?? 'sistem' }}</span>
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
                <h2 class="panel-title">Suspensi</h2>
                <span class="tag">{{ number_format($suspensions->total(), 0, ',', '.') }}</span>
            </div>
            @if($suspensions->isEmpty())
                <div class="empty">
                    <p class="empty-title">Belum ada suspensi.</p>
                    <p>Suspensi otomatis dibuat saat jumlah strike melewati ambang.</p>
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
                                    <td data-label="Mulai">{{ $suspension->starts_at->locale('id')->isoFormat('D MMM Y H:i') }}</td>
                                    <td data-label="Selesai">{{ $suspension->ends_at->locale('id')->isoFormat('D MMM Y H:i') }}</td>
                                    <td data-label="Alasan">{{ $suspension->reason ?: '—' }}</td>
                                    <td data-label="Status">
                                        @if($suspension->ends_at->isFuture())
                                            <span class="tag tag--amber">Aktif</span>
                                        @else
                                            <span class="tag tag--plain">Selesai</span>
                                        @endif
                                    </td>
                                    <td data-label="Aksi" class="data-actions">
                                        @if($suspension->ends_at->isFuture())
                                            <form method="POST" action="{{ route('admin.suspensions.end', $suspension) }}" data-confirm="Akhiri suspensi {{ $suspension->user->name ?? '' }} sekarang?">
                                                @csrf
                                                <button type="submit" class="btn btn--ghost btn--sm">Akhiri</button>
                                            </form>
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
                <h2 class="modal-title" id="createStrikeTitle">Catat strike</h2>
                <p class="modal-desc">Strike otomatis memicu suspensi jika melewati ambang di pengaturan.</p>
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
                        <input class="input" type="text" id="strike_reason" name="reason" value="{{ old('reason') }}" placeholder="Contoh: Tidak check-in tanpa kabar" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan strike</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="createSuspension" class="modal" aria-labelledby="createSuspensionTitle">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="createSuspensionTitle">Buat suspensi</h2>
                <p class="modal-desc">Pengguna tidak bisa membuat booking selama masa suspensi.</p>
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
                        <input class="input" type="datetime-local" id="suspension_start" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="suspension_end">Selesai</label>
                        <input class="input" type="datetime-local" id="suspension_end" name="ends_at" value="{{ old('ends_at', now()->addDays(7)->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="field field--wide">
                        <label class="field-label" for="suspension_reason">Alasan</label>
                        <textarea class="input" id="suspension_reason" name="reason" rows="3" placeholder="Alasan suspensi">{{ old('reason') }}</textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan suspensi</button>
                </div>
            </form>
        </div>
    </dialog>
@endsection
