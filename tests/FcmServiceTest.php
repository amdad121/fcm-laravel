<?php

declare(strict_types=1);

use AmdadulHaq\Fcm\Exceptions\FcmUnregisteredTokenException;
use AmdadulHaq\Fcm\FcmService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function fakeCredentialsPath(): string
{
    $path = sys_get_temp_dir().'/fcm-test-credentials-'.uniqid().'.json';

    file_put_contents($path, json_encode(['project_id' => 'test-project']));

    return $path;
}

/**
 * Seeds the cached OAuth access token so sendToToken() never has to reach
 * Google's token endpoint, which google/auth calls with its own HTTP
 * client (not Laravel's), so Http::fake() cannot intercept it.
 */
function seedFakeFcmAccessToken(): void
{
    Cache::put((string) config('fcm.cache.key', 'fcm.access_token'), 'fake-token', now()->addMinutes(50));
}

it('is not configured when no credentials path is set', function (): void {
    config(['fcm.credentials' => null]);

    expect(app(FcmService::class)->isConfigured())->toBeFalse();
});

it('is not configured when the credentials file does not exist', function (): void {
    config(['fcm.credentials' => '/no/such/file.json']);

    expect(app(FcmService::class)->isConfigured())->toBeFalse();
});

it('is configured when a valid credentials file is set', function (): void {
    config(['fcm.credentials' => fakeCredentialsPath()]);

    expect(app(FcmService::class)->isConfigured())->toBeTrue();
});

it('returns false when sending without configured credentials', function (): void {
    config(['fcm.credentials' => null]);

    expect(app(FcmService::class)->sendToToken('token', 'Title', 'Body'))->toBeFalse();
});

it('sends a message to the fcm v1 endpoint with the configured android options', function (): void {
    config([
        'fcm.credentials' => fakeCredentialsPath(),
        'fcm.android.priority' => 'high',
        'fcm.android.channel_id' => 'custom_channel',
    ]);
    seedFakeFcmAccessToken();

    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test-project/messages/1']),
    ]);

    $result = app(FcmService::class)->sendToToken('device-token', 'Title', 'Body', ['key' => 'value']);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request): bool {
        $message = $request->data()['message'];

        return $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
            && $request->hasHeader('Authorization', 'Bearer fake-token')
            && $message['token'] === 'device-token'
            && $message['notification'] === ['title' => 'Title', 'body' => 'Body']
            && $message['android'] === ['priority' => 'high', 'notification' => ['channel_id' => 'custom_channel']]
            && $message['data'] === ['key' => 'value'];
    });
});

it('omits the android block when priority and channel_id are both unset', function (): void {
    config([
        'fcm.credentials' => fakeCredentialsPath(),
        'fcm.android.priority' => null,
        'fcm.android.channel_id' => null,
    ]);
    seedFakeFcmAccessToken();

    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

    app(FcmService::class)->sendToToken('device-token', 'Title', 'Body');

    Http::assertSent(fn ($request): bool => ! array_key_exists('android', $request->data()['message']));
});

it('applies the configured apns priority header', function (): void {
    config([
        'fcm.credentials' => fakeCredentialsPath(),
        'fcm.apns.priority' => '5',
    ]);
    seedFakeFcmAccessToken();

    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

    app(FcmService::class)->sendToToken('device-token', 'Title', 'Body');

    Http::assertSent(fn ($request): bool => $request->data()['message']['apns'] === ['headers' => ['apns-priority' => '5']]);
});

it('omits the apns block when no priority is configured', function (): void {
    config(['fcm.credentials' => fakeCredentialsPath(), 'fcm.apns.priority' => null]);
    seedFakeFcmAccessToken();

    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

    app(FcmService::class)->sendToToken('device-token', 'Title', 'Body');

    Http::assertSent(fn ($request): bool => ! array_key_exists('apns', $request->data()['message']));
});

it('throws for an unregistered token so the caller can prune it', function (): void {
    config(['fcm.credentials' => fakeCredentialsPath()]);
    seedFakeFcmAccessToken();

    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED', 'message' => 'gone']], 404),
    ]);

    app(FcmService::class)->sendToToken('dead-token', 'Title', 'Body');
})->throws(FcmUnregisteredTokenException::class);

it('returns false without throwing for other fcm errors', function (): void {
    config(['fcm.credentials' => fakeCredentialsPath()]);
    seedFakeFcmAccessToken();

    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'INTERNAL', 'message' => 'oops']], 500),
    ]);

    expect(app(FcmService::class)->sendToToken('token', 'Title', 'Body'))->toBeFalse();
});

it('does not call the fcm api in dry run mode', function (): void {
    config([
        'fcm.credentials' => fakeCredentialsPath(),
        'fcm.dry_run' => true,
    ]);
    seedFakeFcmAccessToken();

    Http::fake();

    $result = app(FcmService::class)->sendToToken('token', 'Title', 'Body');

    expect($result)->toBeTrue();
    Http::assertNothingSent();
});
