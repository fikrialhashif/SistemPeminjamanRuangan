<dialog id="accountDialog" class="modal" aria-labelledby="accountDialogTitle">
    <div class="modal-head">
        <div>
            <h2 class="modal-title" id="accountDialogTitle">Pengaturan akun</h2>
            <p class="modal-desc">Ubah nama tampilan dan kata sandi Anda.</p>
        </div>
        <button type="button" class="modal-close" data-close-dialog aria-label="Tutup dialog">&times;</button>
    </div>
    <div class="modal-body">
        <div class="stack">
            @if(old('account_popup') && $errors->any())
                <div class="alert" role="alert">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.profile.update') }}" data-confirm="Simpan nama tampilan?">
                @csrf
                @method('PUT')
                <input type="hidden" name="account_popup" value="1">

                <h3 class="panel-title">Nama tampilan</h3>
                <p class="field-hint">Nama ini muncul di sidebar dan laporan.</p>
                <div class="field">
                    <label class="field-label" for="account_name">Nama</label>
                    <input class="input" type="text" id="account_name" name="name" value="{{ old('name', auth()->user()->name) }}" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Simpan nama</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.password.update') }}" data-confirm="Ganti kata sandi?">
                @csrf
                @method('PUT')
                <input type="hidden" name="account_popup" value="1">

                <h3 class="panel-title">Kata sandi</h3>
                <p class="field-hint">Masukkan kata sandi saat ini untuk mengganti kata sandi baru.</p>
                <div class="form-grid">
                    <div class="field field--wide">
                        <label class="field-label" for="account_current_password">Kata sandi saat ini</label>
                        <input class="input" type="password" id="account_current_password" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="account_password">Kata sandi baru</label>
                        <input class="input" type="password" id="account_password" name="password" autocomplete="new-password" required>
                        <span class="field-hint">Minimal 8 karakter.</span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="account_password_confirmation">Ulangi kata sandi baru</label>
                        <input class="input" type="password" id="account_password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-dialog>Batal</button>
                    <button type="submit" class="btn btn--primary">Ganti kata sandi</button>
                </div>
            </form>
        </div>
    </div>
</dialog>
