<?php

namespace App\Http\Controllers\Blogger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BloggerPendingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('blogger/Pending', [
            'status' => $request->user()->bloggerProfile?->status->value ?? 'pending',
        ]);
    }
}
