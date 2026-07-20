<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Page-expiry (CSRF token mismatch → 419): instead of the bare
        // "419 Page Expired" screen, send the user somewhere useful with a
        // friendly message — back to where they were if they still have a
        // session, otherwise the login page. JSON clients keep the 419.
        //
        // Must type-hint HttpException, NOT TokenMismatchException: Handler
        // ::prepareException() converts the latter into HttpException(419)
        // BEFORE render callbacks are matched (Handler.php:584 runs before
        // :586), so a TokenMismatchException-typed callback never fires.
        // We re-narrow to 419 here and return null for every other status
        // so all non-419 HttpExceptions fall through to default rendering.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $target = $request->user() ? url()->previous() : route('login');

            return redirect()->to($target)
                ->with('status', __('Your session expired — please try again.'));
        });
    })->create();
