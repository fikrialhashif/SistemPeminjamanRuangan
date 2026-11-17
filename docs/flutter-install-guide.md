# Panduan build dan instalasi Flutter (staging)

Target: Android. Backend berjalan di staging (HTTPS) atau lokal (Docker Compose).

## 1. Prasyarat

- Flutter 3.47+ dan Dart 3.13+ (`flutter doctor` hijau untuk Android toolchain).
- Android SDK, platform tools (`adb`), dan perangkat/emulator API 26+.
- Akses ke backend staging. Untuk lokal, `docker compose up` dari root repo.

## 2. Konfigurasi API tanpa berkas rahasia

Aplikasi **tidak** membundel `.env`. Base URL diberikan saat build:

```text
flutter build apk --release --dart-define=API_BASE_URL=https://staging.example.com/api/v1
```

- Tanpa `--dart-define`, aplikasi memakai `http://10.0.2.2:8000/api/v1` (emulator Android ke backend lokal).
- Build rilis memblokir HTTP cleartext (default Android untuk targetSdk modern). Gunakan HTTPS untuk staging.
- Jangan commit `.env`, `key.properties`, atau keystore.

## 3. Penandatanganan rilis (milik owner)

Template masih memakai debug signing. Untuk build yang dibagikan:

1. Buat keystore di luar repo:

   ```text
   keytool -genkey -v -keystore %USERPROFILE%\smartroom-release.jks -keyalg RSA -keysize 2048 -validity 10000 -alias smartroom
   ```

2. Buat `mobile/android/key.properties` (sudah diabaikan git):

   ```properties
   storePassword=...
   keyPassword=...
   keyAlias=smartroom
   storeFile=C:/Users/<user>/smartroom-release.jks
   ```

3. Hubungkan konfigurasi di `mobile/android/app/build.gradle.kts` (`signingConfigs.release`) lalu ganti `signingConfig` pada buildType `release`. Langkah ini sengaja tidak dilakukan agent karena kredensial milik owner.

## 4. Build dan pasang

```text
cd mobile
flutter pub get
flutter analyze
flutter test
flutter build apk --release --dart-define=API_BASE_URL=https://staging.example.com/api/v1
adb install -r build\app\outputs\flutter-apk\app-release.apk
```

Untuk Play Store internal testing:

```text
flutter build appbundle --release --dart-define=API_BASE_URL=https://staging.example.com/api/v1
```

## 5. Uji asap di perangkat

1. Login dengan akun uji dari seeder backend.
2. Buka katalog resource, cari dan filter.
3. Buat booking; pastikan konflik tampil sebagai pesan, bukan crash.
4. Buka "Booking saya", lakukan check-in (QR ruangan + izin lokasi). Wajah hanya aktif jika `face_verification_enabled` di backend menyala.
5. Buka menu: notifikasi, status penalti, daftar wajah, peminjaman aset (akun manager).
6. Putuskan jaringan saat membuka daftar; pastikan muncul pesan dan tombol "Coba lagi".
7. Uji izin: tolak lokasi/kamera, lalu izinkan lewat pengaturan aplikasi.

## 6. Batasan yang diketahui

- APK saat ini ditandatangani dengan debug key; hanya untuk demo internal.
- Bukti wajah/lokasi pada layar handover manager masih placeholder (lihat `docs/backend-requests.md`).
- Certificate pinning dan `FLAG_SECURE` belum aktif; menunggu domain staging final (lihat `docs/flutter-security-review.md`).
- Notifikasi push FCM belum dikonfigurasi; layar notifikasi mengambil data dari API.
- Uji lapangan GPS/wajah harus dilakukan manusia sesuai `docs/gps-field-test.md` dan `docs/face-field-test.md`.
