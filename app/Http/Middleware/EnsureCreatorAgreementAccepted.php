<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Services\Creators\CreatorAgreementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCreatorAgreementAccepted
{
    public function __construct(
        private readonly CreatorAgreementService $agreements,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->hasRole(RoleName::Blogger->value), 403);

        if (! $this->agreements->hasAccepted($user)) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('creator.agreement.show');
        }

        return $next($request);
    }
}
