@extends('admin.layout')

@section('title', 'Laporan')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Laporan</h1>
            <p class="plate-desc">Ringkasan utilisasi ruangan, no-show, aset populer, dan keberhasilan verifikasi check-in.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($totalBookings, 0, ',', '.') }}</span>
                <span class="plate-stat-label">booking pada periode</span>
            </div>
        </div>
    </header>

    <form method="GET" action="{{ route('admin.reports') }}" class="toolbar">
        <div class="filter-grid">
            <div class="field">
                <label class="field-label" for="report_from">Dari tanggal</label>
                <input class="input" type="date" id="report_from" name="from" value="{{ $fromInput }}">
            </div>
            <div class="field">
                <label class="field-label" for="report_to">Sampai tanggal</label>
                <input class="input" type="date" id="report_to" name="to" value="{{ $toInput }}">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn--primary">Terapkan</button>
                <a class="btn btn--ghost" href="{{ route('admin.reports') }}">Bulan ini</a>
            </div>
        </div>
    </form>

    <div class="board">
        <div class="board-cell">
            <span class="board-label">Total booking</span>
            <span class="board-value">{{ number_format($totalBookings, 0, ',', '.') }}</span>
            <span class="board-note">Semua status pada periode</span>
        </div>
        <div class="board-cell">
            <span class="board-label">Selesai</span>
            <span class="board-value">{{ number_format($completedBookings, 0, ',', '.') }}</span>
            <span class="board-note">Booking berstatus completed</span>
        </div>
        <div class="board-cell">
            <span class="board-label">No-show</span>
            <span class="board-value">{{ number_format($noShowBookings, 0, ',', '.') }}</span>
            <span class="board-note">{{ $noShowRate }}% dari total booking</span>
        </div>
        <div class="board-cell">
            <span class="board-label">Percobaan check-in</span>
            <span class="board-value">{{ number_format($verification['total_attempts'], 0, ',', '.') }}</span>
            <span class="board-note">QR {{ $verification['qr_success'] }}% · GPS {{ $verification['gps_success'] }}% · Wajah {{ $verification['face_success'] }}%</span>
        </div>
    </div>

    <div class="split">
        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Utilisasi ruangan</h2>
                <span class="tag">Completed</span>
            </div>
            @if($roomUtilization->isEmpty())
                <div class="empty">
                    <p class="empty-title">Belum ada data.</p>
                    <p>Booking ruangan yang selesai pada periode ini akan muncul di sini.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Ruangan</th>
                                <th scope="col">Booking</th>
                                <th scope="col">Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roomUtilization as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ number_format($row['bookings'], 0, ',', '.') }}</td>
                                    <td>{{ intdiv($row['minutes'], 60) }} jam {{ $row['minutes'] % 60 }} menit</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Aset populer</h2>
                <span class="tag">Peminjaman</span>
            </div>
            @if($popularAssets->isEmpty())
                <div class="empty">
                    <p class="empty-title">Belum ada data.</p>
                    <p>Peminjaman aset pada periode ini akan muncul di sini.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Aset</th>
                                <th scope="col">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($popularAssets as $row)
                                <tr>
                                    <td>
                                        <div class="cell-title">{{ $row->asset->resource->name ?? 'Aset' }}</div>
                                        <div class="cell-sub">{{ $row->asset->code ?? '—' }}</div>
                                    </td>
                                    <td>{{ number_format($row->loan_count, 0, ',', '.') }}</td>
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
            <h2 class="panel-title">Keberhasilan verifikasi</h2>
        </div>
        <div class="stats-grid">
            <div class="stat">
                <span class="stat-label">QR valid</span>
                <span class="stat-value">{{ $verification['qr_success'] }}%</span>
            </div>
            <div class="stat">
                <span class="stat-label">GPS valid</span>
                <span class="stat-value">{{ $verification['gps_success'] }}%</span>
            </div>
            <div class="stat">
                <span class="stat-label">Wajah valid</span>
                <span class="stat-value">{{ $verification['face_success'] }}%</span>
            </div>
            <div class="stat">
                <span class="stat-label">Total percobaan</span>
                <span class="stat-value">{{ number_format($verification['total_attempts'], 0, ',', '.') }}</span>
            </div>
        </div>
    </section>
@endsection
