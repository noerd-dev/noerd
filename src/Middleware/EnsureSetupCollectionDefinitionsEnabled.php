<?php

declare(strict_types=1);

namespace Noerd\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupCollectionDefinitionsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('noerd.collections.mode') === 'database', 404);

        return $next($request);
    }
}
