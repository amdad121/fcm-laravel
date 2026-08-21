<?php

declare(strict_types=1);

use AmdadulHaq\Fcm\Events\FcmTokenRejected;
use AmdadulHaq\Fcm\Notifications\FcmChannel;
use AmdadulHaq\Fcm\Tests\Fixtures\FakeFcmNotifiable;
use AmdadulHaq\Fcm\Tests\Fixtures\FakeFcmNotification;
use AmdadulHaq\Fcm\Tests\Fixtures\PlainNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function fakeFcmCredentials(): string
{
    $path = sys_get_temp_dir().'/fcm-channel-test-'.uniqid().'.json';
    file_put_contents($path, json_encode(['project_id' => 'test-project']));

    return $path;
}

function seedFakeFcmAccessTokenForChannel(): void
{
    Cache::put((string) config('fcm.cache.key', 'fcm.access_token'), 'fake-token', now()->addMinutes(50));
}

it('skips notifications that do not implement HasFcmPayload', function (): void {
    Http::fake();

    app(FcmChannel::class)->send(new FakeFcmNotifiable, new PlainNotification);

    Http::assertNothingSent();
});

it('skips sending when fcm credentials are not configured', function (): void {
    config(['fcm.credentials' => null]);
    Http::fake();

    app(FcmChannel::class)->send(new FakeFcmNotifiable, new FakeFcmNotification);

    Http::assertNothingSent();
});

it('skips sending when the notifiable has no device tokens', function (): void {
    config(['fcm.credentials' => fakeFcmCredentials()]);
    Http::fake();

    app(FcmChannel::class)->send(new FakeFcmNotifiable([]), new FakeFcmNotification);

    Http::assertNothingSent();
});

it('sends the notification payload to every routed token', function (): void {
    config(['fcm.credentials' => fakeFcmCredentials()]);
    seedFakeFcmAccessTokenForChannel();

    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test-project/messages/1']),
    ]);

    app(FcmChannel::class)->send(new FakeFcmNotifiable(['token-a', 'token-b']), new FakeFcmNotification);

    Http::assertSentCount(2);
});

it('dispatches FcmTokenRejected and continues when a token is unregistered', function (): void {
    config(['fcm.credentials' => fakeFcmCredentials()]);
    seedFakeFcmAccessTokenForChannel();
    Event::fake([FcmTokenRejected::class]);

    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
    ]);

    $notifiable = new FakeFcmNotifiable(['dead-token']);

    app(FcmChannel::class)->send($notifiable, new FakeFcmNotification);

    Event::assertDispatched(FcmTokenRejected::class, fn (FcmTokenRejected $event): bool => $event->token === 'dead-token' && $event->notifiable === $notifiable
    );
});
