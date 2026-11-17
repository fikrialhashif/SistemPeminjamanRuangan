# Flutter client security review (FL-9.2)

Scope: `mobile/` at the end of Flutter phase 9. Method: code review of network, storage, permissions, camera/location evidence, Android manifests, and release configuration. Backend authorization remains the source of truth; this review only covers the client.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| 1 | Critical | Release manifest had no `INTERNET` permission (Flutter only adds it to debug/profile), so a release APK could not reach the API. | Fixed in `android/app/src/main/AndroidManifest.xml`. |
| 2 | Critical | `main()` required a bundled `.env` asset that was never declared in `pubspec.yaml`; startup would fail and encouraged committing environment files. | Fixed: `dotenv.load(isOptional: true)` plus `--dart-define=API_BASE_URL=...` in `AppEnvironment`. |
| 3 | High | Camera `takePicture()` writes a JPEG to app temp storage; the file was read but never deleted. | Fixed in `FaceRepository.capture`: bytes are read and the temp file is deleted before returning. Frames are never written elsewhere. |
| 4 | High | Release builds used debug signing keys (`build.gradle.kts` template). | Open by design: keystore is provided by the owner. Guide: `docs/flutter-install-guide.md`. Do not ship a demo APK externally. |
| 5 | Medium | `android:allowBackup` defaulted to true, allowing app data backup. | Fixed: `allowBackup=false`, `fullBackupContent=false`. |
| 6 | Medium | On HTTP 401 the token was cleared but the user stayed on a protected screen, with no session reset. | Fixed: `dioProvider` clears the token and raises `sessionExpiryProvider`; `BookingApp` routes to login. `/auth/login` failures are excluded. |
| 7 | Medium | Raw error objects were rendered (`Instance of 'ApiError'`) and some screens showed no retry action. | Fixed: `ApiError.toString`, `apiErrorMessage`, shared `ErrorView` with retry. Error text never includes request bodies or tokens. |
| 8 | Medium | No TLS certificate pinning. | Open: needs the staging domain and SPKI pins. Do not hardcode pins before staging is final. Cleartext is allowed only in the debug manifest; release uses Android's default (blocked). |
| 9 | Low | Screen capture/recording is not blocked on face enrollment/verification screens. | Open: recommend `FLAG_SECURE` via a small platform channel if the client requires it. |
| 10 | Low | Manager handover screen still sends placeholder GPS (0,0) and a text field for face evidence. | Recorded in `docs/backend-requests.md`; connect the phase 4/5 evidence modules before real-device rollout. |

## Verified controls

- Access token is only stored with `flutter_secure_storage`; no token in source, logs, or preferences.
- No `print`, `debugPrint`, or Dio `LogInterceptor` in `lib/`; requests/responses are not logged.
- Coordinates, QR tokens, face frames, and embeddings are only sent in request bodies; no analytics or local persistence.
- App performs no security decisions: check-in, liveness, and match results are rendered from server responses.
- Camera/location permissions handle not-requested, denied, permanently denied, and restricted states.
- Release builds block cleartext HTTP by Android default; debug builds allow it only for the local emulator backend.
- Dependencies are the standard Flutter/Riverpod/Dio/geolocator/mobile_scanner/camera set; no abandoned or unknown packages.

## Release checklist

1. Build with `--dart-define=API_BASE_URL=https://<staging-host>/api/v1`; never ship the emulator default.
2. Sign with the owner's keystore (see install guide); do not commit the keystore or `key.properties`.
3. Keep the debug manifest (cleartext) out of store builds.
4. Re-check after staging URL is fixed: add certificate pinning and `FLAG_SECURE` if required by the mentor/client.
5. Run `flutter analyze` and `flutter test` plus the device checks in `docs/flutter-polish-checklist.md`.
