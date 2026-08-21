<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Exceptions;

use RuntimeException;

class FcmUnregisteredTokenException extends RuntimeException
{
    public function __construct(
        public readonly string $token,
    ) {
        parent::__construct("FCM token is unregistered or invalid: {$token}");
    }
}
