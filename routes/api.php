<?php

use App\Http\Controllers\Ads\AdTrackingController;
use App\Http\Controllers\Api\V1\PublicBlogPostController;
use App\Http\Controllers\Api\V1\PublicCourseController;
use App\Http\Controllers\Api\V1\PublicCourseLessonController;
use App\Http\Controllers\Payments\PaddleWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/paddle', PaddleWebhookController::class)
    ->middleware('throttle:payment-webhooks')
    ->name('api.webhooks.paddle');

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware('throttle:public-api')
    ->group(function (): void {
        Route::get('courses', [PublicCourseController::class, 'index'])->name('courses.index');
        Route::get('courses/{course:slug}', [PublicCourseController::class, 'show'])->name('courses.show');

        Route::scopeBindings()->group(function (): void {
            Route::get('courses/{course:slug}/lessons/{lesson:slug}', [PublicCourseLessonController::class, 'show'])
                ->name('courses.lessons.show');
        });

        Route::get('blogs', [PublicBlogPostController::class, 'index'])->name('blogs.index');
        Route::get('blogs/{post:slug}', [PublicBlogPostController::class, 'show'])->name('blogs.show');

        Route::post('ads/impressions', [AdTrackingController::class, 'impression'])
            ->middleware('throttle:ad-tracking')
            ->name('ads.impressions.store');

        Route::get('ads/clicks/{creative}', [AdTrackingController::class, 'click'])
            ->middleware('throttle:ad-tracking')
            ->name('ads.clicks.store');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', function (Request $request) {
                $user = $request->user();

                return response()->json([
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'status' => $user->status->value,
                        'roles' => $user->getRoleNames()->values(),
                        'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                    ],
                ]);
            })->name('me');
        });
    });
