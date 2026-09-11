<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm;

use AmdadulHaq\Fcm\Exceptions\FcmUnregisteredTokenException;
use AmdadulHaq\Fcm\Support\LogsFcmActivity;
use AmdadulHaq\Fcm\Testing\FcmFake;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FcmService
{
    use LogsFcmActivity;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $credentials = null;

    /**
     * Swap the bound FcmService instance for a fake that records every
     * would-be send instead of performing it, for use in tests.
     */
    public static function fake(): FcmFake
    {
        $fake = new FcmFake;

        App::instance(self::class, $fake);

        return $fake;
    }

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * @param  array<string, string>  $data
     * @param  'high'|'normal'|null  $androidPriority  Overrides `config('fcm.android.priority')` for this message only.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], ?string $androidPriority = null): bool
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return false;
        }

        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        $android = $this->androidConfig($androidPriority);

        if ($android !== []) {
            $message['android'] = $android;
        }

        $apns = $this->apnsConfig();

        if ($apns !== []) {
            $message['apns'] = $apns;
        }

        if ($data !== []) {
            $message['data'] = $data;
        }

        if (config('fcm.dry_run')) {
            $this->log('info', 'FCM dry run: message not sent.', ['message' => $message]);

            return true;
        }

        $response = Http::withToken($this->accessToken())
            ->timeout((int) config('fcm.http.timeout', 10))
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => $message,
            ]);

        if ($response->successful()) {
            return true;
        }

        $status = $response->json('error.status');

        if (in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            $this->log('warning', 'FCM token rejected; removing it.', [
                'http_status' => $response->status(),
                'error_status' => $status,
                'error_message' => $response->json('error.message'),
            ]);

            throw new FcmUnregisteredTokenException($token);
        }

        $this->log('warning', 'FCM send failed.', [
            'http_status' => $response->status(),
            'error_status' => $status,
            'error_message' => $response->json('error.message'),
        ]);

        return false;
    }

    /**
     * @param  'high'|'normal'|null  $priorityOverride
     * @return array<string, mixed>
     */
    private function androidConfig(?string $priorityOverride = null): array
    {
        $priority = $priorityOverride ?? config('fcm.android.priority');
        $channelId = config('fcm.android.channel_id');

        if (blank($priority) && blank($channelId)) {
            return [];
        }

        $config = [];

        if (filled($priority)) {
            $config['priority'] = $priority;
        }

        if (filled($channelId)) {
            $config['notification'] = ['channel_id' => $channelId];
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function apnsConfig(): array
    {
        $priority = config('fcm.apns.priority');

        if (blank($priority)) {
            return [];
        }

        return [
            'headers' => [
                'apns-priority' => (string) $priority,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = config('fcm.credentials');

        if (blank($path) || ! is_string($path)) {
            return null;
        }

        $path = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            ? $path
            : base_path($path);

        if (! file_exists($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! isset($decoded['project_id'])) {
            return null;
        }

        return $this->credentials = $decoded;
    }

    private function accessToken(): string
    {
        return Cache::remember(
            (string) config('fcm.cache.key', 'fcm.access_token'),
            now()->addMinutes((int) config('fcm.cache.ttl', 50)),
            function (): string {
                $credentials = new ServiceAccountCredentials(
                    (string) config('fcm.scope'),
                    $this->credentials() ?? [],
                );

                $token = $credentials->fetchAuthToken();

                return $token['access_token'];
            }
        );
    }
}
