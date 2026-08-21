<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Notifications;

use AmdadulHaq\Fcm\Contracts\HasFcmPayload;
use AmdadulHaq\Fcm\Events\FcmTokenRejected;
use AmdadulHaq\Fcm\Exceptions\FcmUnregisteredTokenException;
use AmdadulHaq\Fcm\FcmService;
use AmdadulHaq\Fcm\Support\LogsFcmActivity;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Delivers notifications over Firebase Cloud Messaging.
 *
 * The notifiable must resolve one or more device tokens via
 * `routeNotificationForFcm()` (Laravel's standard `routeNotificationFor()`
 * convention). Rejected/unregistered tokens are reported through the
 * {@see FcmTokenRejected} event so the host application can prune them.
 */
class FcmChannel
{
    use LogsFcmActivity;

    public function __construct(
        private readonly FcmService $fcm,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! $notification instanceof HasFcmPayload) {
            return;
        }

        if (! $this->fcm->isConfigured()) {
            $this->log('warning', 'Skipped push notification: FCM credentials are not configured.');

            return;
        }

        $routed = $notifiable->routeNotificationFor('fcm', $notification);

        /** @var Collection<int, string> $tokens */
        $tokens = collect(is_array($routed) ? $routed : [$routed])->filter();

        if ($tokens->isEmpty()) {
            $this->log('debug', 'Skipped push notification: notifiable has no registered device tokens.');

            return;
        }

        $payload = $notification->toFcm($notifiable);

        foreach ($tokens as $token) {
            try {
                $this->fcm->sendToToken($token, $payload['title'], $payload['body'], $payload['data'] ?? []);
            } catch (FcmUnregisteredTokenException) {
                Event::dispatch(new FcmTokenRejected($token, $notifiable));
            } catch (Throwable $exception) {
                $this->log('warning', 'Failed to send push notification.', ['exception' => $exception->getMessage()]);
            }
        }
    }
}
