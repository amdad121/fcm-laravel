<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Tests\Fixtures;

use AmdadulHaq\Fcm\Contracts\HasFcmPayload;
use Illuminate\Notifications\Notification;

class FakeFcmNotification extends Notification implements HasFcmPayload
{
    /**
     * @return array{title: string, body: string, data?: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        return ['title' => 'Title', 'body' => 'Body', 'data' => ['k' => 'v']];
    }
}
