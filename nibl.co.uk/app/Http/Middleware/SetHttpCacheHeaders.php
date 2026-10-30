<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies a safe caching policy to web (HTML) responses.
 *
 * HTML now varies by cookie (the legacy site-view toggle and the legacy color
 * theme), so it must be revalidated on every request — a long-lived public
 * cache would keep serving the wrong template. Redirects are never cached.
 */
final class SetHttpCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->setVary('Cookie', false);

        if ($response->isRedirection()) {
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        }

        $response->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');

        return $response;
    }
}
