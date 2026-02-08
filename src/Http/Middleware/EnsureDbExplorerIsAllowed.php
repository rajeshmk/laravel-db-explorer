<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDbExplorerIsAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('db-explorer.enabled')) {
            abort(404);
        }

        if (! app()->environment(config('db-explorer.allowed_environments', []))) {
            abort(404);
        }

        return $next($request);
    }
}
