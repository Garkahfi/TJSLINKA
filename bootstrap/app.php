<?php

use App\Http\Middleware\EnsureAdminPasswordChanged;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin-pumk', 'admin-pumk/*', 'admin/monitoring', 'admin/monitoring/*')
                ? '/admin-pumk/login'
                : ($request->is('superadmin', 'superadmin/*')
                    ? '/superadmin/login'
                    : ($request->is('admin', 'admin/*') ? '/admin/login' : '/login'))
        );
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is('admin-pumk', 'admin-pumk/*', 'admin/monitoring', 'admin/monitoring/*')
                ? '/admin-pumk/home'
                : ($request->is('superadmin', 'superadmin/*')
                    ? '/superadmin/home'
                    : ($request->is('admin', 'admin/*') ? '/admin/home' : '/'))
        );
        $middleware->alias([
            'admin.password.changed' => EnsureAdminPasswordChanged::class,
            'role' => EnsureUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
