<?php

declare(strict_types=1);

use AmdadulHaq\Fcm\Events\FcmTokenRejected;
use AmdadulHaq\Fcm\FcmService;
use AmdadulHaq\Fcm\Notifications\FcmChannel;
use AmdadulHaq\Fcm\Tests\Fixtures\FakeFcmNotifiable;
use AmdadulHaq\Fcm\Tests\Fixtures\FakeFcmNotification;
use AmdadulHaq\Fcm\Tests\Fixtures\FakeFcmPriorityNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\ExpectationFailedException;

it('records sends instead of hitting the network', function (): void {
    $fake = FcmService::fake();

    Http::fake();

    app(FcmChannel::class)->send(new FakeFcmNotifiable(['token-a']), new FakeFcmNotification);

    Http::assertNothingSent();
    $fake->assertSentTo('token-a', fn (string $title, string $body): bool => $title === 'Title' && $body === 'Body');
    $fake->assertSentCount(1);
});

it('records the per-message android priority override for assertions', function (): void {
    $fake = FcmService::fake();

    app(FcmChannel::class)->send(new FakeFcmNotifiable(['token-a']), new FakeFcmPriorityNotification);

    $fake->assertSentTo('token-a', fn (string $title, string $body, array $data, ?string $priority): bool => $priority === 'high');
});

it('asserts nothing was sent', function (): void {
    $fake = FcmService::fake();

    $fake->assertNothingSent();
});

it('fails assertNotSent when a matching push was sent', function (): void {
    $fake = FcmService::fake();

    $fake->sendToToken('token-a', 'Title', 'Body');

    expect(fn () => $fake->assertNotSent(fn (string $token): bool => $token === 'token-a'))
        ->toThrow(ExpectationFailedException::class);
});

it('simulates a rejected token so the fcm channel prunes it', function (): void {
    $fake = FcmService::fake();
    $fake->rejectToken('dead-token');
    Event::fake([FcmTokenRejected::class]);

    app(FcmChannel::class)->send(new FakeFcmNotifiable(['dead-token']), new FakeFcmNotification);

    $fake->assertNothingSent();
    Event::assertDispatched(FcmTokenRejected::class, fn (FcmTokenRejected $event): bool => $event->token === 'dead-token');
});
