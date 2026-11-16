# Roadmap: Smart Room & Asset Booking System

Pasangan dari `PRD.md`. ID requirement (FR-xx, NFR-xx) merujuk ke PRD.

Roadmap ini dipisah menjadi dua jalur kerja utama:

- **Bagian A: Backend** (Laravel API + service face recognition Python). ID task: `BE-x.y` dan `FS-x.y` (face service).
- **Bagian B: Flutter** (aplikasi mobile). ID task: `FL-x.y`.
- **Bagian C: Titik sinkronisasi** antara backend dan Flutter (task Flutter mana yang menunggu task backend mana).

Angka fase (0-9) sama di kedua bagian, jadi mudah dicocokkan.

## Cara membaca

- Estimasi memakai asumsi AI agent mengerjakan kode, manusia mereview. Waktu terbanyak ada di review, pengujian di perangkat nyata, dan pengambilan keputusan. Angka adalah perkiraan awal, bukan janji; kalibrasi ulang setelah Fase 1 (pakai kecepatan nyata).
- **[REVIEW]** = agent berhenti dan menunggu persetujuan manusia.
- Backend dan Flutter boleh berjalan **paralel**. Kuncinya: backend menerbitkan `docs/openapi.yaml` lebih dulu, Flutter bekerja dengan mock server dari OpenAPI itu (lihat FL-0.2) sampai endpoint asli siap.
- Urutan mengikuti saran brief: **GPS lock dibangun sebelum face recognition**.
- Satu task = satu branch/PR. Jangan mencampur task backend dan Flutter dalam satu PR kecuali perubahan kontrak API yang memang menyentuh keduanya.

## Ringkasan fase

| Fase | Isi | Estimasi |
|---|---|---|
| 0 | Fondasi: repo, environment, CI, ERD, OpenAPI awal | 3-4 hari |
| 1 | Auth, RBAC, katalog resource | 1 minggu |
| 2 | Booking engine inti + anti double booking | 1 minggu |
| 3 | Approval workflow | 3-4 hari |
| 4 | GPS lock + QR dinamis + check-in (tanpa wajah) + no-show | 1 minggu |
| 5 | Face recognition: service, enrollment, verifikasi, integrasi check-in | 1,5 minggu |
| 6 | Siklus peminjaman aset | 1 minggu |
| 7 | Penalti, notifikasi, job terjadwal | 4-5 hari |
| 8 | Recurring, waitlist, bundled booking | 1 minggu |
| 9 | Laporan, audit, privasi, hardening, deploy | 1 minggu |

Total perkiraan: **sekitar 8-9 minggu** dengan 1 manusia sebagai reviewer penuh waktu (lebih cepat dari 12-14 minggu di brief). Risiko utama ada di pengujian perangkat nyata (GPS, kamera) dan evaluasi wajah, yang tidak bisa dipercepat agent. Jika perlu lebih pendek, buang Fase 8 dulu (sesuai saran brief).

---

# BAGIAN A: BACKEND (Laravel + Face Service)

Aturan khusus jalur backend:
- Otorisasi di server (Policy/Gate). Semua keputusan keamanan (GPS, QR, wajah, check-in) dibuat di backend.
- Tes wajib: Pest/PHPUnit untuk aturan bisnis, pytest untuk face service. Linter (Pint) dan analisis statis (Larastan) bersih.
- Perubahan kontrak API wajib memperbarui `docs/openapi.yaml` **dalam PR yang sama**, karena Flutter bergantung padanya.
- Perubahan skema lewat migration baru, jangan edit migration lama yang sudah dipakai.

## Fase 0: Fondasi backend

| ID | Task | Acceptance criteria |
|---|---|---|
| BE-0.1 | Struktur repo monorepo (`backend/`, `face-service/`, `mobile/`, `docs/`) + README setup | Clone lalu satu perintah menjalankan semua service |
| BE-0.2 | Docker Compose: MySQL 8, Redis, Laravel, face service (stub), mailpit | `docker compose up` sehat, health check hijau |
| BE-0.3 | Scaffold Laravel: Sanctum, Pest/PHPUnit, Pint, Larastan, struktur folder (Actions/Services) | `composer test` dan linter jalan |
| BE-0.4 | CI (GitHub Actions atau setara) untuk backend dan face service | PR gagal jika tes merah |
| BE-0.5 | `docs/erd.md` awal dari PRD bagian 11 + kerangka `docs/openapi.yaml` | **[REVIEW]** ERD disetujui sebelum migration besar |
| FS-0.1 | Scaffold FastAPI + pytest + endpoint `/health` | Tes health hijau |
| BE-0.6 | `docs/risk-and-privacy.md` awal (threat model PRD bagian 9-10) | Dibaca mentor |

Keluar fase: ERD disetujui dan keputusan PRD bagian 14 (terutama sumber QR dan panel admin) sudah dijawab.

## Fase 1: Auth, RBAC, katalog

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-1.1 | Migration + model: users, roles, departments, locations | FR-AUTH | Seeder membuat 4 peran dan user contoh |
| BE-1.2 | Login/logout/me (Sanctum) + rate limit | FR-AUTH-1,4 | Tes: login benar/salah, throttle bekerja |
| BE-1.3 | Policy/Gate per peran + middleware | FR-AUTH-2 | Tes feature: tiap endpoint menolak peran yang salah dengan 403. **[REVIEW]** matriks hak akses |
| BE-1.4 | Migration + model resources, rooms, assets, resource_rules, resource_access | FR-CAT | Relasi dan constraint benar |
| BE-1.5 | CRUD resource untuk manager/admin + upload foto (disk privat, signed URL) | FR-CAT-1..3 | Validasi input, foto tidak publik |
| BE-1.6 | Pencarian + filter + pembatasan akses resource | FR-CAT-4,5 | Resource terlarang tidak muncul di hasil |
| BE-1.7 | Audit log dasar (trait/observer) untuk perubahan model | FR-RP-2 | Perubahan resource tercatat siapa/apa/kapan. **[REVIEW]** |

## Fase 2: Booking engine inti

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-2.1 | Migration bookings + state machine status (PRD 6.1) | FR-BK | Transisi ilegal ditolak (tes tiap pasangan status) |
| BE-2.2 | Aturan per resource: buffer, durasi, advance window, jam buka | FR-BK-3 | Tes batas (tepat di batas, satu menit melewati) |
| BE-2.3 | `BookingService::create` dengan transaksi + `FOR UPDATE` + cek overlap + buffer (PRD bagian 7) | FR-BK-2 | Tes konkurensi: N proses paralel ke slot sama = tepat 1 sukses. **[REVIEW]** jalankan sendiri di lingkungan mirip produksi |
| BE-2.4 | Endpoint availability per resource (kalender) | FR-CAT-5 | Slot terisi/kosong benar termasuk buffer |
| BE-2.5 | Pembatalan booking + aturan batas waktu | FR-BK-6 | Tes: batal sebelum/sesudah batas |

## Fase 3: Approval workflow

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-3.1 | Migration approvals + jalur per resource (0/1/2 level) | FR-AP-1 | Booking tanpa approval langsung `approved` |
| BE-3.2 | Endpoint pending/approve/reject + catatan | FR-AP-2 | Hanya approver yang ditunjuk; tiap keputusan tercatat |
| BE-3.3 | Pending menahan slot; expire/eskalasi lewat job terjadwal | FR-AP-3,4 | Tes dengan waktu dimajukan (fake clock) |

## Fase 4: GPS lock, QR, check-in, no-show

Wajah belum ada di fase ini. `CheckInDecisionService` dibuat dengan **slot wajah yang bisa dimatikan lewat konfigurasi** supaya Fase 5 tinggal menyambung.

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-4.1 | Geofence di model location/room (titik+radius, polygon) + CRUD admin + pengecualian | FR-GPS-1,5 | Tes titik di dalam/di luar/tepat di batas |
| BE-4.2 | `GpsValidator` server: geofence, akurasi, kesegaran, flag mock, deteksi lompatan | FR-GPS-2..4 | Tes tiap alasan penolakan dengan kode alasan terstruktur |
| BE-4.3 | Generator dan validator QR dinamis (token bertanda tangan, TTL pendek, sekali pakai, terikat ruangan/booking) | FR-CI-2 | Tes: kedaluwarsa, dipakai dua kali, ruangan salah |
| BE-4.4 | Tampilan QR untuk ruangan (halaman web sederhana, sesuai keputusan PRD 14 #1) | FR-CI-2 | QR berganti sesuai TTL |
| BE-4.5 | `CheckInDecisionService` + endpoint check-in + tabel `check_in_attempts` | FR-CI-1,4 | Hasil terstruktur `accepted/rejected/needs_manual_review`, semua percobaan tercatat |
| BE-4.6 | Job pelepasan no-show otomatis (idempoten) | FR-CI-3 | Dijalankan dua kali tidak menggandakan efek |
| BE-4.7 | Kalibrasi ambang akurasi GPS dari hasil uji lapangan (butuh FL-4.3) | FR-GPS-2 | Ambang tersimpan di `settings`, hasil di `docs/gps-field-test.md`. **[REVIEW]** uji di HP sungguhan (termasuk aplikasi mock-location) |

## Fase 5: Face recognition (service + integrasi backend)

Urutan: pilih model dan ukur dulu, baru integrasi.

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FS-5.1 | Riset singkat model embedding + deteksi + cek kualitas, bandingkan 2-3 kandidat, pilih satu | FR-FACE | `docs/face-model-choice.md`. **[REVIEW]** sebelum implementasi |
| FS-5.2 | `POST /enroll`: cek kualitas (cahaya, sudut, ukuran wajah), beberapa sampel, simpan embedding terenkripsi | FR-FACE-1 | Gambar kualitas buruk ditolak dengan alasan |
| FS-5.3 | `POST /verify`: cocokkan embedding, kembalikan `{match, score, liveness_passed, reason}` | FR-FACE-2 | Tes pasangan sama/beda orang |
| FS-5.4 | Liveness aktif (tantangan acak kedip/tengok, dicek berurutan di server) | FR-FACE-3 | Foto statis dan video putar ulang ditolak pada dataset uji |
| FS-5.5 | `DELETE /users/{id}` + auth antar-service (shared secret/mTLS) | FR-FACE-6 | Hanya Laravel yang bisa memanggil; data benar-benar terhapus |
| FS-5.6 | Skrip evaluasi FAR/FRR + dataset uji (dengan consent) + threshold awal | FR-FACE-5 | `docs/face-evaluation.md` berisi angka dan kurva. **[REVIEW]** angka dan threshold |
| BE-5.7 | Consent + endpoint enroll/status/hapus di Laravel, tabel `face_enrollments` | FR-FACE-1,6 | Enroll tanpa consent ditolak |
| BE-5.8 | Integrasi ke `CheckInDecisionService`: skor + liveness jadi syarat, threshold dari `settings`, tantangan liveness dikeluarkan server | FR-FACE-2,5 | Matriks tes semua kombinasi QR/GPS/wajah lolos/gagal |
| BE-5.9 | Fallback: batas retry, override manager dengan alasan wajib + audit | FR-FACE-4 | Override tanpa alasan ditolak; tercatat di audit |
| BE-5.10 | Evaluasi lapangan gabungan (butuh FL-5.3), hasil ditambahkan ke `docs/face-evaluation.md` | FR-FACE | **[REVIEW]** setelah hasil lapangan |

Catatan: liveness aktif tidak menahan deepfake atau topeng; batas ini ditulis di dokumen risiko.

## Fase 6: Siklus peminjaman aset

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-6.1 | Migration asset_loans, loan_events, condition_reports + state machine (reserved → handed_over → in_use → returned → closed) | FR-LN-1 | Transisi ilegal ditolak |
| BE-6.2 | Handover: verifikasi wajah+lokasi peminjam, scan kode aset | FR-LN-2 | Gagal verifikasi tidak mengubah status |
| BE-6.3 | Return: cek kondisi + foto (disk privat), laporan kerusakan | FR-LN-3,4 | Foto tersimpan, kondisi aset diperbarui |
| BE-6.4 | Job tandai terlambat + pengingat | FR-LN-4 | Idempoten; memicu notifikasi dan strike (Fase 7) |

## Fase 7: Penalti, notifikasi, job terjadwal

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-7.1 | `strikes`, `suspensions`, aturan konfigurasi | FR-PN | Aturan default PRD 6.3 bisa diubah admin |
| BE-7.2 | Strike otomatis dari no-show, telat, verifikasi mencurigakan | FR-PN-1 | Tiap sumber punya tes |
| BE-7.3 | Suspend setelah ambang + blokir booking baru + pembatalan strike oleh admin (diaudit) | FR-PN-2..4 | Tes ambang tepat |
| BE-7.4 | Integrasi FCM + email, semua via queue dengan retry dan log; endpoint pendaftaran token perangkat | FR-NT | Tiap jenis notifikasi punya template dan tes |

## Fase 8: Fitur lanjutan booking (bisa dibuang jika waktu mepet)

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-8.1 | Booking berulang: aturan seri + kebijakan konflik (lewati/tolak seluruhnya) | FR-BK-4 | Tes mingguan 10 kali, konflik di kemunculan ke-3 |
| BE-8.2 | Edit/batalkan satu kemunculan vs seluruh seri | FR-BK-4 | Tes kedua mode |
| BE-8.3 | Waitlist: antrean, penawaran saat slot lepas, batas klaim, kedaluwarsa | FR-BK-5 | Tes dua klaim bersamaan, hanya satu menang |
| BE-8.4 | Bundled booking ruangan + aset (semua atau tidak sama sekali) | FR-BK-7 | Transaksi atomik |

## Fase 9: Laporan, audit, hardening, rilis

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-9.1 | Endpoint laporan: utilisasi ruangan, no-show rate, aset terpopuler, tingkat sukses verifikasi | FR-RP-1 | Angka cocok dengan data uji yang dihitung manual |
| BE-9.2 | Audit trail lengkap + pencarian admin; pastikan append-only | FR-RP-2 | Tidak ada endpoint edit/hapus audit |
| BE-9.3 | Antarmuka admin (laporan, audit, user, geofence, aturan, threshold). Bentuknya menunggu keputusan PRD 14 #9 | FR-RP | Sesuai keputusan; semua aksi admin diaudit |
| BE-9.4 | Tinjauan keamanan: matriks otorisasi, rate limit, header, rahasia, log tidak membocorkan biometrik | NFR-1 | Daftar temuan dan perbaikan |
| BE-9.5 | Privasi: alur hapus data wajah menyeluruh, retensi, dokumen UU PDP untuk mentor | PRD 10 | **[REVIEW]** mentor |
| BE-9.6 | Optimasi indeks dan kinerja pada data uji besar | NFR-4 | Target PRD tercapai |
| BE-9.7 | Dokumentasi akhir: README, OpenAPI final, ERD final, panduan deploy, panduan admin | NFR-7 | Orang baru bisa setup dari README saja |
| BE-9.8 | Deploy staging (Docker Compose di VPS) | - | Staging bisa dipakai Flutter dan mentor |

---

# BAGIAN B: FLUTTER

Aturan khusus jalur Flutter:
- State management **Riverpod**, struktur per fitur (feature-first), repository terpisah dari UI.
- Aplikasi **tidak membuat keputusan keamanan**. Ia hanya mengirim bukti (koordinat, akurasi, timestamp, flag mock, frame wajah/liveness, token QR) dan menampilkan hasil server beserta kode alasan.
- Setiap layar punya state loading, error, dan kosong. Kode alasan terstruktur dari server dipetakan ke pesan yang bisa ditindaklanjuti.
- Izin kamera dan lokasi ditangani lengkap: belum diminta, ditolak, ditolak permanen, dicabut (FR-APP-1).
- Kontrak API mengikuti `docs/openapi.yaml`. Jika kontrak terasa kurang, minta perubahan di jalur backend, jangan menebak sendiri.
- Tes wajib: `flutter analyze` bersih, widget test untuk layar utama, unit test untuk repository dan pemetaan error.
- Platform awal: Android (PRD 14 #2).

## Fase 0: Fondasi Flutter

| ID | Task | Acceptance criteria |
|---|---|---|
| FL-0.1 | Scaffold Flutter: struktur fitur, Riverpod, routing, tema, konfigurasi environment (dev/staging) | `flutter analyze` dan `flutter test` bersih |
| FL-0.2 | HTTP client (Dio) + interceptor token + pemetaan error terstruktur; mock server dari `docs/openapi.yaml` (mis. Prism) | Flutter bisa berjalan penuh terhadap mock tanpa backend |
| FL-0.3 | Modul izin reusable (kamera + lokasi) dengan semua status izin dan layar penjelasan | Widget test untuk tiap status izin |
| FL-0.4 | CI Flutter (analyze + test) | PR gagal jika merah |

## Fase 1: Auth dan katalog

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-1.1 | Layar login + token di secure storage + logout + penanganan 401 | FR-AUTH-1 | Login sukses; token tidak ada di log |
| FL-1.2 | Layar daftar/detail resource + pencarian + filter | FR-CAT-5 | Mengikuti OpenAPI, ada state loading/error/kosong |

## Fase 2: Booking

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-2.1 | Layar kalender ketersediaan + form booking | FR-BK-1 | Error konflik ditampilkan jelas (bukan crash) |
| FL-2.2 | Layar "Booking saya" + batalkan | FR-BK-6 | Status sinkron dengan server |

## Fase 3: Approver

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-3.1 | Layar approver: daftar pending + detail + approve/reject dengan catatan | FR-AP-2 | Alur lengkap sampai keputusan terkirim |

## Fase 4: Check-in (GPS + QR)

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-4.1 | Pengambilan lokasi: koordinat, akurasi, timestamp, flag mock; tahan terhadap izin ditolak/dicabut | FR-APP-1,3 | Semua kasus izin ditangani |
| FL-4.2 | Alur check-in: scan QR + kirim bukti lokasi, tampilkan hasil dan alasan dari server | FR-CI-1 | Setiap kode alasan penolakan punya pesan ramah |
| FL-4.3 | Uji lapangan GPS di lokasi dan perangkat nyata, catat akurasi (masukan untuk BE-4.7) | FR-GPS-2 | Catatan uji di `docs/gps-field-test.md` |

## Fase 5: Face recognition

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-5.1 | Layar consent + enrollment dengan panduan (cahaya, sudut), status enrollment, hapus data wajah | FR-FACE-1,6 | Panduan jelas; izin kamera ditangani penuh; tanpa consent tidak bisa lanjut |
| FL-5.2 | Layar verifikasi wajah + tantangan liveness (kedip/tengok sesuai perintah server) di alur check-in | FR-FACE-2,3 | Terhubung end-to-end ke check-in |
| FL-5.3 | Uji lapangan: berbagai cahaya dan perangkat, serangan foto dari layar HP lain (masukan untuk BE-5.10) | FR-FACE | Catatan uji dibagikan ke jalur backend |
| FL-5.4 | Tampilan hasil `needs_manual_review` dan alur fallback (batas retry tercapai) | FR-FACE-4 | Pesan jelas dan tidak membuntu |

Catatan: tidak ada foto wajah yang disimpan di perangkat setelah dikirim, dan tidak ada foto wajah di log.

## Fase 6: Manager (aset)

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-6.1 | Layar handover: scan kode aset, verifikasi wajah peminjam, kirim lokasi | FR-LN-2 | Gagal verifikasi tidak mengubah tampilan status |
| FL-6.2 | Layar return: cek kondisi, upload foto, laporan kerusakan | FR-LN-3,4 | Foto terunggah dengan indikator progres dan retry |
| FL-6.3 | Layar override verifikasi manual oleh manager (alasan wajib) | FR-FACE-4 | Tidak bisa dikirim tanpa alasan |

## Fase 7: Notifikasi dan penalti

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-7.1 | Integrasi FCM: izin notifikasi, pendaftaran token perangkat, tap notifikasi membuka layar yang tepat | FR-NT | Menangani izin notifikasi ditolak |
| FL-7.2 | Layar notifikasi + layar status penalti (strike, sisa waktu suspend) | FR-PN-3 | Pengguna suspended melihat alasan dan sisa waktu |

## Fase 8: Fitur lanjutan booking (ikut dibuang jika Fase 8 backend dibuang)

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-8.1 | UI booking berulang (aturan seri, pilihan kebijakan konflik) dan edit/batal seri | FR-BK-4 | Sinkron dengan OpenAPI |
| FL-8.2 | UI waitlist: gabung antrean, terima/tolak penawaran sebelum batas klaim | FR-BK-5 | Hitung mundur klaim sesuai waktu server |
| FL-8.3 | UI bundled booking ruangan + aset | FR-BK-7 | Hasil gagal menjelaskan resource mana yang bentrok |

## Fase 9: Pemolesan dan rilis

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-9.1 | Integration test alur kritis: login → booking → check-in 3 faktor (terhadap staging/mock) | NFR-5 | Hijau di CI |
| FL-9.2 | Tinjauan keamanan sisi klien: secure storage, tanpa rahasia di build, tanpa log data sensitif, pinning/HTTPS | NFR-1 | Daftar temuan dan perbaikan |
| FL-9.3 | Pemolesan: pesan error, aksesibilitas dasar, performa daftar dan kalender | - | Daftar periksa selesai |
| FL-9.4 | Build rilis Android + panduan instalasi untuk staging | - | APK/AAB terpasang di perangkat uji. **[REVIEW]** demo ke mentor/client |

---

# BAGIAN C: TITIK SINKRONISASI BACKEND ↔ FLUTTER

Task Flutter di kolom kiri baru bisa diuji terhadap server asli jika task backend di kolom kanan sudah selesai. Sebelum itu, Flutter tetap bisa dikerjakan terhadap mock OpenAPI (FL-0.2), asalkan kontrak endpoint-nya sudah ada di `docs/openapi.yaml`.

| Task Flutter | Menunggu task backend | Kontrak yang harus sudah ada |
|---|---|---|
| FL-1.1 | BE-1.2 | `POST /auth/login`, `POST /auth/logout`, `GET /me`, format error |
| FL-1.2 | BE-1.6 | `GET /resources`, `GET /resources/{id}` |
| FL-2.1 | BE-2.3, BE-2.4 | `GET /resources/{id}/availability`, `POST /bookings`, kode error konflik |
| FL-2.2 | BE-2.5 | `GET /bookings`, `DELETE /bookings/{id}` |
| FL-3.1 | BE-3.2 | `GET /approvals/pending`, `POST /approvals/{id}/approve\|reject` |
| FL-4.2 | BE-4.3, BE-4.5 | `POST /bookings/{id}/check-in`, daftar kode alasan penolakan |
| FL-4.3 | BE-4.2 (dan hasilnya jadi masukan BE-4.7) | Server menolak lokasi buruk dengan alasan jelas |
| FL-5.1 | BE-5.7, FS-5.2 | `POST /face/enroll`, `GET /face/status`, `DELETE /face` |
| FL-5.2 | BE-5.8, FS-5.3, FS-5.4 | Format tantangan liveness dan bukti frame di endpoint check-in |
| FL-5.4 | BE-5.9 | Status `needs_manual_review` dan jumlah sisa percobaan |
| FL-6.1, FL-6.2 | BE-6.2, BE-6.3 | `POST /loans/{id}/handover`, `/return`, `/damage` |
| FL-6.3 | BE-5.9 | Endpoint override manager |
| FL-7.1 | BE-7.4 | Pendaftaran token perangkat, format payload notifikasi |
| FL-7.2 | BE-7.3 | `GET /me/strikes` |
| FL-8.1..8.3 | BE-8.1..8.4 | Endpoint seri, waitlist, bundled |
| FL-9.1 | BE-9.8 | Staging berjalan |

Aturan sinkronisasi:
1. Backend **menerbitkan kontrak (OpenAPI) sebelum** mengimplementasikan endpoint, supaya Flutter bisa mulai lebih awal.
2. Perubahan kontrak yang merusak (breaking) diumumkan di deskripsi PR dan memicu task penyesuaian di jalur Flutter.
3. Hasil uji lapangan Flutter (FL-4.3, FL-5.3) adalah masukan resmi untuk kalibrasi backend (BE-4.7, BE-5.10). Backend tidak mengubah ambang tanpa data itu.
4. Daftar kode alasan penolakan (check-in, verifikasi) disepakati bersama dan disimpan di `docs/openapi.yaml`, agar pesan di Flutter tidak mengarang sendiri.

---

## Jalur kritis dan risiko

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Double booking lolos saat konkurensi | Fitur inti rusak | Tes paralel nyata di BE-2.3, review manual |
| Akurasi GPS di dalam gedung buruk | Check-in sah ditolak | FL-4.3 + BE-4.7, ambang bisa dikonfigurasi, jalur `needs_manual_review` |
| Liveness mudah ditembus / FRR tinggi | Keamanan lemah atau user frustrasi | FS-5.6, FL-5.3, BE-5.10, threshold bisa diubah, fallback manager |
| Regulasi data biometrik | Risiko hukum | Consent, minimisasi, hapus data, konsultasi mentor di BE-9.5 |
| Flutter menunggu backend | Jalur Flutter menganggur | Mock OpenAPI (FL-0.2), kontrak diterbitkan lebih dulu |
| Kontrak API berubah diam-diam | Flutter rusak | Aturan sinkronisasi nomor 1 dan 2 |
| Estimasi AI agent terlalu optimis | Jadwal mundur | Kalibrasi setelah Fase 1; Fase 8 sebagai penyangga |
| Review manusia jadi bottleneck | Agent menganggur | Titik [REVIEW] dijadwalkan di awal; jalur paralel tetap berjalan |

## Definition of done

Semua jalur:
- Acceptance criteria terpenuhi dan tes baru ada serta hijau di CI.
- Tidak ada rahasia atau data pribadi di commit.
- PR menyebut ID task dan ID requirement.

Tambahan backend: Pint dan Larastan bersih; `docs/openapi.yaml` dan `docs/erd.md` diperbarui bila kontrak atau skema berubah.

Tambahan Flutter: `flutter analyze` bersih; layar punya state loading/error/kosong; tidak ada keputusan keamanan di sisi klien.
