# Changelog

All notable changes to `fcm-laravel` will be documented in this file.

## v1.0.0 - Initial release

### Added

- `FcmService` — sends push notifications to a device token via the Firebase Cloud Messaging HTTP v1 API, authenticating with a service account JSON (`google/auth`), without depending on the Firebase Admin SDK.
- `fcm` notification channel — implement `toFcm()` and `HasFcmPayload` on any notification to route it through FCM. Device tokens are resolved via Laravel's standard `routeNotificationFor('fcm', $notification)` convention.
- `FcmTokenRejected` event, dispatched when FCM reports a token as unregistered or invalid, so the host app can prune it without interrupting delivery to the notifiable's other tokens.
- Configurable delivery options in `config/fcm.php`: credentials path, HTTP timeout, Android priority/channel ID, APNs priority header, debug logging (with its own log channel, independent of the host app's `app.debug`), and a `dry_run` mode for local/staging.
- `FcmService::fake()` and `AmdadulHaq\Fcm\Testing\FcmFake` for asserting on sent pushes in tests (`assertSent`, `assertSentTo`, `assertNotSent`, `assertNothingSent`, `assertSentCount`) plus `rejectToken()` to simulate the unregistered-token path, without dispatching anything or hitting the network.
- Test suite (Pest + Orchestra Testbench) covering `FcmService`, the `fcm` channel, and `FcmFake`.
- Tooling: Pint (code style), Larastan (static analysis, level 8), Rector (automated refactoring, targeting Laravel up to 13.0 without attribute-only rewrites since Laravel 11 is still supported).
- CI workflows: test matrix across PHP 8.2-8.5 and Laravel 11/12/13, PHPStan, Pint auto-fix.
- Project scaffolding: `.gitignore`, `.gitattributes`, `.editorconfig`, `README.md`, `LICENSE.md`, `CONTRIBUTING.md`.
