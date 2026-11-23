# PRD: Smart Room & Asset Booking System

Versi 1.0 | Sumber: brief client "Smart Room & Asset Booking System v2 (face recognition + GPS lock)"

Dokumen ini ditulis agar bisa dibaca dan dikerjakan AI agent. Setiap requirement punya ID (FR-xx, NFR-xx) yang dirujuk di `ROADMAP.md`.

---

## 1. Ringkasan

Platform pemesanan ruangan dan peminjaman aset untuk kampus/kantor. Pengguna memesan ruangan dan meminjam aset, persetujuan mengikuti aturan yang bisa dikonfigurasi, dan check-in memakai tiga faktor: **GPS** (orang ada di lokasi), **wajah** (orang yang benar), **QR** (ruangan yang benar).

Bagian tersulit ada di logika bisnis dan keamanan, bukan di layar CRUD.

### Tujuan
1. Tidak ada double booking, bahkan saat dua orang memesan di detik yang sama.
2. Check-in hanya valid jika lokasi, wajah, dan QR semuanya lolos, divalidasi di server.
3. Semua aksi penting punya jejak audit.
4. Data wajah (biometrik) ditangani sesuai UU PDP: ada consent, minimal, terlindungi, bisa dihapus.

### Bukan tujuan (v1)
Stretch goal brief (realtime availability, offline scanning, sinkron kalender, saran slot pintar, face login 2FA, beacon Wi-Fi/Bluetooth) tidak masuk scope. Arsitektur tidak boleh menutup kemungkinan menambahkannya nanti.

---

## 2. Keputusan teknis (sudah ditetapkan)

| Area | Keputusan |
|---|---|
| Mobile | Flutter (Android dulu, iOS jika sempat) |
| Backend | Laravel (versi LTS/terbaru yang didukung), REST API, Sanctum untuk auth token |
| Database | **MySQL 8** (InnoDB) |
| Queue / cache | Redis (queue notifikasi, cache, rate limit) |
| Face recognition | **Service terpisah Python** (FastAPI), dipanggil Laravel lewat HTTP internal |
| Push notif | Firebase Cloud Messaging |
| Email | SMTP via Laravel Mail (queued) |
| Storage foto | Disk privat Laravel (bukan public), akses lewat signed URL |
| Testing | PHPUnit/Pest (backend), pytest (face service), flutter test + integration test |

Asumsi yang perlu dikonfirmasi tercantum di bagian 14.

---

## 3. Peran dan hak akses

| Peran | Kemampuan utama |
|---|---|
| Member | Cari resource, booking, pinjam aset, enroll wajah, check-in, lihat penalti |
| Approver | Lihat dan putuskan permintaan booking untuk resource terbatas |
| Resource Manager | Kelola ruangan/aset, serah-terima dan pengembalian aset, manual override verifikasi |
| Admin | Kelola user, aturan, geofence, laporan, konfigurasi threshold |

Aturan: otorisasi dilakukan di backend (Policy/Gate Laravel). Menyembunyikan tombol di app bukan kontrol keamanan.

---

## 4. Requirement fungsional

### 4.1 Auth dan user (FR-AUTH)
- FR-AUTH-1: Login email + password, token Sanctum per perangkat.
- FR-AUTH-2: Role-based access (member, approver, manager, admin). User bisa punya lebih dari satu role.
- FR-AUTH-3: Setiap user punya departemen/unit. Dipakai untuk pembatasan akses resource.
- FR-AUTH-4: Rate limit pada login dan endpoint verifikasi.
- AC: akses endpoint di luar role mengembalikan 403 dan tercatat di audit.

### 4.2 Katalog resource (FR-CAT)
- FR-CAT-1: Ruangan: nama, kapasitas, fasilitas, foto, jam buka, lokasi/geofence.
- FR-CAT-2: Aset dilacak per unit (laptop, kamera, proyektor) dengan kode unik, kondisi, status.
- FR-CAT-3: Kategori dan lokasi (gedung) sebagai pengelompokan.
- FR-CAT-4: Pembatasan akses resource per role atau departemen.
- FR-CAT-5: Pencarian dan filter (kategori, lokasi, kapasitas, fasilitas, ketersediaan pada rentang waktu).
- AC: resource yang tidak boleh diakses user tidak muncul di hasil pencarian dan ditolak saat booking.

### 4.3 Booking engine (FR-BK)
- FR-BK-1: Buat booking untuk ruangan atau aset pada rentang waktu.
- FR-BK-2: **Tanpa double booking**, termasuk request konkuren (lihat bagian 7).
- FR-BK-3: Aturan per resource: buffer antar booking, durasi min/maks, jendela booking di muka (advance window), jam buka.
- FR-BK-4: Booking berulang (mis. mingguan) dengan aturan akhir (tanggal atau jumlah). Konflik pada satu kemunculan ditangani jelas (lewati atau tolak seluruh seri, sesuai pilihan user).
- FR-BK-5: Waitlist saat slot terisi. Saat booking dibatalkan/dilepas, penawaran dikirim ke antrean pertama dengan batas waktu klaim.
- FR-BK-6: Pembatalan oleh user sebelum mulai; aturan batas waktu pembatalan bisa dikonfigurasi.
- FR-BK-7: Booking dengan banyak resource sekaligus (bundled, ruangan + proyektor) atomik: semua resource tersedia atau seluruh booking ditolak. Prioritas rendah; fitur ini yang pertama dibuang jika scope dipotong.
- AC: pengujian konkurensi (N request paralel ke slot yang sama) hanya menghasilkan satu booking sukses.

### 4.4 Alur persetujuan (FR-AP)
- FR-AP-1: Jalur approval per resource: tanpa approval, satu level, dua level.
- FR-AP-2: Approver bisa approve/reject dengan catatan dari aplikasi mobile.
- FR-AP-3: Permintaan pending kedaluwarsa atau dieskalasi ke level berikutnya setelah batas waktu yang bisa dikonfigurasi.
- FR-AP-4: Slot yang sedang pending tetap menahan slot (status `pending` ikut dihitung dalam pengecekan konflik).
- AC: setiap keputusan tercatat (siapa, kapan, catatan).

### 4.5 Face recognition (FR-FACE)
- FR-FACE-1: **Enrollment**: user mendaftarkan wajah sekali, dengan panduan sampel baik (cahaya, sudut). Minimal beberapa sampel, kualitas dicek.
- FR-FACE-2: **Verifikasi live**: check-in, serah-terima aset, dan pengembalian aset wajib cocok dengan wajah terdaftar.
- FR-FACE-3: **Liveness**: foto atau video orang lain tidak boleh lolos (tantangan acak: kedip atau tengok kepala, dengan verifikasi di server).
- FR-FACE-4: **Fallback**: batas percobaan ulang, lalu override manual oleh manager dengan alasan wajib dan entri audit.
- FR-FACE-5: Threshold kecocokan bisa dikonfigurasi admin. Tim mengukur **false accept rate (FAR)** dan **false reject rate (FRR)** dan melaporkannya.
- FR-FACE-6: Persetujuan eksplisit (consent) sebelum enrollment, plus fitur hapus data wajah.
- AC: hasil pengukuran FAR/FRR pada dataset uji tersimpan di `docs/face-evaluation.md`.

### 4.6 GPS lock (FR-GPS)
- FR-GPS-1: Setiap ruangan/gedung punya geofence (titik pusat + radius, atau polygon).
- FR-GPS-2: Check-in hanya diterima jika koordinat di dalam geofence, akurasi cukup (mis. <= 50 m, dikonfigurasi), dan pembacaan masih segar (mis. <= 30 detik).
- FR-GPS-3: Deteksi mock GPS (flag dari app) dan lompatan lokasi mencurigakan (kecepatan tidak wajar antar check-in).
- FR-GPS-4: **Validasi di server**, bukan hanya di app.
- FR-GPS-5: Admin bisa mengubah geofence dan membuat pengecualian (mis. area acara outdoor).
- AC: koordinat di luar radius, akurasi buruk, atau timestamp basi ditolak dan dicatat dengan alasan.

### 4.7 Check-in dan no-show (FR-CI)
- FR-CI-1: Check-in menggabungkan QR + GPS + wajah dalam jendela waktu booking (mis. 15 menit sebelum sampai batas setelah mulai, dikonfigurasi).
- FR-CI-2: QR ruangan statis bertanda tangan dicetak di pintu. Screenshot tidak dicegah, jadi check-in tetap mensyaratkan booking, GPS, wajah, dan jendela waktu yang benar (lihat bagian 9).
- FR-CI-3: Booking yang tidak di-check-in otomatis dilepas dan dicatat sebagai strike.
- FR-CI-4: Setiap percobaan check-in dan hasilnya tersimpan.
- AC: keputusan validitas check-in dibuat satu tempat di backend (`CheckInDecisionService`) dan mengembalikan alasan terstruktur.

### 4.8 Siklus peminjaman aset (FR-LN)
- FR-LN-1: Status: `reserved`, `handed_over`, `in_use`, `returned`, `closed`. Transisi di luar aturan ditolak.
- FR-LN-2: Serah-terima: wajah dan lokasi peminjam diverifikasi, resource manager memindai label QR statis aset; backend memvalidasi token dan mencocokkannya ke loan.
- FR-LN-3: Pengembalian: cek kondisi dengan foto.
- FR-LN-4: Pengingat keterlambatan, laporan kerusakan.
- AC: tiap transisi mencatat aktor, waktu, dan bukti (foto, hasil verifikasi).

### 4.9 Penalti (FR-PN)
- FR-PN-1: Strike untuk no-show, keterlambatan pengembalian, dan verifikasi gagal/mencurigakan.
- FR-PN-2: Suspend sementara setelah sejumlah strike dalam periode tertentu (angka dikonfigurasi).
- FR-PN-3: User suspended tidak bisa membuat booking baru; status dan sisa waktu terlihat di app.
- FR-PN-4: Admin bisa membatalkan strike dengan alasan (diaudit).

### 4.10 Notifikasi (FR-NT)
- Push dan email untuk: permintaan approval, hasil approval, pengingat booking, penawaran waitlist, aset terlambat, peringatan keamanan.
- Semua lewat queue, dengan retry dan pencatatan status kirim.

### 4.11 Laporan dan audit (FR-RP)
- FR-RP-1: Laporan: utilisasi ruangan, tingkat no-show, aset paling sering dipinjam, tingkat keberhasilan verifikasi.
- FR-RP-2: Audit trail: siapa mengubah apa dan kapan, termasuk setiap percobaan check-in. Audit bersifat append-only.

---

## 5. Aplikasi Flutter

| Peran | Layar |
|---|---|
| Member | Pencarian + kalender ketersediaan, buat booking, enrollment wajah, alur check-in (lokasi, kamera, QR), notifikasi, status penalti |
| Approver | Daftar permintaan pending, approve/reject |
| Manager | Serah-terima dan pengembalian dengan scan, verifikasi wajah, upload foto |

Requirement:
- FR-APP-1: Izin kamera dan lokasi ditangani lengkap: belum diminta, ditolak, ditolak permanen, dicabut. Selalu ada penjelasan jelas dan arahan ke pengaturan.
- FR-APP-2: Manajemen state konsisten (disarankan Riverpod), repository terpisah dari UI, error ditampilkan dengan pesan yang bisa ditindaklanjuti.
- FR-APP-3: Semua keputusan keamanan dibuat server; app hanya mengirim bukti (koordinat, flag mock, foto/frame liveness, token QR).

---

## 6. Aturan bisnis kunci

### 6.1 Status booking
`pending` -> `approved` -> `checked_in` -> `completed`
Cabang: `rejected`, `cancelled`, `expired`, `no_show`, `waitlisted` (sebelum `pending`/`approved`).
Status yang menahan slot: `pending`, `approved`, `checked_in`.

### 6.2 Keputusan check-in valid
Check-in valid jika **semua** terpenuhi:
1. Booking milik user, status `approved`, dan sekarang berada di jendela check-in.
2. QR pintu statis bertanda tangan valid dan cocok dengan ruangan booking.
3. GPS: di dalam geofence (atau pengecualian aktif), akurasi dan kesegaran memadai, tidak ada flag mock, tidak ada lompatan mencurigakan.
4. Wajah: skor di atas threshold dan liveness lolos.

Hasil: `accepted`, `rejected` (dengan kode alasan), atau `needs_manual_review`. Semua hasil dicatat. Kegagalan berulang memicu strike dan peringatan keamanan sesuai FR-PN.

### 6.3 Penalti
Nilai default (dapat diubah admin): no-show = 1 strike, telat kembali = 1 strike, verifikasi mencurigakan = 1 strike; 3 strike dalam 30 hari = suspend 7 hari.

---

## 7. Anti double booking di MySQL

MySQL tidak punya exclusion constraint seperti PostgreSQL, jadi pencegahan dilakukan di aplikasi dengan transaksi:

1. Mulai transaksi.
2. `SELECT ... FOR UPDATE` pada baris resource (mengunci per resource, bukan seluruh tabel).
3. Cek tumpang tindih dengan buffer: ada booking aktif di resource yang sama dengan `start < new_end + buffer` dan `end + buffer > new_start`.
4. Jika tidak ada konflik, insert booking, commit. Jika ada, rollback dan tawarkan waitlist.

Syarat: booking berulang memproses semua kemunculan dalam satu transaksi (atau dengan kebijakan parsial yang jelas). Wajib ada tes konkurensi (proses paralel nyata, bukan hanya tes berurutan). Deadlock ditangani dengan retry terbatas.

---

## 8. Face recognition: perbandingan opsi dan keputusan

| Kriteria | On-device (ML Kit/TFLite) | Service Python terpisah | Cloud API (mis. AWS Rekognition) |
|---|---|---|---|
| Keamanan terhadap manipulasi app | Rendah (hasil dari perangkat yang bisa di-tamper) | **Tinggi** (verifikasi di server) | Tinggi |
| Kontrol threshold dan pengukuran FAR/FRR | Terbatas | **Penuh** | Terbatas ke fitur vendor |
| Liveness | Harus dibangun sendiri di app | Bisa dibangun/diintegrasikan di server | Ada fitur liveness berbayar |
| Biaya | Gratis | Gratis (butuh server) | Per request |
| Data biometrik | Tetap di perangkat | Tetap di infrastruktur sendiri | Keluar ke vendor (risiko UU PDP, transfer lintas negara) |
| Kompleksitas | Sedang | Sedang | Rendah |

**Keputusan: service Python terpisah.** Alasan: validasi harus di server (konsisten dengan prinsip GPS), kontrol penuh atas threshold dan evaluasi, data biometrik tidak keluar dari infrastruktur sendiri.

### Rancangan service
- FastAPI, model embedding wajah open-source (mis. InsightFace/ArcFace), deteksi wajah dan cek kualitas.
- Hanya Laravel yang boleh memanggil (jaringan internal + shared secret/mTLS). Tidak diekspos publik.
- Endpoint inti:
  - `POST /enroll`: terima beberapa foto, cek kualitas, kembalikan embedding terenkripsi atau simpan di store sendiri.
  - `POST /verify`: terima frame/foto + data tantangan liveness + id user, kembalikan `{match, score, liveness_passed, reason}`.
  - `DELETE /users/{id}`: hapus data wajah.
- Penyimpanan: simpan **embedding** (vektor), bukan foto mentah, kecuali dibutuhkan untuk audit (lalu dengan retensi pendek). Embedding dienkripsi at rest.
- Liveness: tantangan acak dari server (kedip/tengok), dicek berurutan pada frame yang dikirim. Catatan: liveness aktif ini menahan serangan foto dan video sederhana, bukan serangan tingkat lanjut (deepfake, topeng). Batas ini dicatat di dokumen risiko.
- Evaluasi: skrip yang menghitung FAR/FRR pada dataset uji (pasangan sama/beda orang, plus sampel serangan foto/layar) dan memilih threshold default. Hasil di `docs/face-evaluation.md`.

---

## 9. Anti-kecurangan (threat model)

| Serangan | Deteksi / pencegahan |
|---|---|
| Foto atau video orang lain | Liveness acak di server + skor kualitas; kegagalan berulang jadi strike |
| GPS palsu | Flag mock dari app, validasi akurasi/kesegaran, deteksi lompatan, validasi server; catatan: app yang di-root bisa memalsukan flag, jadi sinyal tambahan dipakai (lihat stretch goal beacon) |
| Screenshot QR dibagikan | QR statis bertanda tangan di pintu; screenshot tidak bisa dicegah, dimitigasi oleh GPS, wajah, booking, dan jendela waktu di server |
| Pinjam akun | Enrollment wajah wajib, verifikasi wajah, deteksi perangkat berbeda |
| Replay request | Nonce/timestamp pada request check-in; QR statis tidak sekali pakai |
| App di-tamper | Semua keputusan di server; app hanya mengirim bukti |
| Brute force verifikasi | Rate limit, batas percobaan, strike |

Sumber QR: stiker QR statik bertanda tangan yang dicetak sekali dan ditempel di pintu ruangan. Keputusan bagian 14 #1: stiker statis.

---

## 10. Privasi dan kepatuhan

- Data wajah = data biometrik, termasuk data pribadi yang bersifat spesifik menurut UU PDP.
- Wajib: consent eksplisit dan terdokumentasi sebelum enrollment; tujuan penggunaan jelas; minimisasi data; enkripsi; kontrol akses ketat; retensi dan penghapusan; hak user untuk menghapus data wajah.
- Konsultasikan detail kepatuhan ke mentor (sesuai brief). PRD ini tidak menggantikan nasihat hukum.
- Log tidak boleh memuat foto wajah, embedding, atau token secara mentah.

---

## 11. Model data (garis besar)

`users`, `roles`, `departments`, `locations` (gedung + geofence), `resources` (tipe: room/asset), `rooms` (detail ruangan), `assets` (unit aset), `resource_rules` (buffer, durasi, advance window, approval path), `resource_access` (role/departemen), `bookings`, `booking_series` (recurring), `waitlist_entries`, `approvals` (per level), `check_in_attempts`, `face_enrollments` (consent, status, referensi embedding), `face_verifications`, `asset_loans`, `loan_events`, `condition_reports` (+ foto), `strikes`, `suspensions`, `notifications_log`, `audit_logs`, `settings` (threshold dan konfigurasi).

Catatan: kolom waktu disimpan UTC, ditampilkan sesuai zona waktu lokasi (WIB). Agent merinci kolom dan indeks di Fase 0/1 dan menyimpannya di `docs/erd.md`.

---

## 12. Garis besar API

Semua di bawah `/api/v1`. Format JSON, error terstruktur (`code`, `message`, `details`).

- Auth: `POST /auth/login`, `POST /auth/logout`, `GET /me`
- Katalog: `GET /resources`, `GET /resources/{id}`, `GET /resources/{id}/availability`; admin/manager: CRUD
- Booking: `POST /bookings`, `GET /bookings`, `DELETE /bookings/{id}`, `POST /bookings/{id}/waitlist`
- Approval: `GET /approvals/pending`, `POST /approvals/{id}/approve|reject`
- Wajah: `POST /face/enroll`, `GET /face/status`, `DELETE /face`
- Check-in: `POST /bookings/{id}/check-in` (QR + GPS + bukti wajah)
- Loan: `POST /loans/{id}/handover`, `POST /loans/{id}/return`, `POST /loans/{id}/damage`
- Penalti: `GET /me/strikes`
- Admin: geofence, aturan, threshold, user, laporan, audit

Agent menghasilkan spesifikasi OpenAPI (`docs/openapi.yaml`) pada Fase 1 dan menjaganya tetap sinkron.

---

## 13. Requirement non-fungsional

- NFR-1 Keamanan: validasi input, otorisasi per endpoint, tanpa rahasia di repo, HTTPS, rate limiting.
- NFR-2 Audit: append-only, mencakup setiap check-in.
- NFR-3 Keandalan: job terjadwal idempoten (aman dijalankan ulang).
- NFR-4 Kinerja: pengecekan ketersediaan dan booking merespons < 500 ms pada data wajar; verifikasi wajah < 3 detik di luar jaringan.
- NFR-5 Pengujian: unit + feature test untuk aturan bisnis; tes konkurensi booking; tes keputusan check-in (matriks kombinasi); integrasi face service; flutter test untuk alur kritis.
- NFR-6 Observabilitas: log terstruktur dengan correlation id.
- NFR-7 Dokumentasi: README setup, OpenAPI, ERD, dokumen evaluasi wajah, dokumen risiko dan privasi.

---

## 14. Asumsi dan pertanyaan terbuka

Agent tidak boleh memutuskan sendiri hal berikut tanpa konfirmasi; gunakan default sampai ada jawaban.

| # | Pertanyaan | Default sementara |
|---|---|---|
| 1 | Sumber QR: layar/tablet di ruangan (dinamis) atau stiker per ruangan? | Stiker QR statis bertanda tangan di pintu ruangan, dicetak sekali |
| 2 | Platform Flutter: Android saja atau iOS juga? | Android dulu |
| 3 | Satu atau banyak gedung/kampus? | Banyak lokasi dalam satu organisasi |
| 4 | Aset dan ruangan boleh dipesan bersamaan (bundled)? | Ya, prioritas rendah |
| 5 | Dari mana data user awal (impor CSV, SSO kampus)? | Impor CSV + login email/password |
| 6 | Hosting dan server untuk face service | Docker Compose di satu VPS |
| 7 | Dataset uji untuk FAR/FRR | Dikumpulkan tim dengan consent peserta |
| 8 | Zona waktu | Asia/Jakarta (WIB) |
| 9 | Antarmuka admin: lewat aplikasi Flutter, panel web terpisah, atau cukup API? (brief hanya merinci layar member, approver, manager) | Panel web sederhana (mis. Filament) di backend |

---

## 15. Aturan kerja untuk AI agent

1. Kerjakan **satu task roadmap per branch/PR**; sebutkan ID requirement di deskripsi.
2. Tulis tes bersama kode; task selesai jika acceptance criteria terpenuhi dan tes hijau.
3. Jangan mengubah keputusan teknis (bagian 2) tanpa persetujuan.
4. Jangan menaruh kredensial, foto wajah nyata, atau data pribadi di repo.
5. Setiap perubahan skema lewat migration; jangan edit migration lama yang sudah dipakai.
6. Perbarui `docs/openapi.yaml` dan `docs/erd.md` ketika kontrak atau skema berubah.
7. Jika ada ketidakjelasan yang mengubah perilaku bisnis, berhenti dan ajukan pertanyaan, jangan menebak.
8. Titik review manusia ditandai **[REVIEW]** di roadmap; agent tidak lanjut melewatinya tanpa persetujuan.
