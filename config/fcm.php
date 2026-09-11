<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Service Account Credentials
    |--------------------------------------------------------------------------
    |
    | Absolute or app-relative path to the Firebase service account JSON
    | file used to obtain OAuth access tokens for the FCM HTTP v1 API.
    | The Firebase project ID is read from this file automatically.
    |
    */

    'credentials' => env('FIREBASE_CREDENTIALS'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Scope
    |--------------------------------------------------------------------------
    */

    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',

    /*
    |--------------------------------------------------------------------------
    | Access Token Cache
    |--------------------------------------------------------------------------
    |
    | The fetched OAuth access token is cached to avoid a round-trip to
    | Google on every push. Google issues tokens valid for 60 minutes;
    | keep this comfortably under that.
    |
    */

    'cache' => [
        'key' => 'fcm.access_token',
        'ttl' => 50, // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => env('FCM_HTTP_TIMEOUT', 10), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Android Delivery Options
    |--------------------------------------------------------------------------
    |
    | Applied to every outgoing message's `android` payload. Set
    | `channel_id` to null to omit it from the payload. `priority` can be
    | overridden per notification via `HasFcmPayload::toFcm()`.
    |
    */

    'android' => [
        'priority' => env('FCM_ANDROID_PRIORITY', 'high'),
        'channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'default_channel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | APNs (iOS) Delivery Options
    |--------------------------------------------------------------------------
    |
    | Applied to every outgoing message's `apns.headers` payload. Set
    | `priority` to null to omit the whole `apns` block.
    |
    */

    'apns' => [
        'priority' => env('FCM_APNS_PRIORITY'), // '5' or '10'
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, delivery failures, rejected tokens, and skipped sends
    | (missing credentials, no device tokens) are logged. This is
    | independent of the host application's own `app.debug` setting,
    | since this package may be installed in apps that keep debug off
    | in every environment.
    |
    */

    'debug' => (bool) env('FCM_DEBUG', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The channel debug logs are written to. Leave null to use the
    | application's default log channel.
    |
    */

    'log_channel' => env('FCM_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Dry Run
    |--------------------------------------------------------------------------
    |
    | When enabled, messages are built and logged as usual but the HTTP
    | request to FCM is never made; `sendToToken()` returns true as if
    | delivery succeeded. Useful for local development and staging
    | environments that shouldn't push to real devices.
    |
    */

    'dry_run' => (bool) env('FCM_DRY_RUN', false),

];
