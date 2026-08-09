<?php

namespace App\Http\Controllers;

use App\Services\Operations\PlatformHealthService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OperationalHealthController extends Controller
{
    public function __invoke(PlatformHealthService $health): JsonResponse
    {
        $report = $health->report();
        $status = $report['status'] === 'healthy'
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()
            ->json($report, $status)
            ->header('Cache-Control', 'no-store, private');
    }
}
