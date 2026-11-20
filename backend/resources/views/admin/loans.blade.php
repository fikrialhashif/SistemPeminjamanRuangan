@extends('admin.layout')

@section('title', 'Peminjaman aset')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Peminjaman aset</h1>
            <p class="plate-desc">Catat serah terima dan pengembalian aset beserta kondisi saat dikembalikan.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($loans->total(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">peminjaman</span>
            </div>
        </div>
    </header>

    <form method="GET" action="{{ route('admin.loans') }}" class="toolbar">
        <div class="filter-grid">
            <div class="field">
                <label class="field-label" for="loan_status">Status</label>
                <select class="input" id="loan_status" name="status">
                    <option value="">Semua status</option>
                    @foreach(['reserved' => 'Dipesan', 'handed_over' => 'Diserahkan', 'in_use' => 'Dipakai', 'returned' => 'Dikembalikan', 'closed' => 'Selesai'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn--primary">Saring</button>
                <a class="btn btn--ghost" href="{{ route('admin.loans') }}">Atur ulang</a>
            </div>
        </div>
    </form>

    <section class="panel">
        @if($loans->isEmpty())
            <div class="empty">
                <p class="empty-title">Belum ada peminjaman aset.</p>
                <p>Data muncul setelah pengguna meminjam aset.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Aset</th>
                            <th scope="col">Peminjam</th>
                            <th scope="col">Status</th>
                            <th scope="col">Jatuh tempo</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($loans as $loan)
                            @php
                                $statusLabel = match ($loan->status->value) {
                                    'reserved' => 'Dipesan',
                                    'handed_over' => 'Diserahkan',
                                    'in_use' => 'Dipakai',
                                    'returned' => 'Dikembalikan',
                                    'closed' => 'Selesai',
                                    default => 'Status lain',
                                };
                            @endphp
                            <tr>
                                <td data-label="Aset">
                                    <div class="cell-title">{{ $loan->asset->resource->name ?? 'Aset tidak dikenal' }}</div>
                                    <div class="cell-sub">{{ $loan->asset->code ?? '—' }}</div>
                                </td>
                                <td data-label="Peminjam">
                                    <div class="person">
                                        <span class="person-initial" aria-hidden="true">{{ strtoupper(mb_substr($loan->user->name ?? '?', 0, 1)) }}</span>
                                        <span>
                                            <span class="cell-title">{{ $loan->user->name ?? 'Tidak dikenal' }}</span>
                                            <span class="cell-sub">{{ $loan->user->email ?? '' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="tag {{ in_array($loan->status->value, ['returned', 'closed']) ? 'tag--plain' : 'tag--amber' }}">{{ $statusLabel }}</span>
                                    @if($loan->returned_at)
                                        <div class="cell-sub">{{ $loan->returned_at->locale('id')->isoFormat('D MMM Y H:i') }}</div>
                                    @endif
                                </td>
                                <td data-label="Jatuh tempo">
                                    @if($loan->due_at)
                                        <div class="cell-time {{ $loan->due_at->isPast() && ! $loan->returned_at ? 'overdue' : '' }}">
                                            {{ $loan->due_at->locale('id')->isoFormat('D MMM Y') }}
                                            <span class="cell-time-sub">{{ $loan->due_at->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="cell-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td data-label="Aksi" class="data-actions">
                                    @if($loan->status->value === 'reserved')
                                        <form method="POST" action="{{ route('admin.loans.handover', $loan) }}" data-confirm="Tandai aset ini sudah diserahkan?">
                                            @csrf
                                            <button type="submit" class="btn btn--primary btn--sm">Serahkan</button>
                                        </form>
                                    @elseif(in_array($loan->status->value, ['handed_over', 'in_use']))
                                        <form method="POST" action="{{ route('admin.loans.return', $loan) }}" class="inline-form">
                                            @csrf
                                            <select class="input input--sm" name="condition" required>
                                                <option value="good">Baik</option>
                                                <option value="damaged">Rusak</option>
                                                <option value="lost">Hilang</option>
                                                <option value="maintenance">Perawatan</option>
                                            </select>
                                            <input class="input input--sm" type="text" name="notes" placeholder="Catatan (boleh kosong)">
                                            <button type="submit" class="btn btn--primary btn--sm">Kembalikan</button>
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

    {{ $loans->links() }}
@endsection
