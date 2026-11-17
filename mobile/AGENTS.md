# Mobile rules

- Flutter Android-first, Riverpod, feature-first.
- Server decides security; app sends evidence and renders server results.
- Keep repositories separate from UI.
- Never log tokens, coordinates, face frames, embeddings, or personal data.
- Do not guess API contracts. Record gaps in `docs/backend-requests.md`.

## Commands

```text
flutter pub get
flutter analyze
flutter test
```
