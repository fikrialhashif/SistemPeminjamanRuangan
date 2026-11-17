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
