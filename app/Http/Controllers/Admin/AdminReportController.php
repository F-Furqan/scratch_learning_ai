<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\AdminReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly AdminReportService $reports,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/Reports', [
            'reports' => $this->reports->summary(
                $request->date('from'),
                $request->date('to'),
            ),
        ]);
    }
}
