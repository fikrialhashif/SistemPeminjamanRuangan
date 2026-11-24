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

    @if($charts)
        <section class="charts" aria-label="Grafik ringkasan">
            @foreach($charts as $chart)
                <article class="panel chart-card">
                    <div class="panel-head">
                        <h2 class="panel-title">{{ $chart['title'] }}</h2>
                        @if(! empty($chart['key']))
                            <form method="GET" action="{{ route('admin.dashboard') }}" class="chart-filter">
                                @foreach($periods as $key => $value)
                                    @if($key !== $chart['key'])
                                        <input type="hidden" name="periods[{{ $key }}]" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <label class="chart-filter-label" for="period_{{ $chart['key'] }}">Periode</label>
                                <select class="input input--sm" id="period_{{ $chart['key'] }}" name="periods[{{ $chart['key'] }}]" data-chart-period>
                                    @foreach($periodOptions as $option)
                                        <option value="{{ $option }}" @selected($option === $periods[$chart['key']])>{{ $option }} bulan</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn--ghost btn--sm">Terapkan</button>
                            </form>
                        @else
                            <span class="tag">{{ $chart['note'] }}</span>
                        @endif
                    </div>

                    @if(! empty($chart['key']))
                        <p class="chart-note">{{ $chart['note'] }}</p>
                    @endif

                    @if(empty($chart['items']))
                        <div class="empty">
                            <p class="empty-title">Belum ada data.</p>
                            <p>Grafik ini akan terisi setelah ada aktivitas yang relevan.</p>
                        </div>
                    @elseif($chart['kind'] === 'donut')
                        <div class="chart-donut-body" role="img" aria-label="{{ $chart['title'] }}, total {{ $chart['total'] }}">
                            <div class="chart-donut" style="background: {{ $chart['gradient'] }}" aria-hidden="true">
                                <span class="chart-donut-hole">
                                    <span class="chart-donut-total">{{ number_format($chart['total'], 0, ',', '.') }}</span>
                                    <span class="chart-donut-caption">total</span>
                                </span>
                            </div>
                            <ul class="chart-legend">
                                @foreach($chart['items'] as $item)
                                    <li class="chart-legend-row">
                                        <span class="chart-swatch" style="background: {{ $item['color'] }}" aria-hidden="true"></span>
                                        <span class="chart-legend-label">{{ $item['label'] }}</span>
                                        <span class="chart-legend-value">{{ number_format($item['value'], 0, ',', '.') }} <small>{{ $item['percent'] }}%</small></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="chart-bars" role="img" aria-label="{{ $chart['title'] }}">
                            @foreach($chart['items'] as $item)
                                <div class="chart-bar-row">
                                    <span class="chart-bar-label" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                                    <span class="chart-bar-track" aria-hidden="true"><span class="chart-bar-fill" style="width: {{ $item['percent'] }}%"></span></span>
                                    <span class="chart-bar-value">{{ number_format($item['value'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

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

    @push('scripts')
        <script>
            document.querySelectorAll('[data-chart-period]').forEach(function (select) {
                select.addEventListener('change', function () {
                    this.form.submit();
                });
            });
        </script>
    @endpush
@endsection
