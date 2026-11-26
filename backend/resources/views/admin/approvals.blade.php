@extends('admin.layout')

@section('title', 'Persetujuan')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Persetujuan</h1>
            <p class="plate-desc">Putuskan permintaan peminjaman sebelum batas waktunya.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value plate-stat-value--amber">{{ number_format($approvals->total(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">menunggu keputusan</span>
            </div>
        </div>
    </header>

    <section class="panel">
        @if($approvals->isEmpty())
            <div class="empty">
                <p class="empty-title">Antrian sudah bersih.</p>
                <p>Tidak ada permintaan yang menunggu keputusan saat ini.</p>
                <a class="empty-link" href="{{ route('admin.dashboard') }}">Kembali ke ringkasan</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Permintaan</th>
                            <th scope="col">Pemohon</th>
                            <th scope="col">Ruangan atau aset</th>
                            <th scope="col">Jadwal</th>
                            <th scope="col">Tahap</th>
                            <th scope="col">Batas waktu</th>
                            <th scope="col">Keputusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($approvals as $approval)
                            <tr>
                                <td data-label="Permintaan">
                                    <div class="cell-title">{{ $approval->booking->title ?? 'Peminjaman #'.$approval->booking_id }}</div>
                                    <div class="cell-sub">Permintaan #{{ $approval->booking_id }}</div>
                                </td>
                                <td data-label="Pemohon">
                                    <div class="person">
                                        <span class="person-initial" aria-hidden="true">{{ strtoupper(mb_substr($approval->booking->user->name ?? '?', 0, 1)) }}</span>
                                        <span>{{ $approval->booking->user->name ?? 'Tidak dikenal' }}</span>
                                    </div>
                                </td>
                                <td data-label="Ruangan atau aset">{{ $approval->booking->resource->name ?? 'Tidak dikenal' }}</td>
                                <td data-label="Jadwal">
                                    @if($approval->booking?->starts_at)
                                        <div class="cell-time">
                                            {{ $approval->booking->starts_at->wib()->locale('id')->isoFormat('D MMM Y') }}
                                            <span class="cell-time-sub">{{ $approval->booking->starts_at->wib()->format('H:i') }}&ndash;{{ $approval->booking->ends_at->wib()->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="cell-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td data-label="Tahap"><span class="tag tag--plain">Tahap {{ $approval->level }}</span></td>
                                <td data-label="Batas waktu">
                                    @if($approval->expires_at)
                                        <div class="cell-time {{ $approval->expires_at->isPast() ? 'overdue' : '' }}">
                                            {{ $approval->expires_at->wib()->locale('id')->isoFormat('D MMM Y') }}
                                            <span class="cell-time-sub">{{ $approval->expires_at->wib()->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="cell-muted">Tanpa batas</span>
                                    @endif
                                </td>
                                <td data-label="Keputusan" class="data-actions">
                                    @if($decidableIds->contains($approval->id))
                                        <form method="POST" action="{{ route('admin.approvals.approve', $approval) }}" class="decide">
                                            @csrf
                                            <input type="text" name="notes" class="input" placeholder="Catatan (boleh kosong)" aria-label="Catatan untuk permintaan {{ $approval->booking->title ?? '#'.$approval->booking_id }}">
                                            <button type="submit" class="btn btn--primary btn--sm">Setujui</button>
                                            <button type="submit" formaction="{{ route('admin.approvals.reject', $approval) }}" class="btn btn--reject btn--sm">Tolak</button>
                                        </form>
                                    @else
                                        <span class="cell-muted">Anda tidak punya izin untuk memutuskan.</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{ $approvals->links() }}
@endsection
