<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Events;

/**
 * Fired when Firebase reports a device token as unregistered or invalid,
 * so the host application can prune it from wherever it is stored.
 */
class FcmTokenRejected
{
    public function __construct(
        public readonly string $token,
        public readonly mixed $notifiable,
    ) {}
}
