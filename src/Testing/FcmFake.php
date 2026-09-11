<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Testing;

use AmdadulHaq\Fcm\Exceptions\FcmUnregisteredTokenException;
use AmdadulHaq\Fcm\FcmService;
use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Swapped in for the real FcmService via FcmService::fake(), recording
 * every would-be send instead of dispatching it so tests can assert on
 * what would have been sent without touching the network.
 */
class FcmFake extends FcmService
{
    /**
     * @var array<int, array{token: string, title: string, body: string, data: array<string, string>, priority: string|null}>
     */
    private array $sent = [];

    /**
     * @var array<int, string>
     */
    private array $unregisteredTokens = [];

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @param  array<string, string>  $data
     * @param  'high'|'normal'|null  $androidPriority
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], ?string $androidPriority = null): bool
    {
        if (in_array($token, $this->unregisteredTokens, true)) {
            throw new FcmUnregisteredTokenException($token);
        }

        $this->sent[] = ['token' => $token, 'title' => $title, 'body' => $body, 'data' => $data, 'priority' => $androidPriority];

        return true;
    }

    /**
     * Make sends to the given token fail as unregistered, so tests can
     * exercise the pruning path without a real FCM error response.
     */
    public function rejectToken(string $token): self
    {
        $this->unregisteredTokens[] = $token;

        return $this;
    }

    /**
     * Assert a push notification matching the given truth test was sent.
     */
    public function assertSent(?Closure $callback = null, ?int $times = null): void
    {
        $matching = $this->matching($callback);

        if ($times !== null) {
            PHPUnit::assertCount($times, $matching, sprintf('The expected push notification was sent %d times instead of %d times.', $matching->count(), $times));

            return;
        }

        PHPUnit::assertTrue($matching->isNotEmpty(), 'The expected push notification was not sent.');
    }

    /**
     * Assert no push notification matching the given truth test was sent.
     */
    public function assertNotSent(?Closure $callback = null): void
    {
        PHPUnit::assertTrue($this->matching($callback)->isEmpty(), 'A push notification matching the given constraints was sent.');
    }

    /**
     * Assert a push notification was sent to the given device token.
     */
    public function assertSentTo(string $token, ?Closure $callback = null): void
    {
        $this->assertSent(fn (string $sentToken, string $title, string $body, array $data, ?string $priority): bool => $sentToken === $token && (! $callback instanceof Closure || $callback($title, $body, $data, $priority)));
    }

    /**
     * Assert no push notifications were sent at all.
     */
    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->sent, 'Push notifications were sent unexpectedly.');
    }

    /**
     * Assert the total number of push notifications sent.
     */
    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->sent, sprintf('Expected %d push notifications to be sent, %d were sent.', $count, $this->count()));
    }

    public function count(): int
    {
        return count($this->sent);
    }

    /**
     * @return Collection<int, array{token: string, title: string, body: string, data: array<string, string>, priority: string|null}>
     */
    private function matching(?Closure $callback): Collection
    {
        $callback ??= fn (): bool => true;

        return collect($this->sent)->filter(
            fn (array $push): bool => $callback($push['token'], $push['title'], $push['body'], $push['data'], $push['priority'])
        );
    }
}
