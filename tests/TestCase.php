<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature fixtures represent one account signed into the shared login.
     * Panel controllers still use their existing role-specific guard.
     */
    public function actingAs($user, $guard = null)
    {
        foreach (['web', 'admin', 'superadmin', 'pumk'] as $guardName) {
            Auth::guard($guardName)->logout();
        }
        Auth::forgetGuards();
        Auth::guard('web')->setUser($user);

        return parent::actingAs($user, $guard ?? 'web');
    }

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
