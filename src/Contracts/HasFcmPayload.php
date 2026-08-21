<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Contracts;

interface HasFcmPayload
{
    /**
     * @return array{title: string, body: string, data?: array<string, string>}
     */
    public function toFcm(object $notifiable): array;
}
