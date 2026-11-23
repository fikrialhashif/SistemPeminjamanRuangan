@extends('admin.layout')

@section('title', 'Manajemen akses')

@section('content')
    <header class="plate">
        <div>
            <h1 class="plate-title">Manajemen akses</h1>
            <p class="plate-desc">Pilih peran, lalu centang halaman dan aksi yang boleh diakses. Perubahan tersimpan otomatis.</p>
        </div>
        <div class="plate-aside">
            <div class="plate-stat">
                <span class="plate-stat-value">{{ number_format($roleRows->count(), 0, ',', '.') }}</span>
                <span class="plate-stat-label">peran</span>
            </div>
        </div>
    </header>

    <section class="panel">
        <div class="panel-body">
            <div class="field">
                <label class="field-label" for="access_role">Peran</label>
                <select class="input" id="access_role" data-access-role>
                    @foreach($roleRows as $row)
                        <option value="{{ $row['role']->id }}">{{ $row['label'] }}</option>
                    @endforeach
                </select>
            </div>

            @foreach($roleRows as $row)
                <div class="stack" data-access-panel="{{ $row['role']->id }}" @if(! $loop->first) hidden @endif>
                    <p class="field-hint">{{ $row['description'] }}</p>

                    <form method="POST" action="{{ route('admin.access.update', $row['role']) }}" data-access-form>
                        @csrf
                        @method('PUT')

                        <div class="table-wrap">
                            <table class="data">
                                <thead>
                                    <tr>
                                        <th scope="col">Halaman</th>
                                        @foreach($actionLabels as $label)
                                            <th scope="col">{{ $label }}</th>
                                        @endforeach
                                        <th scope="col">Pilih semua aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row['catalog'] as $module => $permissions)
                                        @php($permissionByAction = collect($permissions)->keyBy(fn ($permission) => $permission->action()))
                                        <tr>
                                            <td data-label="Halaman">
                                                <div class="cell-title">{{ $permissions[0]->moduleLabel() }}</div>
                                                <!-- <div class="cell-sub">{{ $module }}</div> -->
                                            </td>
                                            @foreach($actionLabels as $action => $label)
                                                <td data-label="{{ $label }}">
                                                    @if($permissionByAction->has($action))
                                                        <label class="check check--inline">
                                                            <input
                                                                type="checkbox"
                                                                name="permissions[]"
                                                                value="{{ $permissionByAction[$action]->value }}"
                                                                @checked(in_array($permissionByAction[$action]->value, $row['current'], true))
                                                            >
                                                        </label>
                                                    @else
                                                        <span class="cell-muted">&mdash;</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td data-label="Pilih semua aksi">
                                                <label class="check check--inline">
                                                    <input type="checkbox" data-access-select-all>
                                                </label>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <span class="field-hint" data-access-status aria-live="polite">Perubahan tersimpan otomatis.</span>
                    </form>
                </div>
            @endforeach
        </div>
    </section>

    @push('scripts')
        <script>
            (function () {
                var select = document.querySelector('[data-access-role]');
                var panels = document.querySelectorAll('[data-access-panel]');

                function syncPanels() {
                    panels.forEach(function (panel) {
                        panel.hidden = panel.dataset.accessPanel !== select.value;
                    });
                }

                if (select) {
                    select.addEventListener('change', syncPanels);
                    syncPanels();
                }

                function syncSelectAll(form) {
                    form.querySelectorAll('[data-access-select-all]').forEach(function (selectAll) {
                        var row = selectAll.closest('tr');
                        var boxes = row ? row.querySelectorAll('input[name="permissions[]"]') : [];

                        selectAll.checked = boxes.length > 0 && Array.prototype.every.call(boxes, function (box) {
                            return box.checked;
                        });
                    });
                }

                document.querySelectorAll('[data-access-form]').forEach(function (form) {
                    var status = form.querySelector('[data-access-status]');
                    var boxes = form.querySelectorAll('input[name="permissions[]"]');
                    var allBoxes = form.querySelectorAll('input[type="checkbox"]');
                    var timer = null;

                    function setStatus(text) {
                        if (status) {
                            status.textContent = text;
                        }
                    }

                    function save() {
                        var data = new FormData(form);

                        allBoxes.forEach(function (input) {
                            input.disabled = true;
                        });
                        setStatus('Menyimpan...');

                        fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: data
                        }).then(function (response) {
                            if (! response.ok) {
                                throw new Error('request failed');
                            }
                            setStatus('Tersimpan.');
                        }).catch(function () {
                            setStatus('Gagal menyimpan. Muat ulang halaman lalu coba lagi.');
                        }).finally(function () {
                            allBoxes.forEach(function (input) {
                                input.disabled = false;
                            });
                            syncSelectAll(form);
                        });
                    }

                    function schedule() {
                        setStatus('Menyimpan...');
                        window.clearTimeout(timer);
                        timer = window.setTimeout(save, 200);
                    }

                    boxes.forEach(function (input) {
                        input.addEventListener('change', function () {
                            syncSelectAll(form);
                            schedule();
                        });
                    });

                    form.querySelectorAll('[data-access-select-all]').forEach(function (selectAll) {
                        selectAll.addEventListener('change', function () {
                            var row = selectAll.closest('tr');

                            if (! row) {
                                return;
                            }

                            row.querySelectorAll('input[name="permissions[]"]').forEach(function (box) {
                                box.checked = selectAll.checked;
                            });

                            syncSelectAll(form);
                            schedule();
                        });
                    });

                    syncSelectAll(form);
                });
            })();
        </script>
    @endpush
@endsection
