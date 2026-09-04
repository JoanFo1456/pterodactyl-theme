<?php

namespace JoanFo\PterodactylUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JoanFo\PterodactylUi\Support\Paths;
use Symfony\Component\HttpFoundation\Response;

/**
 * Core sends X-Frame-Options: DENY on every response, which would stop the React app from
 * framing a Filament page contributed by another plugin.
 *
 * Setting the header here — before core's SetSecurityHeaders, which only fills in headers
 * that aren't already present — relaxes it to SAMEORIGIN. It only applies to requests that
 * asked to be embedded, so ordinary panel responses keep DENY.
 */
class AllowPanelEmbedding
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->boolean(Paths::embedParameter())) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }
}
