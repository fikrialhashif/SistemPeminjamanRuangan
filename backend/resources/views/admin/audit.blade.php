@extends('admin.layout')

@section('title', 'Riwayat aktivitas')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Riwayat aktivitas</h1>
            <p class="plate-desc">Setiap perubahan data dan keputusan admin tercatat di sini.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($logs->total(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">catatan</span>
            </div>
        </div>
    </header>

    <form method="GET" action="{{ route('admin.audit') }}" class="toolbar">
        <div class="filter-grid filter-grid--audit">
            <div class="field">
                <label class="field-label" for="audit_search">Cari</label>
                <input class="input" type="search" id="audit_search" name="search" value="{{ request('search') }}" placeholder="Aksi, objek, nama, atau email">
            </div>
            <div class="field">
                <label class="field-label" for="audit_action">Aksi</label>
                <select class="input" id="audit_action" name="action">
                    <option value="">Semua aksi</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst(str_replace('_', ' ', $action)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="audit_type">Objek</label>
                <select class="input" id="audit_type" name="type">
                    <option value="">Semua objek</option>
                    @foreach($types as $type => $label)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="audit_from">Dari</label>
                <input class="input" type="date" id="audit_from" name="from" value="{{ request('from') }}">
            </div>
            <div class="field">
                <label class="field-label" for="audit_to">Sampai</label>
                <input class="input" type="date" id="audit_to" name="to" value="{{ request('to') }}">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn--primary">Terapkan</button>
                <a class="btn btn--ghost" href="{{ route('admin.audit') }}">Reset</a>
            </div>
        </div>
    </form>

    <section class="panel">
        @if($logs->isEmpty())
            <div class="empty">
                <p class="empty-title">Belum ada aktivitas.</p>
                <p>Catatan akan muncul setelah ada perubahan data atau keputusan admin.</p>
                <a class="empty-link" href="{{ route('admin.resources') }}">Kelola ruangan &amp; aset</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data data--stack">
                    <thead>
                        <tr>
                            <th scope="col">Waktu</th>
                            <th scope="col">Pengguna</th>
                            <th scope="col">Aksi</th>
                            <th scope="col">Objek</th>
                            <th scope="col">Alamat IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            @php
                                $actionLabel = match ($log->action) {
                                    'created' => 'Dibuat',
                                    'updated' => 'Diperbarui',
                                    'deleted' => 'Dihapus',
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Ditolak',
                                    'checked_in' => 'Check-in',
                                    'login' => 'Masuk',
                                    default => ucfirst(str_replace('_', ' ', $log->action)),
                                };
                            @endphp
                            <tr>
                                <td data-label="Waktu">
                                    <div class="cell-time">
                                        {{ $log->created_at->locale('id')->isoFormat('D MMM Y') }}
                                        <span class="cell-time-sub">{{ $log->created_at->format('H:i:s') }}</span>
                                    </div>
                                </td>
                                <td data-label="Pengguna">
                                    <div class="person">
                                        <span class="person-initial" aria-hidden="true">{{ strtoupper(mb_substr($log->user->name ?? '?', 0, 1)) }}</span>
                                        <span>{{ $log->user->name ?? 'Sistem' }}</span>
                                    </div>
                                </td>
                                <td data-label="Aksi">{{ $actionLabel }}</td>
                                <td data-label="Objek">
                                    @if($log->auditable_type)
                                        {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                    @else
                                        <span class="cell-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td data-label="Alamat IP">
                                    <span class="cell-ip">{{ $log->ip_address ?? '—' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{ $logs->links() }}
@endsection
