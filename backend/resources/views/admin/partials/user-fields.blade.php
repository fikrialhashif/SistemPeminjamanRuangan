@php
    $isEdit = $isEdit ?? false;
    $prefix = $prefix ?? 'user';
    $currentRole = old('role_id', $user?->roles->first()?->id);
@endphp

<div class="form-grid">
    <div class="field field--wide">
        <label class="field-label" for="{{ $prefix }}_name">Nama</label>
        <input class="input" type="text" id="{{ $prefix }}_name" name="name" value="{{ old('name', $user?->name) }}" required>
    </div>
    <div class="field field--wide">
        <label class="field-label" for="{{ $prefix }}_email">Email</label>
        <input class="input" type="email" id="{{ $prefix }}_email" name="email" value="{{ old('email', $user?->email) }}" required>
    </div>
    <div class="field">
        <label class="field-label" for="{{ $prefix }}_role">Peran</label>
        <select class="input" id="{{ $prefix }}_role" name="role_id" required>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected($currentRole == $role->id)>{{ $roleLabels[$role->name->value] ?? $role->name->value }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label class="field-label" for="{{ $prefix }}_department">Departemen</label>
        <select class="input" id="{{ $prefix }}_department" name="department_id">
            <option value="">Tanpa departemen</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $user?->department_id) == $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label class="field-label" for="{{ $prefix }}_password">Kata sandi {{ $isEdit ? 'baru' : '' }}</label>
        <input class="input" type="password" id="{{ $prefix }}_password" name="password" @required(! $isEdit) autocomplete="new-password">
        <span class="field-hint">{{ $isEdit ? 'Kosongkan jika tidak ingin mengganti.' : 'Minimal 8 karakter.' }}</span>
    </div>
    <div class="field">
        <label class="field-label" for="{{ $prefix }}_password_confirmation">Ulangi kata sandi</label>
        <input class="input" type="password" id="{{ $prefix }}_password_confirmation" name="password_confirmation" @required(! $isEdit) autocomplete="new-password">
    </div>
</div>
