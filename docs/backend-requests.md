# Backend contract requests

- Add `docs/openapi.yaml` covering `/api/v1` endpoints.
- Standardize authentication and validation errors as `{code, message, details}`. Current login failure returns only `{message}`.
- Document HTTP status codes and structured rejection reasons used by check-in and verification.

## Fase 6 Flutter

- Tambahkan endpoint override verifikasi manual manager. Roadmap mensyaratkan endpoint override pada FL-6.3, tetapi `backend/routes/api.php` belum mendefinisikan route atau kontrak request/response.
- Handover API menerima `face_image`, `gps_latitude`, `gps_longitude`, `gps_accuracy`, dan `asset_code`. Flutter saat ini perlu menghubungkan bukti kamera/lokasi Fase 4-5 ke form manager sebelum rilis perangkat nyata.
- Endpoint return menyimpan foto pada disk `public`; PRD mensyaratkan disk privat/signed URL. Perlu perubahan backend sebelum produksi.
