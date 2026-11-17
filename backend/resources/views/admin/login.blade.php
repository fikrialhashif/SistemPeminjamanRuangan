<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2b5f4a">
    <title>Masuk panel admin | Ruang</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="auth">
        <section class="auth-brand">
            <div class="auth-brand-top">
                <a class="brand" href="{{ route('admin.login') }}">
                    <span class="brand-mark" aria-hidden="true">R</span>
                    <span class="brand-text">
                        <span class="brand-name">Ruang</span>
                        <span class="brand-sub">Peminjaman ruangan &amp; aset</span>
                    </span>
                </a>
                <div>
                    <h1 class="auth-title">Kelola ruangan, aset, dan izin masuk.</h1>
                    <p class="auth-lead">Satu papan untuk antrian persetujuan, status ruangan, dan jejak keputusan admin kampus.</p>
                </div>
            </div>

            <div class="doorplate">
                <span class="doorplate-kicker">Akses terbatas</span>
                <div class="doorplate-name">Panel admin</div>
                <p class="doorplate-note">Untuk approver, pengelola aset, dan admin.</p>
                <div class="doorplate-status">
                    <span class="dot dot--amber" aria-hidden="true"></span>
                    <span>Masuk dengan akun kantor</span>
                </div>
            </div>
        </section>

        <section class="auth-form-wrap">
            <div class="auth-form">
                <h2 class="auth-form-title">Masuk ke panel</h2>
                <p class="auth-form-lead">Gunakan akun kantor yang sudah memiliki akses.</p>

                @if($errors->any())
                    <div class="alert" role="alert">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login') }}">
                    @csrf
                    <div class="auth-fields">
                        <div class="field">
                            <label class="field-label" for="email">Email</label>
                            <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                        </div>
                        <div class="field">
                            <label class="field-label" for="password">Kata sandi</label>
                            <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
                        </div>
                        <label class="auth-options">
                            <input type="checkbox" name="remember" value="1">
                            <span>Ingat saya di perangkat ini</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn--primary btn--full auth-submit">Masuk</button>
                </form>

                <p class="auth-foot">Tidak bisa masuk? Hubungi admin sistem untuk mengatur ulang akses akun.</p>
            </div>
        </section>
    </div>
</body>
</html>
