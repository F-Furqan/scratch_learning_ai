<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionName;
use App\Enums\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Course;
use App\Services\Admin\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTrashController extends Controller
{
    public function restore(Request $request, string $type, int $id, AuditLogger $auditLogger): RedirectResponse
    {
        $content = $this->trashedContent($request, $type, $id);
        $before = $content->toArray();

        DB::transaction(function () use ($request, $content, $before, $auditLogger, $type): void {
            if ($content instanceof Course || $content instanceof BlogPost) {
                $content->restore();
                $content->forceFill([
                    'status' => PublishStatus::Published,
                    'published_at' => $content->getAttribute('published_at') ?: now(),
                    'rejection_reason' => null,
                ])->save();
            }

            $auditLogger->log(
                $request,
                'admin.trash.restored',
                $content,
                $before,
                $content->fresh()?->toArray(),
                ['content_type' => $type],
            );
        });

        return back()->with('success', 'Content restored from trash.');
    }

    public function destroy(Request $request, string $type, int $id, AuditLogger $auditLogger): RedirectResponse
    {
        $content = $this->trashedContent($request, $type, $id);
        $before = $content->toArray();

        DB::transaction(function () use ($request, $content, $before, $auditLogger, $type): void {
            $auditLogger->log(
                $request,
                'admin.trash.force_deleted',
                $content,
                $before,
                null,
                ['content_type' => $type],
            );

            if ($content instanceof Course || $content instanceof BlogPost) {
                $content->forceDelete();
            }
        });

        return back()->with('success', 'Content permanently deleted.');
    }

    private function trashedContent(Request $request, string $type, int $id): Model
    {
        return match ($type) {
            'course' => $this->course($request, $id),
            'blog' => $this->blog($request, $id),
            default => abort(404),
        };
    }

    private function course(Request $request, int $id): Course
    {
        abort_unless($request->user()?->can(PermissionName::ManageCourses->value), 403);

        return Course::onlyTrashed()->whereKey($id)->firstOrFail();
    }

    private function blog(Request $request, int $id): BlogPost
    {
        abort_unless($request->user()?->can(PermissionName::ManageBlogs->value), 403);

        return BlogPost::onlyTrashed()->whereKey($id)->firstOrFail();
    }
}
