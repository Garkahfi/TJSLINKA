<?php

use App\Http\Middleware\EnsureAdminPasswordChanged;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\SynchronizeLoginSession;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
        $middleware->trustProxies(at: '*');

        $middleware->web(append: SynchronizeLoginSession::class);
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            SynchronizeLoginSession::class,
        );

        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('entry'));
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
