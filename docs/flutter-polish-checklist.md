# Flutter polish checklist (FL-9.3)

## Done

- [x] Structured errors: `ApiError.toString`, `apiErrorMessage`, no `Instance of ...` in UI.
- [x] Every list screen: loading state with semantic label, error state with "Coba lagi", empty state.
- [x] Login: email/password autofill, keyboard actions, disabled button while loading.
- [x] Booking list shows WIB date/time and human-readable status; check-in and cancel actions.
- [x] Resource list has tooltips for icon buttons and a menu (face, notifications, penalties, loans, logout).
- [x] Check-in result uses a live region, success/error colors, and per-reason guidance from the server.
- [x] Icon-only actions have tooltips (accessible names for screen readers).
- [x] Lists use `ListView.builder`; no unbounded eager lists.
- [x] Session expiry returns the user to login after a 401.
- [x] Status wording centralized in `core/formatting/formatters.dart`.

## Manual device checks (owner)

- [ ] TalkBack: login, resource list, booking list, check-in result announce sensible labels.
- [ ] Font scale 200%: login, booking form, check-in result do not overflow.
- [ ] Contrast: error/success containers readable in light and dark system themes.
- [ ] Touch targets: icon buttons at least 48dp (Material default) on a small phone.
- [ ] Low-end device: resource and booking lists scroll smoothly with 200+ rows.
- [ ] Rotation and background/foreground during check-in do not lose evidence state.
- [ ] Release build shows no debug banner and no cleartext traffic warnings.
