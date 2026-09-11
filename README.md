# FCM (Firebase Cloud Messaging) for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amdadulhaq/fcm-laravel.svg?style=flat-square)](https://packagist.org/packages/amdadulhaq/fcm-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/amdad121/fcm-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/amdad121/fcm-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/amdad121/fcm-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/amdad121/fcm-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/amdadulhaq/fcm-laravel.svg?style=flat-square)](https://packagist.org/packages/amdadulhaq/fcm-laravel)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%2F12%2F13-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink?style=flat-square&logo=github)](https://github.com/sponsors/amdad121)

A simple, lightweight, fast Firebase Cloud Messaging (FCM) notification channel for Laravel. No Firebase Admin SDK, no bloat — just push notifications, configurable, with a fake for testing.

## Requirements

- PHP 8.2, 8.3, 8.4, or 8.5
- Laravel 11, 12, or 13

## Installation

```bash
composer require amdadulhaq/fcm-laravel
```

The service provider is auto-discovered. Publish the config file:

```bash
php artisan vendor:publish --tag=fcm-config
```

## Configuration

Set `FIREBASE_CREDENTIALS` to the path of your Firebase service account JSON file (download it from **Project settings → Service accounts** in the Firebase console). The Firebase project ID is read from that file automatically — nothing else is required to start sending.

```env
FIREBASE_CREDENTIALS=storage/app/firebase-adminsdk.json
```

See [`config/fcm.php`](config/fcm.php) for every option:

| Option | Env | Default | Purpose |
| --- | --- | --- | --- |
| `credentials` | `FIREBASE_CREDENTIALS` | — | Path to the service account JSON. Absolute or relative to the app base path. |
| `http.timeout` | `FCM_HTTP_TIMEOUT` | `10` | HTTP timeout (seconds) for the send request. |
| `android.priority` | `FCM_ANDROID_PRIORITY` | `high` | Default for every message's `android` payload. Set to `null` to omit. A notification can override it for itself alone — see [Notifications](#notifications). |
| `android.channel_id` | `FCM_ANDROID_CHANNEL_ID` | `default_channel` | Android notification channel ID. Set to `null` to omit. |
| `apns.priority` | `FCM_APNS_PRIORITY` | — | Sets `apns.headers.apns-priority` (`5` or `10`). Omitted from the payload unless set. |
| `debug` | `FCM_DEBUG` | `APP_DEBUG` | Logs skipped sends, rejected tokens, and delivery failures. Independent of the host app's `app.debug`. |
| `log_channel` | `FCM_LOG_CHANNEL` | — | Log channel debug messages are written to. Defaults to the app's default channel. |
| `dry_run` | `FCM_DRY_RUN` | `false` | Builds and logs the message but never calls the FCM API; `sendToToken()` returns `true` as if it succeeded. Handy for local/staging. |

## Notifications

Implement `toFcm()` on any notification, have it implement `HasFcmPayload`, and route it through the `fcm` channel:

```php
use AmdadulHaq\Fcm\Contracts\HasFcmPayload;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements HasFcmPayload
{
    public function via(object $notifiable): array
    {
        return ['database', 'fcm'];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Order shipped',
            'body' => "Your order #{$this->order->id} is on its way!",
            'data' => ['order_id' => (string) $this->order->id],
        ];
    }
}
```

Return an optional `priority` (`'high'` or `'normal'`) to override `config('fcm.android.priority')` for that one notification, leaving every other notification on the configured default:

```php
public function toFcm(object $notifiable): array
{
    return [
        'title' => 'Urgent: action needed',
        'body' => $this->body(),
        'priority' => 'high',
    ];
}
```

The channel resolves device tokens via Laravel's standard `routeNotificationFor('fcm', $notification)` convention. Add a `routeNotificationForFcm()` method to the notifiable:

```php
public function routeNotificationForFcm(): array
{
    return $this->deviceTokens()->pluck('token')->all();
}
```

Sends to a token that FCM reports as unregistered or invalid throw internally and are reported through the `AmdadulHaq\Fcm\Events\FcmTokenRejected` event instead of stopping delivery to the notifiable's other tokens — listen for it to prune the dead token from wherever you store them:

```php
use AmdadulHaq\Fcm\Events\FcmTokenRejected;

class PruneRejectedFcmToken
{
    public function handle(FcmTokenRejected $event): void
    {
        DeviceToken::query()->where('token', $event->token)->delete();
    }
}
```

## Sending directly

For a one-off send outside the notification system, use `FcmService` directly:

```php
use AmdadulHaq\Fcm\FcmService;

app(FcmService::class)->sendToToken(
    token: $deviceToken,
    title: 'Order shipped',
    body: 'Your order is on its way!',
    data: ['order_id' => '123'],
);
```

## Testing

Use `FcmService::fake()` to swap the real service with an in-memory fake and assert on what would have been sent, without dispatching anything or hitting the network:

```php
use AmdadulHaq\Fcm\FcmService;

$fake = FcmService::fake();

// ... code under test that notifies via the "fcm" channel ...

$fake->assertSent(fn (string $token, string $title, string $body, array $data) => $title === 'Order shipped');
$fake->assertSentTo($deviceToken);
$fake->assertSentCount(1);
$fake->assertNothingSent();
$fake->assertNotSent(fn (string $token) => $token === 'some-other-token');
```

`$fake->rejectToken($token)` makes sends to that token throw `FcmUnregisteredTokenException` (without a real FCM error response), so you can exercise the `FcmTokenRejected` pruning path in tests.

## Running the package's own test suite

```bash
composer install
composer test          # Pest
composer analyse        # Larastan
composer lint:check    # Pint
```

## License

MIT. See [LICENSE.md](LICENSE.md).
