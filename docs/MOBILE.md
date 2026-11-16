# CHAMA WISE — Mobile App (Flutter)

Phase 5's actual deliverable per the roadmap, built in a session with real Flutter tooling but
**no Android emulator or physical device** — see "How this was verified" below for what that
means for the confidence level here, and `docs/DEVELOPMENT_PROGRESS.md`'s Phase 5 entries for the
full history (the REST API landed first, in an earlier session; this one is the app itself).

The app consumes `backend/docs/API.md`'s `/api/v1` surface exclusively — it holds no financial or
business logic of its own (Part 15). Every number, status, and permission decision shown on
screen is rendered verbatim from what the API returned; the app never recomputes a balance, a
penalty, or a "can I do this" decision.

## Scope: two screens, deliberately

Contributions and withdrawals — the two features a member reaches for most often on a phone —
are built. Accounts, accounting, expenses, member management, and chama settings are not: those
are officer/admin setup screens the web app already covers well and that suit a larger screen
more than a phone. See "Known issues" for the precise list of what a later session should add.

## Architecture

```
lib/
├── core/
│   ├── api_client.dart        Thin HTTP wrapper: auth header injection, error decoding
│   ├── api_exception.dart     Typed error (message, status, field errors) from any API call
│   ├── token_storage.dart     Sanctum token persistence (flutter_secure_storage)
│   ├── constants.dart         Brand palette, API base URL (overridable at build time)
│   └── models/                Plain Dart classes mirroring the API's JSON shapes
├── repositories/               One per resource; each wraps ApiClient, returns typed models
├── providers/                  ChangeNotifier per screen's state, calling a repository
├── screens/                    Login, register, chama list, chama shell (bottom nav), contributions, withdrawals
└── widgets/                     Small shared pieces (error/status banners)
```

`ApiClient` takes an injectable token provider specifically so it (and everything built on it)
can be unit-tested without a real device keystore — see `test/`.

## Running it

### Web (what this session actually used to verify the app)

```bash
cd mobile
flutter pub get
flutter build web --no-web-resources-cdn --dart-define=API_BASE_URL=http://127.0.0.1:8788/api/v1
cd build/web && python3 -m http.server 9090
```

`--no-web-resources-cdn` matters in a network-restricted environment: without it, Flutter web
fetches its CanvasKit renderer from `gstatic.com` at runtime, which fails silently (blank page)
if that host isn't reachable. With it, CanvasKit is bundled into `build/web` at build time.

The backend needs `config/cors.php` published (already committed — see
`backend/docs/API.md`/`docs/SECURITY.md`) so a browser on a different origin/port can reach
`/api/v1`; it already allows this out of the box.

### Android (not verified in this session — no SDK/emulator here)

```bash
cd mobile
flutter build apk --dart-define=API_BASE_URL=https://your-production-host/api/v1
```

Requires the Android SDK (`flutter doctor` will say what's missing) and, to actually see it run,
a device or emulator — neither exists in this container. The `--dart-define` is how the API's
real base URL gets baked in per build; `lib/core/constants.dart`'s default
(`http://localhost:8788/api/v1`) is a local-development convenience only.

## How this was verified

This container has Chrome and Flutter's web target, but no Android tooling and no emulator/
device — so "real browser verification" here means the actual **Flutter web build**, not a
screenshot of a design, and not the Android app:

1. `flutter analyze` — clean, zero issues.
2. `flutter test` — 17 tests: `ApiClient`'s header injection/error decoding (including a
   transport failure becoming a friendly message rather than an uncaught error), model JSON
   parsing, `AuthProvider`'s login/auto-login flows, and a full `LoginScreen` widget test
   (validation, success, and backend-error-message display).
3. **A real end-to-end run**: the Flutter web build, served locally, driven by Playwright against
   the actual Laravel API (`php artisan serve`, the seeded dev database) — logged in as the
   seeded `ken` user with a real password over a real HTTP request, which issued a real Sanctum
   token; listed `ken`'s real chama with his real role (`chairperson`) fetched live; opened
   Contributions and saw his real outstanding balance and real overdue obligations with penalties
   from the seed data; opened Withdrawals and saw his real savings balance, a real
   pending-approval queue entry, and his own pending request; and — deliberately — tapped
   "Approve" on his own withdrawal request to confirm the backend's self-approval block holds
   through this client too (it does: a 403 surfaces as "This action is unauthorized." in the UI,
   no crash). Screenshots from this run were sent to the user.

What this does **not** verify: anything Android-specific (platform channels, the real
`flutter_secure_storage` Keystore-backed implementation, actual phone screen sizes/gestures, a
release APK actually installing and running). That gap is real and stated plainly, not glossed
over — see Known issues.

## Two real bugs this session's own tooling caught

1. **`material_ui` migration.** This Flutter version (3.50) scaffolds new projects against
   `package:material_ui/material_ui.dart` instead of the historically bundled
   `package:flutter/material.dart` — Material widgets were decoupled out of the core framework
   into their own package. Fixed with Flutter's own `dart fix --apply --code=migrate_design_widgets`
   rather than hand-editing every import. A second, less obvious consequence: `MaterialApp` from
   `material_ui` does **not** carry the old implicit `MaterialLocalizations` fallback — every
   `TextField`/`TextFormField` threw "No MaterialLocalizations found" until
   `localizationsDelegates: GlobalMaterialLocalizations.delegates` (material_ui's own, not the
   `flutter_localizations` package's — they are two distinct, non-interchangeable classes of the
   same name, confirmed by a compile error when both were imported at once) was added explicitly
   to the app's `MaterialApp`. This would have blocked the login screen entirely, and was only
   caught by actually trying to type into a field in a widget test before any manual verification
   happened.
2. **Two `async` closures without `async`.** `ApiClient.get()`/`post()` passed
   `() => _client.get(uri, headers: await _headers())` to a helper — an arrow function containing
   `await` but never marked `async`, which `flutter analyze` (not a runtime test) caught
   immediately as a real compile error, before any test ran against it.

## Known issues

- **No Android verification at all** — the single biggest gap. This should be the first thing
  done in a session with real Flutter/Android tooling and a device or emulator, before trusting
  this app with a real chama: confirm the release build actually compiles and runs, that
  `flutter_secure_storage`'s Keystore-backed implementation behaves as expected (this session only
  ever exercised its web/mocked-channel paths), and that layouts hold up on real phone screens
  rather than the one viewport size (420×900 logical pixels) used here.
- **No account/accounting/expense/member-management/settings screens** — a deliberate scope
  line (see above), not an oversight; a future session can add them following the same
  repository/provider/screen pattern already established here.
- **No push notifications, no offline support, no biometric unlock** — none of these were asked
  for; noting them only so "not built" is explicit rather than silently assumed impossible.
- **Only English** — `supportedLocales` is `[Locale('en')]`; Kenya's other major languages
  (Swahili in particular, given the target market) are not localized. The app has no hardcoded
  user-facing strings framework in place for this yet (no `.arb` files); adding real localization
  is a dedicated piece of work, not a quick follow-up.
- **No CI** — tests and analyze are run manually; a GitHub Actions workflow mirroring the
  backend's would catch regressions automatically.
