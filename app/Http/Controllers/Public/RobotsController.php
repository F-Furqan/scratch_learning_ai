<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /student',
            'Disallow: /creator',
            'Disallow: /blogger/dashboard',
            'Disallow: /settings',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
