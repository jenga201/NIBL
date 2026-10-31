<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Collapses duplicate query-string keys and drops the legacy `by` sort param,
 * 301-redirecting to the canonical URL. The old site's sort links appended
 * `by=...&direction=...` on every click, producing enormous, duplicated query
 * strings that clutter logs (and can eventually hit request-line limits).
 */
final class NormalizeQueryString
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawQuery = $request->server->get('QUERY_STRING', '');

        if ($rawQuery === '') {
            return $next($request);
        }

        $canonical = $this->canonicalize($rawQuery);
        if ($canonical === $rawQuery) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        if ($canonical !== '') {
            $path .= '?'.$canonical;
        }

        return redirect($path, 301);
    }

    private function canonicalize(string $rawQuery): string
    {
        $seen = [];
        $parts = [];

        foreach (explode('&', $rawQuery) as $segment) {
            if ($segment === '') {
                continue;
            }

            $equals = strpos($segment, '=');
            if ($equals === false) {
                $key = $segment;
                $value = null;
            } else {
                $key = substr($segment, 0, $equals);
                $value = substr($segment, $equals + 1);
            }

            // `by` is the old site's sort param; the modern app uses `sort`.
            if ($key === 'by') {
                continue;
            }

            // Keep only the first occurrence of each key (collapses the
            // repeated `by=...&direction=...` pairs the old links produced).
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $parts[] = $value === null ? $key : $key.'='.$value;
        }

        return implode('&', $parts);
    }
}
