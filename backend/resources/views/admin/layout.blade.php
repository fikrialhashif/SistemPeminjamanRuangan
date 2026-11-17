<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2b5f4a">
    <title>@yield('title', 'Panel admin') | Ruang</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
    @php
        $roleName = auth()->user()->roles->first()?->name;
        $isAdmin = auth()->user()->isAdmin();
        $isResourceManager = auth()->user()->hasRole('resource_manager');
        $roleLabel = match ($roleName) {
            'admin' => 'Admin',
            'approver' => 'Approver',
            'resource_manager' => 'Pengelola aset',
            default => 'Panel admin',
        };
    @endphp
    <div class="shell">
        <aside class="rail">
            <a class="brand" href="{{ route('admin.dashboard') }}">
                <span class="brand-mark" aria-hidden="true">R</span>
                <span class="brand-text">
                    <span class="brand-name">Ruang</span>
                    <span class="brand-sub">Peminjaman ruangan &amp; aset</span>
                </span>
            </a>

            <nav class="nav" aria-label="Navigasi utama">
                <a href="{{ route('admin.dashboard') }}" class="nav-link" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="6" height="6" rx="1"></rect>
                        <rect x="11" y="3" width="6" height="6" rx="1"></rect>
                        <rect x="3" y="11" width="6" height="6" rx="1"></rect>
                        <rect x="11" y="11" width="6" height="6" rx="1"></rect>
                    </svg>
                    <span>Ringkasan</span>
                </a>
                <a href="{{ route('admin.approvals') }}" class="nav-link" @if(request()->routeIs('admin.approvals')) aria-current="page" @endif>
                    <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M7 3.5h6"></path>
                        <path d="M5 5.5h10a1 1 0 0 1 1 1V16a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6.5a1 1 0 0 1 1-1Z"></path>
                        <path d="m7.5 11 1.8 1.8 3.4-3.6"></path>
                    </svg>
                    <span>Persetujuan</span>
                </a>
                @if($isAdmin || $isResourceManager)
                    <a href="{{ route('admin.resources') }}" class="nav-link" @if(request()->routeIs('admin.resources', 'admin.resources.edit')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 17V3.5h8V17"></path>
                            <path d="M12 8h4v9"></path>
                            <path d="M2.5 17h15"></path>
                            <path d="M9 7.5v1"></path>
                        </svg>
                        <span>Ruangan &amp; aset</span>
                    </a>
                    <a href="{{ route('admin.loans') }}" class="nav-link" @if(request()->routeIs('admin.loans')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 6.5h12v10H4z"></path>
                            <path d="M7 6.5V4h6v2.5"></path>
                            <path d="M8 11h4"></path>
                        </svg>
                        <span>Peminjaman aset</span>
                    </a>
                @endif
                @if($isAdmin)
                    <a href="{{ route('admin.locations') }}" class="nav-link" @if(request()->routeIs('admin.locations')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10 17s5-4.2 5-8a5 5 0 1 0-10 0c0 3.8 5 8 5 8Z"></path>
                            <circle cx="10" cy="9" r="1.6"></circle>
                        </svg>
                        <span>Lokasi &amp; geofence</span>
                    </a>
                    <a href="{{ route('admin.penalties') }}" class="nav-link" @if(request()->routeIs('admin.penalties')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10 3 3.5 16.5h13L10 3Z"></path>
                            <path d="M10 8v4"></path>
                            <path d="M10 14.5v.1"></path>
                        </svg>
                        <span>Strike &amp; suspensi</span>
                    </a>
                    <a href="{{ route('admin.reports') }}" class="nav-link" @if(request()->routeIs('admin.reports')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 16.5V10"></path>
                            <path d="M10 16.5V4"></path>
                            <path d="M16 16.5v-4"></path>
                            <path d="M3 16.5h14"></path>
                        </svg>
                        <span>Laporan</span>
                    </a>
                    <a href="{{ route('admin.users') }}" class="nav-link" @if(request()->routeIs('admin.users')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="8" cy="7" r="2.5"></circle>
                            <path d="M3.5 16c0-2.5 2-4.5 4.5-4.5s4.5 2 4.5 4.5"></path>
                            <path d="M13 7.5a2 2 0 1 1 0 4"></path>
                            <path d="M14 12.5c1.6.6 2.5 1.9 2.5 3.5"></path>
                        </svg>
                        <span>Pengguna</span>
                    </a>
                    <a href="{{ route('admin.settings') }}" class="nav-link" @if(request()->routeIs('admin.settings')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="10" cy="10" r="2.5"></circle>
                            <path d="M10 3.5v1.2M10 15.3v1.2M3.5 10h1.2M15.3 10h1.2M5.4 5.4l.9.9M13.7 13.7l.9.9M14.6 5.4l-.9.9M6.3 13.7l-.9.9"></path>
                        </svg>
                        <span>Pengaturan</span>
                    </a>
                @endif
                <a href="{{ route('admin.audit') }}" class="nav-link" @if(request()->routeIs('admin.audit')) aria-current="page" @endif>
                    <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3.5 5.5h13"></path>
                        <path d="M3.5 10h13"></path>
                        <path d="M3.5 14.5h8"></path>
                        <circle cx="15.5" cy="14.5" r="1.2"></circle>
                    </svg>
                    <span>Riwayat aktivitas</span>
                </a>
            </nav>

            <div class="rail-foot">
                <div class="who">
                    <span class="who-initial" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="who-text">
                        <span class="who-name">{{ auth()->user()->name }}</span>
                        <span class="who-role">{{ $roleLabel }}</span>
                    </span>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="logout">Keluar</button>
                </form>
            </div>
        </aside>

        <main class="workspace">
            @if(session('success'))
                <div class="toast" role="status">
                    <span class="toast-icon" aria-hidden="true">&#10003;</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert--page" role="alert">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        document.querySelectorAll('[data-open-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = document.getElementById(button.dataset.openDialog);
                if (dialog) {
                    dialog.showModal();
                }
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = button.closest('dialog');
                if (dialog) {
                    dialog.close();
                }
            });
        });

        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });

        document.querySelectorAll('[data-resource-type]').forEach(function (select) {
            var form = select.closest('form');
            var groups = form ? form.querySelectorAll('[data-fields]') : [];

            function syncFields() {
                groups.forEach(function (group) {
                    var active = group.dataset.fields === select.value;
                    group.hidden = !active;
                    group.querySelectorAll('input, select, textarea').forEach(function (input) {
                        input.disabled = !active;
                    });
                });
            }

            select.addEventListener('change', syncFields);
            syncFields();
        });

        document.querySelectorAll('[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (! window.confirm(form.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                var submitter = event.submitter;
                if (submitter && submitter.matches('button, input[type="submit"]')) {
                    submitter.classList.add('is-loading');
                    submitter.setAttribute('aria-busy', 'true');
                }

                form.setAttribute('aria-busy', 'true');
            });
        });
    </script>
</body>
</html>
