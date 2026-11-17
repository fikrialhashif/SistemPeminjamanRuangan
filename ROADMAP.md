# Roadmap: Smart Room & Asset Booking System

Pasangan dari `PRD.md`. ID requirement (FR-xx, NFR-xx) merujuk ke PRD.

Roadmap ini dipisah menjadi tiga bagian:

- **Bagian A: Backend** (Laravel API + service face recognition Python). ID task: `BE-x.y` dan `FS-x.y` (face service). **Status: selesai.**
- **Bagian B: Flutter** (aplikasi mobile). ID task: `FL-x.y`. **Status: sedang dikerjakan.** Tiap fase punya prompt siap tempel dengan pemanggilan skill opencode yang eksplisit.
- **Bagian C: Titik sinkronisasi** antara backend dan Flutter (task Flutter mana yang menunggu task backend mana).

Angka fase (0-9) sama di kedua bagian, jadi mudah dicocokkan.

## Cara membaca

- Estimasi memakai asumsi AI agent mengerjakan kode, manusia mereview. Waktu terbanyak ada di review, pengujian di perangkat nyata, dan pengambilan keputusan. Angka adalah perkiraan awal, bukan janji; kalibrasi ulang setelah Fase 1 (pakai kecepatan nyata).
- **[REVIEW]** = agent berhenti dan menunggu persetujuan manusia. **[CHECKPOINT]** (khusus Bagian B) = titik berhenti tambahan untuk keputusan arsitektur.
- Backend dan Flutter semula dirancang paralel dengan mock server dari OpenAPI. Karena backend sudah selesai, Flutter kini langsung dikerjakan terhadap backend asli (lihat Bagian B).
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

# BAGIAN A: BACKEND (Laravel + Face Service) — SELESAI

Aturan khusus jalur backend:
- Otorisasi di server (Policy/Gate). Semua keputusan keamanan (GPS, QR, wajah, check-in) dibuat di backend.
- Tes wajib: Pest/PHPUnit untuk aturan bisnis, pytest untuk face service. Linter (Pint) dan analisis statis (Larastan) bersih.
- Perubahan kontrak API wajib memperbarui `docs/openapi.yaml` **dalam PR yang sama**, karena Flutter bergantung padanya.
- Perubahan skema lewat migration baru, jangan edit migration lama yang sudah dipakai.

## Fase 0 Backend: Fondasi

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

## Fase 1 Backend: Auth, RBAC, katalog

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-1.1 | Migration + model: users, roles, departments, locations | FR-AUTH | Seeder membuat 4 peran dan user contoh |
| BE-1.2 | Login/logout/me (Sanctum) + rate limit | FR-AUTH-1,4 | Tes: login benar/salah, throttle bekerja |
| BE-1.3 | Policy/Gate per peran + middleware | FR-AUTH-2 | Tes feature: tiap endpoint menolak peran yang salah dengan 403. **[REVIEW]** matriks hak akses |
| BE-1.4 | Migration + model resources, rooms, assets, resource_rules, resource_access | FR-CAT | Relasi dan constraint benar |
| BE-1.5 | CRUD resource untuk manager/admin + upload foto (disk privat, signed URL) | FR-CAT-1..3 | Validasi input, foto tidak publik |
| BE-1.6 | Pencarian + filter + pembatasan akses resource | FR-CAT-4,5 | Resource terlarang tidak muncul di hasil |
| BE-1.7 | Audit log dasar (trait/observer) untuk perubahan model | FR-RP-2 | Perubahan resource tercatat siapa/apa/kapan. **[REVIEW]** |

## Fase 2 Backend: Booking engine inti

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-2.1 | Migration bookings + state machine status (PRD 6.1) | FR-BK | Transisi ilegal ditolak (tes tiap pasangan status) |
| BE-2.2 | Aturan per resource: buffer, durasi, advance window, jam buka | FR-BK-3 | Tes batas (tepat di batas, satu menit melewati) |
| BE-2.3 | `BookingService::create` dengan transaksi + `FOR UPDATE` + cek overlap + buffer (PRD bagian 7) | FR-BK-2 | Tes konkurensi: N proses paralel ke slot sama = tepat 1 sukses. **[REVIEW]** jalankan sendiri di lingkungan mirip produksi |
| BE-2.4 | Endpoint availability per resource (kalender) | FR-CAT-5 | Slot terisi/kosong benar termasuk buffer |
| BE-2.5 | Pembatalan booking + aturan batas waktu | FR-BK-6 | Tes: batal sebelum/sesudah batas |

## Fase 3 Backend: Approval workflow

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-3.1 | Migration approvals + jalur per resource (0/1/2 level) | FR-AP-1 | Booking tanpa approval langsung `approved` |
| BE-3.2 | Endpoint pending/approve/reject + catatan | FR-AP-2 | Hanya approver yang ditunjuk; tiap keputusan tercatat |
| BE-3.3 | Pending menahan slot; expire/eskalasi lewat job terjadwal | FR-AP-3,4 | Tes dengan waktu dimajukan (fake clock) |

## Fase 4 Backend: GPS lock, QR, check-in, no-show

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

## Fase 5 Backend: Face recognition (service + integrasi backend)

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

## Fase 6 Backend: Siklus peminjaman aset

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-6.1 | Migration asset_loans, loan_events, condition_reports + state machine (reserved → handed_over → in_use → returned → closed) | FR-LN-1 | Transisi ilegal ditolak |
| BE-6.2 | Handover: verifikasi wajah+lokasi peminjam, scan kode aset | FR-LN-2 | Gagal verifikasi tidak mengubah status |
| BE-6.3 | Return: cek kondisi + foto (disk privat), laporan kerusakan | FR-LN-3,4 | Foto tersimpan, kondisi aset diperbarui |
| BE-6.4 | Job tandai terlambat + pengingat | FR-LN-4 | Idempoten; memicu notifikasi dan strike (Fase 7) |

## Fase 7 Backend: Penalti, notifikasi, job terjadwal

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-7.1 | `strikes`, `suspensions`, aturan konfigurasi | FR-PN | Aturan default PRD 6.3 bisa diubah admin |
| BE-7.2 | Strike otomatis dari no-show, telat, verifikasi mencurigakan | FR-PN-1 | Tiap sumber punya tes |
| BE-7.3 | Suspend setelah ambang + blokir booking baru + pembatalan strike oleh admin (diaudit) | FR-PN-2..4 | Tes ambang tepat |
| BE-7.4 | Integrasi FCM + email, semua via queue dengan retry dan log; endpoint pendaftaran token perangkat | FR-NT | Tiap jenis notifikasi punya template dan tes |

## Fase 8 Backend: Fitur lanjutan booking (bisa dibuang jika waktu mepet)

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| BE-8.1 | Booking berulang: aturan seri + kebijakan konflik (lewati/tolak seluruhnya) | FR-BK-4 | Tes mingguan 10 kali, konflik di kemunculan ke-3 |
| BE-8.2 | Edit/batalkan satu kemunculan vs seluruh seri | FR-BK-4 | Tes kedua mode |
| BE-8.3 | Waitlist: antrean, penawaran saat slot lepas, batas klaim, kedaluwarsa | FR-BK-5 | Tes dua klaim bersamaan, hanya satu menang |
| BE-8.4 | Bundled booking ruangan + aset (semua atau tidak sama sekali) | FR-BK-7 | Transaksi atomik |

## Fase 9 Backend: Laporan, audit, hardening, rilis

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

# BAGIAN B: FLUTTER — SEDANG DIKERJAKAN

Aturan khusus jalur Flutter:
- State management **Riverpod**, struktur per fitur (feature-first), repository terpisah dari UI.
- Aplikasi **tidak membuat keputusan keamanan**. Ia hanya mengirim bukti (koordinat, akurasi, timestamp, flag mock, frame wajah/liveness, token QR) dan menampilkan hasil server beserta kode alasan.
- Setiap layar punya state loading, error, dan kosong. Kode alasan terstruktur dari server dipetakan ke pesan yang bisa ditindaklanjuti.
- Izin kamera dan lokasi ditangani lengkap: belum diminta, ditolak, ditolak permanen, dicabut (FR-APP-1).
- Kontrak API mengikuti `docs/openapi.yaml`. Jika kontrak terasa kurang, minta perubahan di jalur backend, jangan menebak sendiri.
- Tes wajib: `flutter analyze` bersih, widget test untuk layar utama, unit test untuk repository dan pemetaan error.
- Platform awal: Android (PRD 14 #2).

## Cara memakai bagian Flutter

1. Roadmap ini sudah satu file (`ROADMAP.md`); tidak perlu file terpisah.
2. Buka opencode di root monorepo, mulai sesi baru **per fase**.
3. Salin blok prompt fase yang sedang dikerjakan, tempel, jalankan. Tidak perlu menambah apa pun.
4. Agent berhenti di tiap **[REVIEW]** dan **[CHECKPOINT]**. Jawab "lanjut" atau beri koreksi, lalu agent meneruskan.
5. Selesai fase: merge PR, lanjut ke fase berikutnya dengan sesi baru.

Asumsi saya (koreksi jika salah):
- Skill dipanggil dengan `/nama-skill` di dalam teks prompt, dan opencode memuat skill itu.
- Saya tidak tahu isi persis tiap skill. Pembagian peran di bawah berdasarkan nama: `code-reviewer` untuk meninjau diff tiap task, `code-review` untuk tinjauan PR/fase secara menyeluruh. Ubah jika perilaku keduanya ternyata berbeda.
- `mcp-builder` **tidak dipakai** di jalur Flutter (tidak ada MCP server yang dibangun).
- **[CHECKPOINT]** adalah tambahan saya (bukan [REVIEW] dari roadmap asli): titik berhenti untuk keputusan arsitektur supaya agent tidak jalan jauh ke arah yang salah.

## Pemetaan skill per tahap kerja

| Tahap | Skill | Fungsi di proyek ini |
|---|---|---|
| Aturan kerja (selalu, di awal tiap prompt) | `/karpathy-guidelines` | Berpikir dulu, sederhana, perubahan bedah, berorientasi tujuan terukur |
| Memahami kontrak & kode yang ada | `/code-explorer` | Baca `docs/openapi.yaml`, backend, dan kode Flutter fase sebelumnya |
| Merancang struktur | `/code-architect` | Blueprint feature-first Riverpod, repository, alur state |
| Implementasi | `/feature-dev` | Alur pengembangan fitur terarah per task |
| Tampilan layar | `/frontend-design` | Desain UI layar (dipakai di fase yang punya layar) |
| Kebersihan kode | `/code-quality` | Setelah implementasi, sebelum review |
| Review per task | `/code-reviewer` | Tinjau diff task sebelum commit |
| Review akhir fase | `/code-review` | Tinjau seluruh perubahan fase sebelum PR |
| Memelihara konteks agent | `/agents-md-improver` (Fase 0, 9), `/agents-md-revise` (akhir tiap fase) | `AGENTS.md` di `mobile/` tetap akurat |
| Opsional di Fase 9 | `/skill-creator` | Membuat skill `flutter-conventions` khusus proyek |

## Perubahan dari rencana awal Bagian B

- **Backend sudah selesai**, jadi semua "menunggu task backend" di Bagian C sudah terpenuhi. Flutter dikerjakan langsung terhadap backend asli (Docker Compose lokal atau staging). **FL-0.2** tetap membuat HTTP client + pemetaan error, tapi mock server Prism menjadi **opsional** (hanya cadangan jika backend lokal bermasalah).
- Aturan tetap berlaku: jika kontrak di `docs/openapi.yaml` kurang atau ambigu, agent **tidak menebak**. Ia berhenti dan mencatat permintaan di `docs/backend-requests.md`.
- **FL-4.3 dan FL-5.3 (uji lapangan)** tidak bisa dikerjakan agent. Agent menyiapkan formulir/checklist uji, Anda yang menguji di HP sungguhan, hasilnya jadi masukan resmi untuk **BE-4.7** dan **BE-5.10** (kalibrasi ambang backend). Jika kedua task backend itu belum Anda jalankan, ini saatnya.
- Fase 8 hanya dikerjakan jika Fase 8 backend memang ada di `docs/openapi.yaml`.

## Ringkasan fase

| Fase | Task | Isi | Skill utama |
|---|---|---|---|
| 0 | FL-0.1..0.4 | Fondasi: scaffold, Dio, izin, CI | code-explorer, code-architect, feature-dev, agents-md-improver |
| 1 | FL-1.1, 1.2 | Login dan katalog | feature-dev, frontend-design |
| 2 | FL-2.1, 2.2 | Kalender dan booking | feature-dev, frontend-design |
| 3 | FL-3.1 | Layar approver | feature-dev, frontend-design |
| 4 | FL-4.1..4.3 | Check-in GPS + QR | code-architect, feature-dev |
| 5 | FL-5.1..5.4 | Face recognition + liveness | code-architect, feature-dev, code-reviewer |
| 6 | FL-6.1..6.3 | Layar manager (aset) | feature-dev, frontend-design |
| 7 | FL-7.1, 7.2 | Notifikasi dan penalti | feature-dev |
| 8 | FL-8.1..8.3 | Booking lanjutan (opsional) | feature-dev, frontend-design |
| 9 | FL-9.1..9.4 | Tes integrasi, keamanan, rilis | code-review, code-quality, skill-creator |

Estimasi waktu tidak saya ubah dari roadmap asli; kalibrasi ulang memakai kecepatan nyata Fase 0-1.

---

## Fase 0 Flutter: Fondasi Flutter

| ID | Task | Acceptance criteria |
|---|---|---|
| FL-0.1 | Scaffold Flutter di `mobile/`: struktur feature-first, Riverpod, routing, tema, konfigurasi environment (dev/staging) | `flutter analyze` dan `flutter test` bersih |
| FL-0.2 | HTTP client (Dio) + interceptor token + pemetaan error terstruktur (`code`, `message`, `details`). Mock Prism opsional | Aplikasi bisa memanggil backend asli; unit test pemetaan error |
| FL-0.3 | Modul izin reusable (kamera + lokasi) dengan semua status izin dan layar penjelasan | Widget test untuk tiap status izin (belum diminta, ditolak, ditolak permanen, dicabut) |
| FL-0.4 | CI Flutter (analyze + test) | PR gagal jika merah |

### Prompt Fase 0

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking System. Backend (Laravel + face service) sudah SELESAI. Sekarang kerjakan jalur Flutter, Fase 0 (FL-0.1 sampai FL-0.4), di folder mobile/ pada monorepo ini.

Baca dulu: ROADMAP.md (Bagian B, Fase 0 Flutter), PRD.md (bagian 2, 5, 12, 15), docs/openapi.yaml.

Alur kerja, ikuti urutan ini:
1. /code-explorer: pelajari docs/openapi.yaml dan backend. Rangkum format error, autentikasi Sanctum, dan daftar kode alasan. Jangan ubah file apa pun.
2. /code-architect: rancang blueprint mobile/: feature-first, Riverpod, repository terpisah dari UI, routing, tema, env dev/staging. Tampilkan blueprint, lalu BERHENTI di [CHECKPOINT] menunggu persetujuan saya.
3. /agents-md-improver: buat mobile/AGENTS.md (struktur, perintah analyze/test, aturan: app tidak membuat keputusan keamanan, tidak ada log data sensitif, tidak menebak kontrak).
4. /feature-dev: implementasikan FL-0.1, FL-0.2, FL-0.3, FL-0.4 berurutan, satu task satu branch/PR (nama branch memuat ID task). Tulis tes bersama kode.
5. Setelah tiap task: /code-quality lalu /code-reviewer pada diff-nya, perbaiki temuan, jalankan flutter analyze dan flutter test sampai bersih.
6. Akhir fase: /code-review untuk seluruh perubahan fase, lalu /agents-md-revise untuk memperbarui AGENTS.md.

Aturan keras:
- Satu task = satu branch/PR, sebut ID task dan requirement (FR-APP-1/2/3) di deskripsi PR.
- Jika kontrak API kurang atau ambigu, jangan menebak: berhenti dan tulis permintaan di docs/backend-requests.md.
- Jangan ubah keputusan teknis PRD bagian 2 (Flutter Android dulu, Riverpod, Dio).
- Tidak ada rahasia, token, atau data pribadi di commit atau log.
- Jika ada ketidakjelasan yang mengubah perilaku, tanya saya, jangan menebak.
Selesai jika acceptance criteria tiap task terpenuhi dan CI hijau. Laporkan ringkas: apa yang dibuat, hasil tes, dan hal yang perlu keputusan saya.
```

---

## Fase 1 Flutter: Auth dan katalog

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-1.1 | Layar login + token di secure storage + logout + penanganan 401 | FR-AUTH-1 | Login sukses; token tidak ada di log |
| FL-1.2 | Layar daftar/detail resource + pencarian + filter | FR-CAT-5 | Mengikuti OpenAPI; state loading/error/kosong |

Kontrak: `POST /auth/login`, `POST /auth/logout`, `GET /me`, `GET /resources`, `GET /resources/{id}`.

### Prompt Fase 1

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0 sudah selesai dan di-merge. Backend selesai. Kerjakan Fase 1 Flutter: FL-1.1 (login, secure storage, logout, 401) dan FL-1.2 (daftar/detail resource, pencarian, filter).

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 1), PRD.md (bagian 3, 4.1, 4.2, 5), docs/openapi.yaml.

Alur kerja:
1. /code-explorer: pelajari endpoint auth dan resources di OpenAPI, plus kode Flutter Fase 0 yang akan dipakai ulang (Dio client, pemetaan error, routing). Jangan ubah file.
2. Untuk tiap task (FL-1.1 dulu, lalu FL-1.2), satu branch/PR:
   a. /feature-dev: implementasi feature-first (data/domain/presentation), Riverpod, repository terpisah dari UI.
   b. /frontend-design: rancang layar (login; daftar, detail, pencarian, filter resource) dengan state loading, error, dan kosong. Konsisten dengan tema Fase 0.
   c. /code-quality lalu /code-reviewer pada diff. Perbaiki temuan.
   d. Tes: unit test repository dan pemetaan error, widget test layar utama, flutter analyze bersih.
3. Akhir fase: /code-review seluruh perubahan fase, lalu /agents-md-revise.

Aturan keras:
- Token hanya di secure storage, tidak pernah di log. 401 harus mengarahkan ke login dan membersihkan token.
- Resource yang tidak boleh diakses user tidak ditampilkan dari data yang dikirim server; app tidak memfilter keamanan sendiri.
- Kontrak kurang atau ambigu: jangan menebak, tulis di docs/backend-requests.md dan berhenti pada bagian itu.
- Tanya saya jika ada ketidakjelasan perilaku bisnis.
Laporkan: apa yang jadi, hasil tes, dan keputusan yang saya perlu ambil.
```

---

## Fase 2 Flutter: Booking

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-2.1 | Layar kalender ketersediaan + form booking | FR-BK-1 | Error konflik ditampilkan jelas (bukan crash) |
| FL-2.2 | Layar "Booking saya" + batalkan | FR-BK-6 | Status sinkron dengan server |

Kontrak: `GET /resources/{id}/availability`, `POST /bookings`, `GET /bookings`, `DELETE /bookings/{id}`, kode error konflik.

### Prompt Fase 2

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0-1 selesai. Backend selesai. Kerjakan Fase 2 Flutter: FL-2.1 (kalender ketersediaan + form booking) dan FL-2.2 (Booking saya + batalkan).

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 2), PRD.md (bagian 4.3, 6.1, 7), docs/openapi.yaml.

Alur kerja:
1. /code-explorer: pelajari endpoint availability, bookings, kode error konflik, dan status booking (pending, approved, checked_in, completed, rejected, cancelled, expired, no_show, waitlisted). Pelajari juga kode Flutter Fase 1 yang dipakai ulang. Jangan ubah file.
2. Untuk tiap task (FL-2.1 lalu FL-2.2), satu branch/PR:
   a. /feature-dev: implementasi sesuai pola Fase 1.
   b. /frontend-design: kalender ketersediaan yang mudah dipakai di HP, form booking, daftar booking dengan chip status, dialog batalkan. Semua state loading/error/kosong.
   c. /code-quality lalu /code-reviewer. Perbaiki temuan.
   d. Tes: unit test repository dan pemetaan error konflik, widget test layar utama, flutter analyze bersih.
3. Akhir fase: /code-review seluruh perubahan, lalu /agents-md-revise.

Aturan keras:
- Waktu dari server UTC; tampilkan dan kirim sesuai zona waktu lokasi (WIB). Cek konversi dengan tes.
- Konflik booking (409 atau kode setara) ditampilkan sebagai pesan yang bisa ditindaklanjuti, bukan crash. Jangan hitung konflik di sisi app; server yang memutuskan.
- Status yang ditampilkan selalu dari server, refresh setelah batal.
- Kontrak kurang: jangan menebak, tulis di docs/backend-requests.md.
Laporkan ringkas hasil dan keputusan yang perlu saya.
```

---

## Fase 3 Flutter: Approver

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-3.1 | Layar approver: daftar pending + detail + approve/reject dengan catatan | FR-AP-2 | Alur lengkap sampai keputusan terkirim |

Kontrak: `GET /approvals/pending`, `POST /approvals/{id}/approve|reject`.

### Prompt Fase 3

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0-2 selesai. Backend selesai. Kerjakan Fase 3 Flutter: FL-3.1 (layar approver).

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 3), PRD.md (bagian 3, 4.4, 5), docs/openapi.yaml.

Alur kerja:
1. /code-explorer: pelajari endpoint approvals dan cara role/permission dibaca dari GET /me. Jangan ubah file.
2. /feature-dev: implementasi FL-3.1 di satu branch/PR: daftar pending, detail permintaan, approve/reject dengan catatan.
3. /frontend-design: rancang layar approver (daftar, detail, input catatan, konfirmasi) dengan state loading/error/kosong. Navigasi approver hanya muncul untuk user dengan role approver, tetapi INGAT: itu hanya kenyamanan UI, otorisasi sebenarnya di server (403 harus ditangani dengan pesan jelas).
4. /code-quality lalu /code-reviewer. Perbaiki temuan.
5. Tes: unit test repository, widget test layar daftar dan keputusan, flutter analyze bersih.
6. /code-review seluruh perubahan fase, lalu /agents-md-revise.

Aturan keras: catatan wajib sesuai aturan server; keputusan yang sudah diambil orang lain (konflik/expired) ditangani tanpa crash; kontrak kurang dicatat di docs/backend-requests.md, bukan ditebak.
Laporkan ringkas.
```

---

## Fase 4 Flutter: Check-in (GPS + QR)

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-4.1 | Pengambilan lokasi: koordinat, akurasi, timestamp, flag mock; tahan izin ditolak/dicabut | FR-APP-1,3 | Semua kasus izin ditangani |
| FL-4.2 | Alur check-in: scan QR + kirim bukti lokasi, tampilkan hasil dan alasan dari server | FR-CI-1 | Setiap kode alasan penolakan punya pesan ramah |
| FL-4.3 | Uji lapangan GPS di lokasi dan perangkat nyata | FR-GPS-2 | Catatan di `docs/gps-field-test.md` |

Kontrak: `POST /bookings/{id}/check-in` dan daftar kode alasan penolakan. Check-in tanpa wajah dulu (slot wajah dimatikan di backend, sesuai Fase 4 backend).

### Prompt Fase 4

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0-3 selesai. Backend selesai. Kerjakan Fase 4 Flutter: FL-4.1 (lokasi), FL-4.2 (alur check-in QR + GPS), FL-4.3 (uji lapangan GPS).

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 4), PRD.md (bagian 4.6, 4.7, 6.2, 9), docs/openapi.yaml.

Alur kerja:
1. /code-explorer: pelajari kontrak POST /bookings/{id}/check-in, bentuk hasil (accepted/rejected/needs_manual_review), seluruh kode alasan penolakan, format bukti lokasi (koordinat, akurasi, timestamp, flag mock), dan format token QR. Jangan ubah file.
2. /code-architect: rancang modul lokasi dan alur check-in (paket geolokasi dan pemindai QR yang dipilih, cara mendeteksi flag mock di Android, state machine layar check-in). Tampilkan, lalu BERHENTI di [CHECKPOINT] menunggu persetujuan saya.
3. Untuk FL-4.1 lalu FL-4.2, satu branch/PR masing-masing:
   a. /feature-dev implementasi; pakai modul izin Fase 0 (FL-0.3).
   b. /frontend-design layar check-in (loading lokasi, pemindai QR, hasil sukses/gagal dengan alasan).
   c. /code-quality lalu /code-reviewer. Perbaiki temuan.
   d. Tes: setiap kode alasan penolakan terpetakan ke pesan ramah (unit test pemetaan), semua status izin lokasi/kamera, flutter analyze bersih.
4. FL-4.3 TIDAK bisa dikerjakan agent: buat template docs/gps-field-test.md (tabel: lokasi, perangkat, kondisi indoor/outdoor, akurasi yang dilaporkan, hasil diterima/ditolak, alasan) dan checklist langkah uji, lalu BERHENTI di [REVIEW] agar saya menguji di HP sungguhan (termasuk aplikasi mock-location). Hasil menjadi masukan BE-4.7.
5. Akhir fase: /code-review, lalu /agents-md-revise.

Aturan keras:
- App TIDAK memutuskan validitas check-in. Ia hanya mengirim bukti dan menampilkan hasil server beserta kode alasan.
- Jangan membuat pesan error sendiri untuk kode yang tidak ada di OpenAPI; catat di docs/backend-requests.md.
- Jangan log koordinat atau token QR mentah di build rilis.
Laporkan ringkas.
```

---

## Fase 5 Flutter: Face recognition

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-5.1 | Layar consent + enrollment dengan panduan, status enrollment, hapus data wajah | FR-FACE-1,6 | Panduan jelas; izin kamera lengkap; tanpa consent tidak bisa lanjut |
| FL-5.2 | Layar verifikasi wajah + tantangan liveness (kedip/tengok sesuai perintah server) di alur check-in | FR-FACE-2,3 | Terhubung end-to-end ke check-in |
| FL-5.3 | Uji lapangan: cahaya dan perangkat berbeda, serangan foto dari layar HP lain | FR-FACE | Catatan dibagikan ke jalur backend (BE-5.10) |
| FL-5.4 | Tampilan `needs_manual_review` dan alur fallback (batas retry tercapai) | FR-FACE-4 | Pesan jelas dan tidak membuntu |

Catatan wajib: tidak ada foto wajah tersimpan di perangkat setelah dikirim, dan tidak ada foto wajah di log.

### Prompt Fase 5

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0-4 selesai. Backend dan face service selesai. Kerjakan Fase 5 Flutter: FL-5.1 (consent + enrollment), FL-5.2 (verifikasi + liveness di check-in), FL-5.3 (uji lapangan), FL-5.4 (needs_manual_review + fallback).

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 5), PRD.md (bagian 4.5, 6.2, 8, 9, 10), docs/openapi.yaml, docs/face-model-choice.md, docs/risk-and-privacy.md.

Alur kerja:
1. /code-explorer: pelajari kontrak /face/enroll, /face/status, DELETE /face, format tantangan liveness dan bukti frame di endpoint check-in, status needs_manual_review dan sisa percobaan. Jangan ubah file.
2. /code-architect: rancang pengambilan frame kamera (resolusi, jumlah frame, urutan tantangan kedip/tengok sesuai perintah server), pengelolaan memori sehingga tidak ada foto tersimpan di disk perangkat, dan state machine layar enrollment dan verifikasi. Tampilkan, lalu BERHENTI di [CHECKPOINT].
3. Untuk FL-5.1, FL-5.2, FL-5.4 (urut), satu branch/PR masing-masing:
   a. /feature-dev implementasi.
   b. /frontend-design: layar consent (teks jelas, tanpa consent tidak bisa lanjut), panduan cahaya dan sudut, layar verifikasi dengan instruksi liveness, layar hasil dan fallback yang tidak membuntu.
   c. /code-quality lalu /code-reviewer dengan fokus privasi: pastikan tidak ada foto/frame di log, cache, atau penyimpanan permanen; izin kamera ditangani penuh.
   d. Tes: widget test alur consent, unit test pemetaan hasil/alasan, flutter analyze bersih.
4. FL-5.3 TIDAK bisa dikerjakan agent: buat template docs/face-field-test.md (cahaya, perangkat, hasil match, liveness, percobaan serangan foto dari layar HP lain) dan checklist, lalu BERHENTI di [REVIEW] agar saya menguji. Hasil jadi masukan BE-5.10.
5. Akhir fase: /code-review dengan fokus keamanan dan privasi, lalu /agents-md-revise.

Aturan keras:
- Keputusan match dan liveness hanya dari server. App tidak menghitung skor.
- Tidak ada foto wajah atau embedding di log, analytics, atau penyimpanan lokal.
- Tantangan liveness diikuti persis dari server; jangan membuat urutan sendiri.
- Kontrak kurang: tulis di docs/backend-requests.md, jangan menebak.
Laporkan ringkas.
```

---

## Fase 6 Flutter: Manager (aset)

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-6.1 | Layar handover: scan kode aset, verifikasi wajah peminjam, kirim lokasi | FR-LN-2 | Gagal verifikasi tidak mengubah tampilan status |
| FL-6.2 | Layar return: cek kondisi, upload foto, laporan kerusakan | FR-LN-3,4 | Foto terunggah dengan progres dan retry |
| FL-6.3 | Layar override verifikasi manual oleh manager (alasan wajib) | FR-FACE-4 | Tidak bisa dikirim tanpa alasan |

Kontrak: `POST /loans/{id}/handover`, `/return`, `/damage`, endpoint override manager.

### Prompt Fase 6

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0-5 selesai. Backend selesai. Kerjakan Fase 6 Flutter: FL-6.1 (handover), FL-6.2 (return + foto + kerusakan), FL-6.3 (override manager).

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 6), PRD.md (bagian 4.5 FR-FACE-4, 4.8, 5), docs/openapi.yaml.

Alur kerja:
1. /code-explorer: pelajari endpoint loans (handover, return, damage), endpoint override, status siklus loan (reserved, handed_over, in_use, returned, closed), dan kode Flutter Fase 4-5 yang dipakai ulang (pemindai, wajah, lokasi). Jangan ubah file.
2. Untuk FL-6.1, FL-6.2, FL-6.3 (urut), satu branch/PR masing-masing:
   a. /feature-dev implementasi dengan memakai ulang modul scan, wajah, dan lokasi; jangan menduplikasi.
   b. /frontend-design: layar manager yang cepat dipakai (scan, status verifikasi, form kondisi, unggah foto dengan progres dan tombol coba lagi, form override dengan alasan wajib).
   c. /code-quality lalu /code-reviewer. Perbaiki temuan.
   d. Tes: tampilan status tidak berubah saat verifikasi gagal, tombol override nonaktif tanpa alasan, unggah foto retry, flutter analyze bersih.
3. Akhir fase: /code-review, lalu /agents-md-revise.

Aturan keras:
- Status loan selalu dari server, tidak diubah optimistis di app.
- Foto kondisi aset dikirim lalu tidak disimpan permanen di perangkat; tidak ada foto di log.
- Layar manager hanya untuk role manager di UI, tetapi 403 dari server tetap ditangani dengan pesan jelas.
- Kontrak kurang: tulis di docs/backend-requests.md.
Laporkan ringkas.
```

---

## Fase 7 Flutter: Notifikasi dan penalti

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-7.1 | Integrasi FCM: izin notifikasi, pendaftaran token perangkat, tap notifikasi membuka layar yang tepat | FR-NT | Izin notifikasi ditolak ditangani |
| FL-7.2 | Layar notifikasi + layar status penalti (strike, sisa waktu suspend) | FR-PN-3 | User suspended melihat alasan dan sisa waktu |

Kontrak: pendaftaran token perangkat, format payload notifikasi, `GET /me/strikes`.

### Prompt Fase 7

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0-6 selesai. Backend selesai. Kerjakan Fase 7 Flutter: FL-7.1 (FCM) dan FL-7.2 (layar notifikasi + status penalti).

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 7), PRD.md (bagian 4.9, 4.10, 5), docs/openapi.yaml.

Alur kerja:
1. /code-explorer: pelajari endpoint pendaftaran token perangkat, format payload notifikasi per jenis (approval, hasil approval, pengingat, waitlist, aset terlambat, peringatan keamanan), dan GET /me/strikes. Jangan ubah file.
2. FL-7.1 dulu, lalu FL-7.2, satu branch/PR masing-masing:
   a. /feature-dev implementasi: izin notifikasi (termasuk ditolak), pendaftaran dan pembaruan token, penanganan tap notifikasi dari foreground, background, dan terminated yang membuka layar yang tepat.
   b. /frontend-design untuk layar notifikasi dan status penalti (jumlah strike, alasan, sisa waktu suspend).
   c. /code-quality lalu /code-reviewer. Perbaiki temuan.
   d. Tes: pemetaan payload ke rute, izin ditolak tidak crash, tampilan suspended, flutter analyze bersih.
3. Akhir fase: /code-review, lalu /agents-md-revise.

Aturan keras:
- Konfigurasi Firebase (google-services.json dll) berasal dari saya. Jangan membuat atau menaruh kredensial; jika dibutuhkan, berhenti dan minta saya menyediakannya. Jangan commit rahasia.
- Token FCM tidak ditulis ke log.
- Kontrak kurang: tulis di docs/backend-requests.md.
Laporkan ringkas, dan sebutkan langkah manual Firebase yang perlu saya lakukan.
```

---

## Fase 8 Flutter: Booking lanjutan (opsional)

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-8.1 | UI booking berulang (aturan seri, kebijakan konflik) dan edit/batal seri | FR-BK-4 | Sinkron dengan OpenAPI |
| FL-8.2 | UI waitlist: gabung antrean, terima/tolak penawaran sebelum batas klaim | FR-BK-5 | Hitung mundur sesuai waktu server |
| FL-8.3 | UI bundled booking ruangan + aset | FR-BK-7 | Hasil gagal menjelaskan resource mana yang bentrok |

### Prompt Fase 8

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0-7 selesai. Kerjakan Fase 8 Flutter (opsional): FL-8.1 (booking berulang), FL-8.2 (waitlist), FL-8.3 (bundled).

LANGKAH 0: pakai /code-explorer untuk memeriksa apakah docs/openapi.yaml dan backend benar-benar punya endpoint seri/recurring, waitlist, dan bundled. Jika ada yang tidak ada, JANGAN dikerjakan: laporkan task mana yang dilewati dan kerjakan hanya yang kontraknya ada.

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 8), PRD.md (bagian 4.3 FR-BK-4,5,7, bagian 7), docs/openapi.yaml.

Alur kerja untuk tiap task yang kontraknya ada (satu branch/PR masing-masing):
1. /feature-dev implementasi.
2. /frontend-design untuk UI: pilihan aturan seri dan kebijakan konflik (lewati/tolak seluruhnya), edit/batal satu kemunculan vs seluruh seri, antrean waitlist dan hitung mundur klaim dari waktu server (bukan jam perangkat), pesan bundled yang menyebut resource mana yang bentrok.
3. /code-quality lalu /code-reviewer. Perbaiki temuan.
4. Tes: hitung mundur memakai waktu server, hasil bentrok bundled terpetakan jelas, flutter analyze bersih.
5. Akhir fase: /code-review, lalu /agents-md-revise.

Aturan keras: kontrak kurang dicatat di docs/backend-requests.md, bukan ditebak. Laporkan ringkas termasuk task yang dilewati.
```

---

## Fase 9 Flutter: Pemolesan dan rilis

| ID | Task | Req | Acceptance criteria |
|---|---|---|---|
| FL-9.1 | Integration test alur kritis: login → booking → check-in 3 faktor (terhadap staging/mock) | NFR-5 | Hijau di CI |
| FL-9.2 | Tinjauan keamanan sisi klien: secure storage, tanpa rahasia di build, tanpa log sensitif, pinning/HTTPS | NFR-1 | Daftar temuan dan perbaikan |
| FL-9.3 | Pemolesan: pesan error, aksesibilitas dasar, performa daftar dan kalender | - | Daftar periksa selesai |
| FL-9.4 | Build rilis Android + panduan instalasi untuk staging | - | APK/AAB terpasang di perangkat uji. **[REVIEW]** demo ke mentor/client |

### Prompt Fase 9

```text
/karpathy-guidelines

Konteks: proyek Smart Room & Asset Booking, jalur Flutter di mobile/. Fase 0-8 selesai (Fase 8 sesuai yang dikerjakan). Backend sudah di staging (BE-9.8) atau bisa dijalankan lokal. Kerjakan Fase 9 Flutter: FL-9.1 sampai FL-9.4.

Baca dulu: mobile/AGENTS.md, ROADMAP.md (Bagian B, Fase 9), PRD.md (bagian 9, 10, 13), docs/openapi.yaml, docs/risk-and-privacy.md.

Alur kerja:
1. FL-9.1: /feature-dev tulis integration test alur login, booking, check-in 3 faktor terhadap staging atau backend lokal (wajah/GPS/QR di-stub sesuai yang aman). Pastikan hijau di CI.
2. FL-9.2: /code-review dengan fokus keamanan klien pada seluruh mobile/: secure storage, tidak ada rahasia di build atau repo, tidak ada log data sensitif (token, koordinat mentah, foto, embedding), HTTPS dan certificate pinning. Hasilkan daftar temuan di docs/flutter-security-review.md, perbaiki yang kritis, lalu /code-reviewer atas perbaikannya.
3. FL-9.3: /code-quality lalu /frontend-design untuk memoles pesan error, aksesibilitas dasar (label, kontras, ukuran sentuh), dan performa daftar serta kalender. Buat daftar periksa dan centang yang selesai.
4. FL-9.4: siapkan build rilis Android dan panduan instalasi staging (docs/flutter-install-guide.md). Penandatanganan/keystore berasal dari saya; jangan membuat atau commit keystore. BERHENTI di [REVIEW] untuk demo ke mentor/client.
5. Penutup: /agents-md-improver untuk merapikan mobile/AGENTS.md versi final. Opsional, tawarkan dulu dan tunggu persetujuan saya: /skill-creator untuk membuat skill flutter-conventions dari pola yang sudah terbentuk.

Aturan keras: satu task satu branch/PR; definition of done = acceptance criteria terpenuhi, tes hijau di CI, flutter analyze bersih, tidak ada rahasia atau data pribadi di commit. Laporkan ringkas.
```

---

## Prompt cadangan (pakai kapan saja)

**Lanjut setelah berhenti di [REVIEW] atau [CHECKPOINT]**

```text
/karpathy-guidelines
Lanjutkan pekerjaan fase ini dari titik berhenti terakhir. Keputusan saya: <tulis keputusan atau "disetujui">. Tetap ikuti alur dan aturan keras di prompt fase sebelumnya.
```

**Kontrak API kurang / backend perlu diubah**

```text
/karpathy-guidelines
/code-explorer
Baca docs/backend-requests.md. Untuk tiap permintaan, periksa backend dan docs/openapi.yaml, tentukan apakah memang kurang atau hanya salah baca, lalu susun daftar perubahan backend minimum (endpoint, field, kode alasan) yang perlu saya setujui. Jangan mengubah kode apa pun.
```

**Tinjau PR sebelum merge**

```text
/karpathy-guidelines
/code-review
Tinjau seluruh diff branch ini terhadap acceptance criteria task di ROADMAP.md Bagian B, aturan "app tidak membuat keputusan keamanan", dan definition of done. Beri daftar temuan berurutan menurut tingkat keparahan. Jangan mengubah kode.
```

---

---

# BAGIAN C: TITIK SINKRONISASI BACKEND ↔ FLUTTER

Task Flutter di kolom kiri baru bisa diuji terhadap server asli jika task backend di kolom kanan sudah selesai. Karena Bagian A sudah selesai, kolom status menunjukkan kesiapan saat ini. Kontrak endpoint yang harus ada di `docs/openapi.yaml` per task tercantum di tabel fase Bagian B.

| Task Flutter | Task backend terkait | Status |
|---|---|---|
| FL-1.1, 1.2 | BE-1.2, BE-1.6 | Siap |
| FL-2.1, 2.2 | BE-2.3, 2.4, 2.5 | Siap |
| FL-3.1 | BE-3.2 | Siap |
| FL-4.2 | BE-4.3, 4.5 | Siap |
| FL-4.3 → | **BE-4.7** | Hasil uji Flutter jadi masukan kalibrasi |
| FL-5.1, 5.2, 5.4 | BE-5.7, 5.8, 5.9, FS-5.2..5.4 | Siap |
| FL-5.3 → | **BE-5.10** | Hasil uji Flutter jadi masukan evaluasi gabungan |
| FL-6.1..6.3 | BE-6.2, 6.3, 5.9 | Siap |
| FL-7.1, 7.2 | BE-7.3, 7.4 | Siap |
| FL-8.x | BE-8.1..8.4 | Periksa dulu di OpenAPI (Fase 8 opsional) |
| FL-9.1 | BE-9.8 | Staging harus berjalan |

Aturan sinkronisasi 1-4 dari roadmap asli tetap berlaku (kontrak lebih dulu, perubahan breaking diumumkan, hasil uji lapangan jadi masukan resmi, kode alasan disepakati di `docs/openapi.yaml`).

Aturan sinkronisasi:
1. Backend **menerbitkan kontrak (OpenAPI) sebelum** mengimplementasikan endpoint, supaya Flutter bisa mulai lebih awal.
2. Perubahan kontrak yang merusak (breaking) diumumkan di deskripsi PR dan memicu task penyesuaian di jalur Flutter.
3. Hasil uji lapangan Flutter (FL-4.3, FL-5.3) adalah masukan resmi untuk kalibrasi backend (BE-4.7, BE-5.10). Backend tidak mengubah ambang tanpa data itu.
4. Daftar kode alasan penolakan (check-in, verifikasi) disepakati bersama dan disimpan di `docs/openapi.yaml`, agar pesan di Flutter tidak mengarang sendiri.
5. Jika Flutter menemukan kontrak kurang, ia mencatatnya di `docs/backend-requests.md`; perubahan dikerjakan sebagai PR backend terpisah yang memperbarui OpenAPI.

---

## Jalur kritis dan risiko

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Double booking lolos saat konkurensi | Fitur inti rusak | Tes paralel nyata di BE-2.3, review manual |
| Akurasi GPS di dalam gedung buruk | Check-in sah ditolak | FL-4.3 + BE-4.7, ambang bisa dikonfigurasi, jalur `needs_manual_review` |
| Liveness mudah ditembus / FRR tinggi | Keamanan lemah atau user frustrasi | FS-5.6, FL-5.3, BE-5.10, threshold bisa diubah, fallback manager |
| Regulasi data biometrik | Risiko hukum | Consent, minimisasi, hapus data, konsultasi mentor di BE-9.5 |
| Flutter menunggu backend | Jalur Flutter menganggur | Tidak lagi relevan: backend selesai; kontrak kurang dicatat di `docs/backend-requests.md` |
| Kontrak API berubah diam-diam | Flutter rusak | Aturan sinkronisasi nomor 1 dan 2 |
| Estimasi AI agent terlalu optimis | Jadwal mundur | Kalibrasi setelah Fase 1; Fase 8 sebagai penyangga |
| Review manusia jadi bottleneck | Agent menganggur | Titik [REVIEW] dijadwalkan di awal; jalur paralel tetap berjalan |
| Agent menebak kontrak atau membuat keputusan keamanan di app | Celah keamanan, bug sulit dilacak | Aturan keras di tiap prompt fase; `/code-reviewer` per task dan `/code-review` per fase |

## Definition of done

Semua jalur:
- Acceptance criteria terpenuhi dan tes baru ada serta hijau di CI.
- Tidak ada rahasia atau data pribadi di commit.
- PR menyebut ID task dan ID requirement.

Tambahan backend: Pint dan Larastan bersih; `docs/openapi.yaml` dan `docs/erd.md` diperbarui bila kontrak atau skema berubah.

Tambahan Flutter: `flutter analyze` bersih; layar punya state loading/error/kosong; tidak ada keputusan keamanan di sisi klien; tidak ada log data sensitif (token, koordinat mentah, foto, embedding).