<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Support;

use Illuminate\Support\Facades\Log;

/**
 * Shared debug-logging behavior for FcmService and FcmChannel. Gated by
 * `fcm.debug` rather than the host app's `app.debug`, since this package
 * may run inside apps that keep debug off in every environment.
 */
trait LogsFcmActivity
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (! config('fcm.debug')) {
            return;
        }

        $channel = config('fcm.log_channel');

        $logger = filled($channel) ? Log::channel((string) $channel) : Log::getFacadeRoot();

        $logger->log($level, $message, $context);
    }
}
