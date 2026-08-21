<?php

declare(strict_types=1);

namespace AmdadulHaq\Fcm\Tests;

use AmdadulHaq\Fcm\FcmServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FcmServiceProvider::class,
        ];
    }
}
