@extends('admin.layout')

@section('title', 'Ringkasan')

@section('content')
    @php
        $today = now()->locale('id');
    @endphp

    <header class="plate">
        <div>
            <h1 class="plate-title">Ringkasan</h1>
            <p class="plate-desc">Keadaan peminjaman dan antrian persetujuan saat ini.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-date">
                <span class="plate-date-day">{{ $today->isoFormat('dddd') }}</span>
                <span class="plate-date-full">{{ $today->isoFormat('D MMMM Y') }}</span>
            </div>
        </div>
    </header>

    <section class="board" aria-label="Statistik peminjaman">
        <div class="board-cell">
            <span class="board-label">Total peminjaman</span>
            <span class="board-value">{{ number_format($totalBookings, 0, ',', '.') }}</span>
            <span class="board-note">Sepanjang waktu</span>
        </div>
        <a href="{{ route('admin.approvals') }}" class="board-cell board-cell--alert">
            <span class="board-label">Menunggu persetujuan</span>
            <span class="board-value">{{ number_format($pendingApprovals, 0, ',', '.') }}</span>
            <span class="board-note">Buka antrian</span>
        </a>
        <div class="board-cell">
            <span class="board-label">Ruangan &amp; aset aktif</span>
            <span class="board-value">{{ number_format($activeResources, 0, ',', '.') }}</span>
            <span class="board-note">Siap dipinjam</span>
        </div>
        <div class="board-cell">
            <span class="board-label">Catatan pelanggaran</span>
            <span class="board-value">{{ number_format($totalStrikes, 0, ',', '.') }}</span>
            <span class="board-note">Pelanggaran tercatat</span>
        </div>
    </section>

    <div class="split">
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Perlu keputusan</h2>
                <a class="panel-link" href="{{ route('admin.approvals') }}">Lihat semua</a>
            </div>

            @if($recentApprovals->isEmpty())
                <div class="empty">
                    <p class="empty-title">Antrian sudah bersih.</p>
                    <p>Tidak ada permintaan yang menunggu keputusan saat ini.</p>
                    <a class="empty-link" href="{{ route('admin.audit') }}">Baca riwayat aktivitas</a>
                </div>
            @else
                <ul class="ledger">
                    @foreach($recentApprovals as $approval)
                        <li class="ledger-row">
                            <div class="ledger-main">
                                <div class="ledger-title">{{ $approval->booking->title ?? 'Peminjaman #'.$approval->booking_id }}</div>
                                <div class="ledger-meta">
                                    {{ $approval->booking->user->name ?? 'Pengguna tidak dikenal' }}
                                    &middot; {{ $approval->booking->resource->name ?? 'Ruangan atau aset tidak dikenal' }}
                                </div>
                            </div>
                            <div class="ledger-when">
                                @if($approval->expires_at)
                                    {{ $approval->expires_at->isPast() ? 'Lewat ' : 'Hingga ' }}{{ $approval->expires_at->locale('id')->isoFormat('D MMM, HH:mm') }}
                                @else
                                    Tanpa batas
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Pintasan</h2>
            </div>
            <div class="shortcuts">
                <a class="shortcut" href="{{ route('admin.approvals') }}">
                    <span>
                        <span class="shortcut-title">Tinjau persetujuan</span>
                        <span class="shortcut-note">Putuskan permintaan sebelum batas waktu.</span>
                    </span>
                    <span class="shortcut-count">{{ $pendingApprovals }} menunggu</span>
                </a>
                <a class="shortcut" href="{{ route('admin.resources') }}">
                    <span>
                        <span class="shortcut-title">Kelola ruangan &amp; aset</span>
                        <span class="shortcut-note">Tambah data, kelola status, cetak QR.</span>
                    </span>
                </a>
                <a class="shortcut" href="{{ route('admin.audit') }}">
                    <span>
                        <span class="shortcut-title">Baca riwayat aktivitas</span>
                        <span class="shortcut-note">Jejak perubahan data dan keputusan.</span>
                    </span>
                </a>
            </div>
        </section>
    </div>
@endsection
