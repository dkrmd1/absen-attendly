<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrHr
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('admin.login');
        }

        abort_unless(
            $request->user()->is_active
            && ($request->user()->isAdmin() || $request->user()->isHr()),
            403,
        );

        return $next($request);
    }
}
