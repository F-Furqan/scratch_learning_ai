<?php

namespace App\Http\Middleware;

use App\Contracts\Access\AdminAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function __construct(
        private readonly AdminAccessService $adminAccess,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $this->adminAccess->canAccess($user), 403);

        return $next($request);
    }
}
