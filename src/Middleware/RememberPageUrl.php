<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the last full PAGE the user visited, for the reload of a routed
 * modal (`RoutedModal::redirectToRoutedModal()`).
 *
 * Laravel's `session()->previousUrl()` cannot serve that purpose: it is written
 * for every GET request regardless of the response, so a PDF or a download
 * opened in another tab would become the page a reloaded modal returns to.
 * Only a successful HTML response of a plain GET counts here — never a
 * Livewire request, a redirect, a file or download (any Content-Disposition)
 * or a `?modal=true` URL itself.
 */
class RememberPageUrl
{
    public const SESSION_KEY = 'noerd.page_url';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isPage($request, $response) && $request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $request->fullUrl());
        }

        return $response;
    }

    private function isPage(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && ! $request->hasHeader('X-Livewire')
            && ! $request->boolean('modal')
            && $response->isSuccessful()
            // A download without its own Content-Type is served as text/html;
            // its Content-Disposition gives it away (a page never sets one).
            && ! $response->headers->has('Content-Disposition')
            && str_starts_with(mb_strtolower((string) $response->headers->get('Content-Type')), 'text/html');
    }
}
