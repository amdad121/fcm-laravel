<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Tests\Fixtures;

class FakeFcmNotifiable
{
    /**
     * @param  array<int, string>  $tokens
     */
    public function __construct(private readonly array $tokens = ['token-a']) {}

    /**
     * @return array<int, string>
     */
    public function routeNotificationFor(string $driver): array
    {
        return $this->tokens;
    }
}
