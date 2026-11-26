@extends('admin.layout')

@section('title', 'Peminjaman')

@section('content')
    @php
        $activeRoomCount = $activeRoomBookings->count();
        $assetsInUseCount = $activeLoans->pluck('asset_id')->unique()->count();
        $assetsAvailableCount = max($totalAssets - $assetsInUseCount, 0);
        $queueCount = $queuedEntries->count();
    @endphp

    <header class="plate">
        <div>
            <h1 class="plate-title">Peminjaman</h1>
            <p class="plate-desc">Pantau ruangan yang sedang dipakai dan aset yang sedang digunakan saat ini.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-date">
                <span class="plate-date-day">{{ now()->wib()->locale('id')->isoFormat('dddd') }}</span>
                <span class="plate-date-full">{{ now()->wib()->locale('id')->isoFormat('D MMMM Y') }}</span>
            </div>
        </div>
    </header>

    <section class="board" aria-label="Ringkasan penggunaan">
        <div class="board-cell">
            <span class="board-label">Ruangan sedang dipakai</span>
            <span class="board-value">{{ number_format($activeRoomCount, 0, ',', '.') }}</span>
            <span class="board-note">dari {{ number_format($totalRooms, 0, ',', '.') }} ruangan aktif</span>
        </div>
        <div class="board-cell">
            <span class="board-label">Antrian hari ini</span>
            <span class="board-value">{{ number_format($queueCount, 0, ',', '.') }}</span>
            <span class="board-note">ruangan &amp; aset yang akan mulai</span>
        </div>
        <div class="board-cell">
            <span class="board-label">Aset sedang dipakai</span>
            <span class="board-value">{{ number_format($assetsInUseCount, 0, ',', '.') }}</span>
            <span class="board-note">dari {{ number_format($totalAssets, 0, ',', '.') }} aset aktif</span>
        </div>
        <div class="board-cell">
            <span class="board-label">Aset tersedia</span>
            <span class="board-value">{{ number_format($assetsAvailableCount, 0, ',', '.') }}</span>
            <span class="board-note">siap dipinjam</span>
        </div>
    </section>

    <div class="split split--even">
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Ruangan sedang dipakai</h2>
                <a class="panel-link" href="{{ route('admin.resources') }}">Lihat ruangan</a>
            </div>

            @if($activeRoomBookings->isEmpty())
                <div class="empty">
                    <p class="empty-title">Tidak ada ruangan yang sedang dipakai.</p>
                    <p>Peminjaman yang sedang berjalan akan muncul di sini.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data data--stack">
                        <thead>
                            <tr>
                                <th scope="col">Ruangan</th>
                                <th scope="col">Pemakai</th>
                                <th scope="col">Waktu</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeRoomBookings as $booking)
                                @php
                                    $isCheckedIn = $booking->status->value === 'checked_in';
                                @endphp
                                <tr>
                                    <td data-label="Ruangan">
                                        <div class="cell-title">{{ $booking->resource->name ?? 'Ruangan tidak dikenal' }}</div>
                                        <div class="cell-sub">{{ $booking->resource->location->name ?? '—' }}</div>
                                    </td>
                                    <td data-label="Pemakai">
                                        <div class="person">
                                            <span class="person-initial" aria-hidden="true">{{ strtoupper(mb_substr($booking->user->name ?? '?', 0, 1)) }}</span>
                                            <span>
                                                <span class="cell-title">{{ $booking->user->name ?? 'Tidak dikenal' }}</span>
                                                <span class="cell-sub">{{ $booking->user->department->name ?? $booking->user->email ?? '' }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="Waktu">
                                        <div class="cell-time">
                                            {{ $booking->starts_at->wib()->format('H:i') }}–{{ $booking->ends_at->wib()->format('H:i') }}
                                            <span class="cell-time-sub">berakhir {{ $booking->ends_at->wib()->locale('id')->diffForHumans($now, ['short' => true, 'parts' => 1]) }}</span>
                                        </div>
                                    </td>
                                    <td data-label="Status">
                                        <span class="tag {{ $isCheckedIn ? 'tag--amber' : 'tag--plain' }}">{{ $isCheckedIn ? 'Sedang dipakai' : 'Disetujui' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Aset sedang dipakai</h2>
                <a class="panel-link" href="{{ route('admin.loans') }}">Lihat peminjaman</a>
            </div>

            @if($activeLoans->isEmpty())
                <div class="empty">
                    <p class="empty-title">Tidak ada aset yang sedang dipakai.</p>
                    <p>Aset yang sudah diserahkan akan muncul di sini.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data data--stack">
                        <thead>
                            <tr>
                                <th scope="col">Aset</th>
                                <th scope="col">Peminjam</th>
                                <th scope="col">Jatuh tempo</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeLoans as $loan)
                                @php
                                    $isOverdue = $loan->due_at && $loan->due_at->isPast();
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
                                        <div class="cell-sub">
                                            {{ $loan->asset->code ?? '—' }}@if($loan->booking) &middot; {{ $loan->booking->title }}@endif
                                        </div>
                                    </td>
                                    <td data-label="Peminjam">
                                        <div class="person">
                                            <span class="person-initial" aria-hidden="true">{{ strtoupper(mb_substr($loan->user->name ?? '?', 0, 1)) }}</span>
                                            <span>
                                                <span class="cell-title">{{ $loan->user->name ?? 'Tidak dikenal' }}</span>
                                                <span class="cell-sub">{{ $loan->user->department->name ?? $loan->user->email ?? '' }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="Jatuh tempo">
                                        @if($loan->due_at)
                                            <div class="cell-time {{ $isOverdue ? 'overdue' : '' }}">
                                                {{ $loan->due_at->wib()->format('H:i') }}
                                                <span class="cell-time-sub">{{ $loan->due_at->wib()->locale('id')->isoFormat('D MMM') }}</span>
                                            </div>
                                        @else
                                            <span class="cell-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td data-label="Status">
                                        <span class="tag {{ $isOverdue ? 'tag--amber' : 'tag--plain' }}">{{ $isOverdue ? 'Lewat tenggat' : $statusLabel }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <section class="panel panel--spaced">
        <div class="panel-head">
            <h2 class="panel-title">Antrian hari ini</h2>
            <span class="tag">{{ number_format($queueCount, 0, ',', '.') }} entri</span>
        </div>

        @if($queuedEntries->isEmpty())
            <div class="empty">
                <p class="empty-title">Tidak ada antrian hari ini.</p>
                <p>Jadwal ruangan dan aset yang akan mulai hari ini akan muncul di sini.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Kegiatan</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Pemesan</th>
                            <th scope="col">Mulai</th>
                            <th scope="col">Selesai</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($queuedEntries as $entry)
                            <tr>
                                <td data-label="Kegiatan">
                                    <div class="cell-title">{{ $entry['title'] }}</div>
                                    <div class="cell-sub">{{ $entry['subtitle'] }}</div>
                                </td>
                                <td data-label="Jenis">
                                    <span class="tag {{ $entry['type'] === 'Ruangan' ? 'tag--amber' : 'tag--plain' }}">{{ $entry['type'] }}</span>
                                </td>
                                <td data-label="Pemesan">
                                    <div class="person">
                                        <span class="person-initial" aria-hidden="true">{{ strtoupper(mb_substr($entry['user']->name ?? '?', 0, 1)) }}</span>
                                        <span>
                                            <span class="cell-title">{{ $entry['user']->name ?? 'Tidak dikenal' }}</span>
                                            <span class="cell-sub">{{ $entry['user']->department->name ?? $entry['user']->email ?? '' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Mulai">
                                    @if($entry['starts_at'])
                                        <div class="cell-time">
                                            {{ $entry['starts_at']->wib()->format('H:i') }}
                                            <span class="cell-time-sub">{{ $entry['starts_at']->locale('id')->diffForHumans($now, ['short' => true, 'parts' => 1]) }}</span>
                                        </div>
                                    @else
                                        <span class="cell-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td data-label="Selesai">
                                    {{ $entry['ends_at'] ? $entry['ends_at']->wib()->format('H:i') : '—' }}
                                </td>
                                <td data-label="Status">
                                    <span class="tag {{ $entry['status_tone'] }}">{{ $entry['status'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
