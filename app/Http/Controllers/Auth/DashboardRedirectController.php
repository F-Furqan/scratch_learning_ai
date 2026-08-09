<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Navigation\DashboardDestinationResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __construct(
        private readonly DashboardDestinationResolver $destinations,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        return redirect($this->destinations->pathFor($request->user()));
    }
}
