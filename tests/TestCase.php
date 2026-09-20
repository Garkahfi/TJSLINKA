<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the application and stop the test suite before database-refresh
     * traits run when the resolved connection is not the isolated SQLite DB.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || $connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Pengujian dibatalkan untuk melindungi data: APP_ENV=%s, DB_CONNECTION=%s, DB_DATABASE=%s. Test wajib memakai testing + sqlite + :memory:.',
                $app->environment(),
                (string) $connection,
                (string) $database,
            ));
        }

        return $app;
    }
}
