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

- Tanpa `--dart-define`, build debug mencoba `http://10.0.2.2:8000/api/v1` (emulator) lalu otomatis `http://127.0.0.1:8000/api/v1` jika koneksi gagal.
- Untuk perangkat fisik lewat USB, jalankan `adb reverse tcp:8000 tcp:8000` sebelum `flutter run` agar fallback `127.0.0.1` tersedia. Port forwarding bisa hilang saat kabel/adb terputus; cek dengan `adb reverse --list`. Alternatif lebih stabil selama HP dan komputer satu WiFi: `--dart-define=API_BASE_URL=http://192.168.100.171:8000/api/v1` (sesuaikan IP LAN komputer dan firewall yang mengizinkan port 8000).
- Build rilis memblokir HTTP cleartext (default Android untuk targetSdk modern). Gunakan HTTPS untuk staging.
- Jangan commit `.env`, `key.properties`, atau keystore.

### Menjalankan face-service lokal

Pendaftaran dan verifikasi wajah membutuhkan face-service di port 8001. Jika Docker tidak tersedia, jalankan dengan Python 3.13:

```text
cd face-service
py -3.13 -m venv .venv
.venv\Scripts\python.exe -m pip install -r requirements.txt
.venv\Scripts\python.exe -m pip install <wheel InsightFace cp313>
.venv\Scripts\python.exe -m app.warmup
.venv\Scripts\python.exe -m uvicorn app.main:app --host 0.0.0.0 --port 8001
```

`app.warmup` mengunduh model `buffalo_s` ke `~/.insightface/models/buffalo_s`. Pastikan `FACE_SERVICE_SECRET` di `face-service/.env` sama dengan konfigurasi backend.

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
3. Daftarkan wajah lewat menu/resources: ikuti instruksi tiga sampel (toleh kiri, toleh kanan, lalu hadap lurus). Pastikan wajah tampil normal tidak gepeng, gerakan toleh terlihat jelas, dan status berubah menjadi terdaftar. Jika data wajah lama ditolak saat check-in, daftar ulang agar baseline arah kepala tersimpan.
4. Coba buat booking ruangan sebelum pendaftaran: harus muncul pesan `face_enrollment_required` dan tombol untuk mendaftar. Setelah terdaftar, buat booking; pastikan konflik tampil sebagai pesan, bukan crash. Booking aset tetap bisa dibuat tanpa wajah.
5. Buka "Booking saya", lakukan konfirmasi 3 tahap: izin lokasi, pindai QR ruangan, lalu verifikasi wajah. Wajah yang tidak cocok harus ditolak server. Wajah hanya aktif jika `face_verification_enabled` di backend menyala.
6. Buka menu: notifikasi, status penalti, daftar wajah, peminjaman aset (akun manager).
7. Putuskan jaringan saat membuka daftar; pastikan muncul pesan dan tombol "Coba lagi".
8. Uji izin: tolak lokasi/kamera, lalu izinkan lewat pengaturan aplikasi.

## 6. Batasan yang diketahui

- APK saat ini ditandatangani dengan debug key; hanya untuk demo internal.
- Bukti wajah/lokasi pada layar handover manager masih placeholder (lihat `docs/backend-requests.md`).
- Certificate pinning dan `FLAG_SECURE` belum aktif; menunggu domain staging final (lihat `docs/flutter-security-review.md`).
- Notifikasi push FCM belum dikonfigurasi; layar notifikasi mengambil data dari API.
- Uji lapangan GPS/wajah harus dilakukan manusia sesuai `docs/gps-field-test.md` dan `docs/face-field-test.md`.
