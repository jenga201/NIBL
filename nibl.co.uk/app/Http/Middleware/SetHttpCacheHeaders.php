<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies a safe caching policy to web (HTML) responses.
 *
 * HTML varies by cookie (site-view, JS mode, color theme, fuzzy preference),
 * so it must be revalidated on every request. An ETag lets the browser send a
 * conditional request and receive a 304 when the rendered page hasn't changed,
 * skipping the body re-transfer without ever serving stale markup.
 */
final class SetHttpCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Livewire pages set their own "don't cache" policy (back/forward-cache
        // safety) via DisableBackButtonCacheMiddleware. Adding an ETag and a
        // `public` directive here would create a contradictory Cache-Control,
        // so leave those responses untouched.
        $content = $response->getContent();
        if (is_string($content) && str_contains($content, 'wire:snapshot')) {
            return $response;
        }

        $response->setVary('Cookie', false);

        if ($response->isRedirection()) {
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        }

        if ($request->isMethodCacheable()
            && $response->isSuccessful()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $content = $response->getContent();

            if (is_string($content) && $content !== '') {
                $etag = '"'.md5($content).'"';

                $response->setEtag($etag);

                if ($request->headers->get('If-None-Match') === $etag) {
                    return $response->setNotModified();
                }
            }
        }

        $response->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');

        return $response;
    }
}
