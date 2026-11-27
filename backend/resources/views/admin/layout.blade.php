<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2b5f4a">
    <title>@yield('title', 'Panel admin') | Ruang</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    @stack('head')
</head>
<body>
    @php
        $roleName = auth()->user()->roles->first()?->name;
        $isAdmin = auth()->user()->isAdmin();
        $roleLabel = match ($roleName) {
            'admin' => 'Admin',
            'approver' => 'Penyetuju',
            'resource_manager' => 'Pengelola ruangan dan aset',
            default => 'Panel admin',
        };
    @endphp
    <div class="shell">
        <aside class="rail">
            <a class="brand" href="{{ route('admin.dashboard') }}">
                <img class="brand-logo" src="{{ asset('skkmigasxbsp.png') }}" alt="Logo SKK Migas BSP">
                <span class="brand-text">
                    <span class="brand-name">Siboru</span>
                    <span class="brand-sub">Sistem Peminjaman Ruangan</span>
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
                @if(auth()->user()->canAccessApprovals())
                    <a href="{{ route('admin.approvals') }}" class="nav-link" @if(request()->routeIs('admin.approvals')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M7 3.5h6"></path>
                            <path d="M5 5.5h10a1 1 0 0 1 1 1V16a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6.5a1 1 0 0 1 1-1Z"></path>
                            <path d="m7.5 11 1.8 1.8 3.4-3.6"></path>
                        </svg>
                        <span>Persetujuan</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('resources.view'))
                    <a href="{{ route('admin.resources') }}" class="nav-link" @if(request()->routeIs('admin.resources', 'admin.resources.edit')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 17V3.5h8V17"></path>
                            <path d="M12 8h4v9"></path>
                            <path d="M2.5 17h15"></path>
                            <path d="M9 7.5v1"></path>
                        </svg>
                        <span>Ruangan &amp; aset</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('bookings.view'))
                    <a href="{{ route('admin.bookings') }}" class="nav-link" @if(request()->routeIs('admin.bookings')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="10" cy="10" r="7"></circle>
                            <path d="M10 6v4l2.5 1.5"></path>
                        </svg>
                        <span>Peminjaman</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('loans.view'))
                    <a href="{{ route('admin.loans') }}" class="nav-link" @if(request()->routeIs('admin.loans')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 6.5h12v10H4z"></path>
                            <path d="M7 6.5V4h6v2.5"></path>
                            <path d="M8 11h4"></path>
                        </svg>
                        <span>Peminjaman aset</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('categories.view') || auth()->user()->hasPermission('facilities.view'))
                    @php($masterDataActive = request()->routeIs('admin.categories', 'admin.facilities'))
                    <div class="nav-group">
                        <button type="button" class="nav-link nav-toggle" data-nav-toggle aria-expanded="{{ $masterDataActive ? 'true' : 'false' }}">
                            <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <ellipse cx="10" cy="5" rx="6" ry="2.5"></ellipse>
                                <path d="M4 5v5c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V5"></path>
                                <path d="M4 10v5c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-5"></path>
                            </svg>
                            <span>Kategori &amp; Fasilitas</span>
                            <svg class="nav-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m6 8 4 4 4-4"></path>
                            </svg>
                        </button>
                        <div class="nav-sub" @if(! $masterDataActive) hidden @endif>
                            @if(auth()->user()->hasPermission('categories.view'))
                                <a href="{{ route('admin.categories') }}" class="nav-link nav-link--sub" @if(request()->routeIs('admin.categories', 'admin.categories.*')) aria-current="page" @endif>
                                    <span>Kategori</span>
                                </a>
                            @endif
                            @if(auth()->user()->hasPermission('facilities.view'))
                                <a href="{{ route('admin.facilities') }}" class="nav-link nav-link--sub" @if(request()->routeIs('admin.facilities', 'admin.facilities.*')) aria-current="page" @endif>
                                    <span>Fasilitas</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
                @if(auth()->user()->hasPermission('locations.view'))
                    <a href="{{ route('admin.locations') }}" class="nav-link" @if(request()->routeIs('admin.locations')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10 17s5-4.2 5-8a5 5 0 1 0-10 0c0 3.8 5 8 5 8Z"></path>
                            <circle cx="10" cy="9" r="1.6"></circle>
                        </svg>
                        <span>Lokasi &amp; area</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('penalties.view'))
                    <a href="{{ route('admin.penalties') }}" class="nav-link" @if(request()->routeIs('admin.penalties')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10 3 3.5 16.5h13L10 3Z"></path>
                            <path d="M10 8v4"></path>
                            <path d="M10 14.5v.1"></path>
                        </svg>
                        <span>Pelanggaran &amp; pembatasan akun</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('reports.view'))
                    <a href="{{ route('admin.reports') }}" class="nav-link" @if(request()->routeIs('admin.reports')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 16.5V10"></path>
                            <path d="M10 16.5V4"></path>
                            <path d="M16 16.5v-4"></path>
                            <path d="M3 16.5h14"></path>
                        </svg>
                        <span>Laporan</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('users.view'))
                    <a href="{{ route('admin.users') }}" class="nav-link" @if(request()->routeIs('admin.users')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="8" cy="7" r="2.5"></circle>
                            <path d="M3.5 16c0-2.5 2-4.5 4.5-4.5s4.5 2 4.5 4.5"></path>
                            <path d="M13 7.5a2 2 0 1 1 0 4"></path>
                            <path d="M14 12.5c1.6.6 2.5 1.9 2.5 3.5"></path>
                        </svg>
                        <span>Pengguna</span>
                    </a>
                @endif
                @if($isAdmin)
                    <a href="{{ route('admin.access') }}" class="nav-link" @if(request()->routeIs('admin.access')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="7" cy="10" r="3"></circle>
                            <path d="M10 10h7"></path>
                            <path d="M14 10v3"></path>
                            <path d="M17 10v2"></path>
                        </svg>
                        <span>Manajemen akses</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('settings.view'))
                    <a href="{{ route('admin.settings') }}" class="nav-link" @if(request()->routeIs('admin.settings')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="10" cy="10" r="2.5"></circle>
                            <path d="M10 3.5v1.2M10 15.3v1.2M3.5 10h1.2M15.3 10h1.2M5.4 5.4l.9.9M13.7 13.7l.9.9M14.6 5.4l-.9.9M6.3 13.7l-.9.9"></path>
                        </svg>
                        <span>Pengaturan</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('audit.view'))
                    <a href="{{ route('admin.audit') }}" class="nav-link" @if(request()->routeIs('admin.audit')) aria-current="page" @endif>
                        <svg class="nav-glyph" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3.5 5.5h13"></path>
                            <path d="M3.5 10h13"></path>
                            <path d="M3.5 14.5h8"></path>
                            <circle cx="15.5" cy="14.5" r="1.2"></circle>
                        </svg>
                        <span>Riwayat aktivitas</span>
                    </a>
                @endif
            </nav>

            <div class="rail-foot">
                <button type="button" class="who who--button" data-open-dialog="accountDialog" aria-haspopup="dialog" aria-controls="accountDialog" aria-label="Buka pengaturan akun">
                    <span class="who-initial" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="who-text">
                        <span class="who-name">{{ auth()->user()->name }}</span>
                        <span class="who-role">{{ $roleLabel }}</span>
                    </span>
                </button>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="logout">Keluar</button>
                </form>
            </div>
        </aside>

        <main class="workspace">
            @if($errors->any())
                <div class="alert alert--page" role="alert">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @yield('content')
            @include('admin.partials.account-dialog')
        </main>
    </div>

    <div class="toast-stack" aria-live="polite">
        @if(session('success'))
            <div class="toast" role="status" data-toast>
                <span class="toast-icon" aria-hidden="true">&#10003;</span>
                <span class="toast-message">{{ session('success') }}</span>
                <span class="toast-progress" aria-hidden="true"></span>
            </div>
        @endif
    </div>

    <dialog id="confirmDialog" class="modal confirm-dialog" aria-labelledby="confirmDialogTitle" aria-describedby="confirmDialogMessage">
        <div class="modal-head">
            <div>
                <h2 class="modal-title" id="confirmDialogTitle">Konfirmasi</h2>
                <p class="modal-desc" id="confirmDialogMessage"></p>
            </div>
            <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-actions">
                <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                <button type="button" class="btn btn--primary" data-confirm-accept>Ya, lanjutkan</button>
            </div>
        </div>
    </dialog>

    <script>
        document.querySelectorAll('[data-open-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = document.getElementById(button.dataset.openDialog);
                if (dialog) {
                    dialog.showModal();
                    dialog.dispatchEvent(new CustomEvent('dialog:open'));
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

        document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var expanded = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                var submenu = button.parentElement.querySelector('.nav-sub');
                if (submenu) {
                    submenu.hidden = expanded;
                }
            });
        });

        document.querySelectorAll('[data-toast]').forEach(function (toast) {
            window.setTimeout(function () {
                toast.classList.add('is-leaving');

                window.setTimeout(function () {
                    toast.remove();
                }, 220);
            }, 5000);
        });

        var confirmDialog = document.getElementById('confirmDialog');
        var confirmMessage = document.getElementById('confirmDialogMessage');
        var confirmAccept = confirmDialog ? confirmDialog.querySelector('[data-confirm-accept]') : null;
        var pendingForm = null;
        var pendingSubmitter = null;
        var confirmedForm = null;

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (confirmedForm === form) {
                    confirmedForm = null;

                    return;
                }

                event.preventDefault();
                pendingForm = form;
                pendingSubmitter = event.submitter || null;

                if (confirmDialog && confirmMessage) {
                    confirmMessage.textContent = (pendingSubmitter && pendingSubmitter.dataset.confirm)
                        || form.dataset.confirm;
                    confirmDialog.showModal();
                }
            });
        });

        if (confirmDialog) {
            confirmDialog.addEventListener('close', function () {
                pendingForm = null;
                pendingSubmitter = null;
            });
        }

        if (confirmAccept) {
            confirmAccept.addEventListener('click', function () {
                if (! pendingForm) {
                    confirmDialog.close();

                    return;
                }

                var form = pendingForm;
                var submitter = pendingSubmitter;

                pendingForm = null;
                pendingSubmitter = null;
                confirmedForm = form;

                confirmDialog.close();

                if (submitter) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }

                window.setTimeout(function () {
                    confirmedForm = null;
                }, 0);
            });
        }

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

        function clearFieldError(field) {
            field.classList.remove('input--error');

            var error = field.nextElementSibling;

            if (error && error.classList.contains('field-error')) {
                error.remove();
            }
        }

        function showFieldError(field, message) {
            field.classList.add('input--error');

            var error = field.nextElementSibling;

            if (! error || ! error.classList.contains('field-error')) {
                error = document.createElement('span');
                error.className = 'field-error';
                error.setAttribute('role', 'alert');
                field.insertAdjacentElement('afterend', error);
            }

            error.textContent = message;
        }

        document.querySelectorAll('dialog.modal form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var dialog = form.closest('dialog');
                var marker = form.querySelector('input[name="form_marker"]');

                if (! marker) {
                    marker = document.createElement('input');
                    marker.type = 'hidden';
                    marker.name = 'form_marker';
                    form.appendChild(marker);
                }

                marker.value = dialog ? dialog.id : '';
            });

            form.addEventListener('invalid', function (event) {
                event.preventDefault();

                var field = event.target;
                var message = field.validity.valueMissing ? 'Wajib diisi.' : field.validationMessage;

                showFieldError(field, message);

                var firstInvalid = form.querySelector(':invalid');

                if (field === firstInvalid) {
                    window.setTimeout(function () {
                        firstInvalid.focus();
                    }, 0);
                }
            }, true);

            form.addEventListener('input', function (event) {
                if (event.target.validity.valid) {
                    clearFieldError(event.target);
                }
            });

            form.addEventListener('change', function (event) {
                if (event.target.validity.valid) {
                    clearFieldError(event.target);
                }
            });
        });

        @if($errors->any())
            var formMarker = @json(old('form_marker'));
            var errorScope = formMarker ? document.getElementById(formMarker) : document;
            var validationErrors = @json($errors->messages());

            if (errorScope) {
                errorScope.querySelectorAll('form [name]:not([type="hidden"])').forEach(function (field) {
                    var key = field.name.replace(/\[([^\]]*)\]/g, function (_, part) {
                        return part === '' ? '.0' : '.' + part;
                    });
                    var messages = [field.name, key].flatMap(function (candidate) {
                        return validationErrors[candidate] || [];
                    });

                    if (messages.length) {
                        showFieldError(field, messages[0]);
                    }
                });

                if (errorScope.tagName === 'DIALOG') {
                    errorScope.showModal();
                }
            }
        @endif
    </script>
    @stack('scripts')
</body>
</html>
