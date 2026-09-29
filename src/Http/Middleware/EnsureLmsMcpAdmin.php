<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLmsMcpAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! method_exists($user, 'isLmsAdmin') || ! $user->isLmsAdmin()) {
            abort(403, 'LMS MCP access is limited to LMS admins.');
        }

        return $next($request);
    }
}
