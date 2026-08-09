<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseResource;
use App\Services\Learning\LearningAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentResourceDownloadController extends Controller
{
    public function __invoke(Request $request, CourseResource $resource, LearningAccessService $access): JsonResponse|RedirectResponse|StreamedResponse
    {
        $resource->loadMissing(['course', 'lesson', 'mediaAsset']);

        abort_unless((bool) $resource->is_downloadable, 404);
        abort_unless($access->canAccessResource($request->user(), $resource), 403);

        $url = $resource->external_url ?: $resource->mediaAsset?->url;

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $resource->id,
                    'title' => $resource->title,
                    'url' => $url,
                ],
            ]);
        }

        if ($resource->external_url) {
            return redirect()->away($resource->external_url);
        }

        if ($resource->mediaAsset?->url) {
            return redirect()->to($resource->mediaAsset->url);
        }

        abort_unless($resource->file_path && Storage::disk('private')->exists($resource->file_path), 404);

        return Storage::disk('private')->download($resource->file_path, $resource->title);
    }
}
