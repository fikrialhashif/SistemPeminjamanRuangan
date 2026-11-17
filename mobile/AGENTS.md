# Mobile rules

Flutter Android-first app for the Smart Room & Asset Booking system.

## Architecture

- Feature-first under `lib/features/<feature>/{data,presentation}`; shared code in `lib/core`.
- Riverpod for state; repositories must not import widgets. UI reads providers, never Dio directly.
- Routing lives in `lib/app.dart` (`buildAppRouter`). Each `BookingApp` instance owns a fresh router.
- Server decides all security outcomes. The app only sends evidence (QR token, GPS reading, face frame/challenge) and renders `result` plus rejection reasons from the server.

## Hard rules

- Never log tokens, coordinates, QR tokens, face frames, embeddings, or personal data. Do not add Dio `LogInterceptor`/`print` for API traffic.
- Tokens only in `flutter_secure_storage`; never in `.env`, prefs, source, or tests.
- No security decisions in the app: do not pre-validate geofence, QR, face match, or liveness. Use `checkInDecisionMessage` for display only.
- Do not guess API contracts. Record gaps in `docs/backend-requests.md`; the backend currently returns `rejection_reasons` strings, which the app maps defensively.
- Do not commit `.env`, `key.properties`, keystores, or Firebase credentials. API base URL comes from `--dart-define=API_BASE_URL=...`; `.env` is optional for local dev only.
- Keep the debug-only cleartext HTTP allowance out of release builds.
- Camera captures are read into memory and the temp file deleted. Never persist face frames.

## Commands

```text
flutter pub get
dart format lib test
flutter analyze
flutter test
flutter build apk --release --dart-define=API_BASE_URL=https://<host>/api/v1
```

## Tests

- `test/support/fake_backend.dart` provides a Dio `HttpClientAdapter` and fake token storage. Override only `tokenStorageProvider`; read `dioProvider` from the container and set `httpClientAdapter` so auth/401 interceptors keep running.
- Integration flow: `test/integration/app_flow_test.dart` (login -> catalog -> booking -> check-in with QR/GPS/face evidence).
- Widget/unit tests cover login navigation, API error mapping, and check-in reason mapping.

## Docs

- `docs/flutter-security-review.md` — client findings and release checklist.
- `docs/flutter-polish-checklist.md` — accessibility/performance checks still needing a device.
- `docs/flutter-install-guide.md` — build, signing (owner-provided), install, smoke test.
- `docs/backend-requests.md` — contract gaps.
