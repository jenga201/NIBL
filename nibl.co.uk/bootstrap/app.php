<?php

use App\Http\Middleware\SetHttpCacheHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            \App\Domain\SiteView::COOKIE,
            \App\Infrastructure\Service\LegacyThemeService::COOKIE,
        ]);

        $middleware->web(append: [
            SetHttpCacheHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The /nibl JSON API always answers with JSON error payloads, even
        // when the client sends no Accept header (curl, feed readers, ...).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api', 'api/*') || $request->expectsJson()
        );

        // Render any HTTP error without a dedicated errors/<code> view
        // through the generic error page (parity with the old Slim handler).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->expectsJson()
                && $response->getStatusCode() >= 400
                && ! view()->exists('errors.'.$response->getStatusCode())) {
                return response()->view(
                    'errors.error',
                    ['code' => $response->getStatusCode()],
                    $response->getStatusCode()
                );
            }

            return $response;
        });
    })->create();

// The persistent var/ volume (bind-mounted in Docker) doubles as Laravel's
// storage directory, so logs/sessions/compiled views survive redeploys.
$app->useStoragePath($app->basePath('var'));

return $app;
