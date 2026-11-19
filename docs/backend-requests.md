# Backend contract requests

- Add `docs/openapi.yaml` covering `/api/v1` endpoints.
- Standardize authentication and validation errors as `{code, message, details}`. Current login failure returns only `{message}`.
- Document HTTP status codes and structured rejection reasons used by check-in and verification.

## Fase 6 Flutter

- Endpoint override verifikasi manual manager tersedia sebagai `POST /api/v1/loans/{loan}/override` dengan body `{reason}`.
- Handover API menerima `face_image`, `gps_latitude`, `gps_longitude`, `gps_accuracy`, dan `asset_code`. Flutter saat ini perlu menghubungkan bukti kamera/lokasi Fase 4-5 ke form manager sebelum rilis perangkat nyata.
- Endpoint return menyimpan foto pada disk `public`; PRD mensyaratkan disk privat/signed URL. Perlu perubahan backend sebelum produksi.

## Fase 8

- Endpoint recurring tersedia: `POST /api/v1/bookings/recurring`.
- Endpoint bundled tersedia: `POST /api/v1/bookings/bundled`.
- Waitlist tersedia: `POST /api/v1/bookings/{booking}/waitlist`; penawaran klaim masih diproses job backend, belum ada endpoint accept/decline atau UI countdown server.

## Konfirmasi 3 tahap (Flutter)

- `GET /api/v1/face/status` sekarang mengembalikan `{enrolled, enabled}`; Flutter memakai `enabled` hanya untuk UX, bukan keputusan keamanan.
- `GET /api/v1/face/challenge` menerbitkan tantangan `turn` (toleh kiri/kanan) dari face-service dan menyimpannya di cache server (5 menit, sekali pakai). `POST /api/v1/bookings/{booking}/check-in` menerima `face_image`, `face_challenge`, `face_challenge_response`; server memakai tantangan yang tersimpan, bukan nilai dari klien.
- Alasan penolakan baru: `face: challenge missing`, `face: challenge mismatch`, `face: liveness check failed`, `face: user not enrolled`, `face: service unavailable`.
- Booking ruangan (`type = room`) sekarang ditolak dengan HTTP 422 `{code: "face_enrollment_required", message: "..."}` bila `face_verification_enabled` aktif dan pengguna belum memiliki `face_enrollments.status = enrolled`. Booking aset tidak terpengaruh. Flutter memakai `code` ini untuk membuka pendaftaran wajah dari katalog/form booking.
- Alur pendaftaran: `POST /api/v1/face/enroll` dengan `{consent: true, images: [3 × base64 JPEG], poses: ["turn_left", "turn_right", "front"]}`, cek `GET /api/v1/face/status`, hapus dengan `DELETE /api/v1/face`. Face-service menyimpan baseline yaw dan menolak pendaftaran bila sampel toleh tidak benar-benar berubah arah. Enrollment/verifikasi membutuhkan face-service aktif (default `http://127.0.0.1:8001`).
- Tantangan check-in kini diverifikasi dari gambar: yaw wajah dibandingkan dengan baseline pendaftaran, sehingga `face_challenge_response` dari klien tidak lagi menentukan hasil. Data lama yang belum memiliki baseline yaw harus didaftar ulang agar check-in dapat diverifikasi.
- Check-in wajah kini mengirim dua frame: `face_front_image` (wajah menghadap kamera, dipakai untuk pencocokan identitas) dan `face_image` (wajah menoleh, dipakai untuk liveness yaw). `face-front-image` bersifat wajib bila verifikasi wajah aktif; klien lama tanpa field ini ditolak dengan `face: front image not provided`.
- Batasan: pendeteksian memakai dua frame foto diam, bukan video; gerakan toleh hanya diverifikasi dari perbedaan yaw antara frame depan dan frame tolehan pada face-service.

## Fase 9

- `POST /api/v1/bookings/{booking}/check-in` mengembalikan model `CheckInAttempt` dengan `result` dan `rejection_reasons` berupa string bebas (mis. `gps: Mock GPS detected`), bukan `{code, message, details}`. Flutter memetakan string tersebut secara defensif. Mohon tambahkan daftar `reason_code` terstruktur di `docs/openapi.yaml` agar pesan tidak bergantung pada teks bebas.
- `GET /api/v1/me/strikes` mengembalikan array langsung, sedangkan endpoint daftar lain memakai `{data: [...]}`. Keduanya didukung Flutter; mohon diseragamkan di kontrak.
- `POST /api/v1/loans/{loan}/return` masih menyimpan foto di disk `public`; perlu disk privat + signed URL sebelum produksi (sama seperti catatan Fase 6).
- Staging perlu HTTPS sebelum build rilis diarahkan ke sana; Flutter memakai `--dart-define=API_BASE_URL=https://...` dan tidak membundel berkas `.env`.

