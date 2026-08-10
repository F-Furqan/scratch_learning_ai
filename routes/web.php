<?php

use App\Http\Controllers\Admin\AdminBlogWorkflowController;
use App\Http\Controllers\Admin\AdminCourseWorkflowController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminReviewCenterController;
use App\Http\Controllers\Admin\AdminTrashController;
use App\Http\Controllers\Auth\DashboardRedirectController;
use App\Http\Controllers\Auth\RegistrationPathController;
use App\Http\Controllers\Blogger\BloggerPendingController;
use App\Http\Controllers\Creator\CreatorAgreementController;
use App\Http\Controllers\Creator\CreatorBlogController;
use App\Http\Controllers\Creator\CreatorCourseController;
use App\Http\Controllers\Creator\CreatorDashboardController;
use App\Http\Controllers\Creator\CreatorGuidelinesController;
use App\Http\Controllers\Creator\CreatorNotificationController;
use App\Http\Controllers\Creator\CreatorProfileController;
use App\Http\Controllers\Growth\AbExperimentController;
use App\Http\Controllers\Growth\AffiliateRedirectController;
use App\Http\Controllers\Growth\CheckoutRecoveryController;
use App\Http\Controllers\Growth\LeadMagnetSubmissionController;
use App\Http\Controllers\OperationalHealthController;
use App\Http\Controllers\Payments\PaymentCheckoutController;
use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\BloggerProfileController;
use App\Http\Controllers\Public\CertificateVerificationController;
use App\Http\Controllers\Public\CommunityForumController;
use App\Http\Controllers\Public\CommunityThreadController;
use App\Http\Controllers\Public\CopyrightTakedownController;
use App\Http\Controllers\Public\CourseCatalogController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\InstructorProfileController;
use App\Http\Controllers\Public\LessonController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Student\CommunityReactionController;
use App\Http\Controllers\Student\ContentReportController;
use App\Http\Controllers\Student\StudentAssignmentSubmissionController;
use App\Http\Controllers\Student\StudentCommunityGroupController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\StudentDiscussionController;
use App\Http\Controllers\Student\StudentLessonBookmarkController;
use App\Http\Controllers\Student\StudentLessonNoteController;
use App\Http\Controllers\Student\StudentLessonProgressController;
use App\Http\Controllers\Student\StudentLessonQuestionAnswerController;
use App\Http\Controllers\Student\StudentLessonQuestionController;
use App\Http\Controllers\Student\StudentQuizAttemptController;
use App\Http\Controllers\Student\StudentResourceDownloadController;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Enums\AdminDomain;
use Modules\Admin\Http\Controllers\AdminCourseBuilderController;
use Modules\Admin\Http\Controllers\AdminMenuBuilderController;
use Modules\Admin\Http\Controllers\CatalogAdminController;
use Modules\Admin\Http\Controllers\CommerceAdminController;
use Modules\Admin\Http\Controllers\CommunityAdminController;
use Modules\Admin\Http\Controllers\EditorialAdminController;
use Modules\Admin\Http\Controllers\EditorialRevisionReviewController;
use Modules\Admin\Http\Controllers\GrowthAdminController;
use Modules\Admin\Http\Controllers\LearningAdminController;
use Modules\Admin\Http\Controllers\LearningRecordReviewController;
use Modules\Admin\Http\Controllers\OperationalRecordActionController;
use Modules\Admin\Http\Controllers\OperationalRecordExportController;
use Modules\Admin\Http\Controllers\OperationsAdminController;
use Modules\Admin\Http\Controllers\OperationsCenterController;
use Modules\Admin\Http\Requests\Learning\AbstractLearningRecordRequest;
use Modules\Admin\Http\Requests\Operations\AbstractOperationalRecordRequest;
use Modules\Admin\Registry\AdminResourceRegistry;
use Modules\Admin\Services\AdminCourseBuilderService;
use Modules\Admin\Services\CommerceOperationsAdminService;
use Modules\Admin\Services\CommunityOperationsAdminService;
use Modules\Admin\Services\CourseCatalogAdminService;
use Modules\Admin\Services\EditorialCmsAdminService;
use Modules\Admin\Services\OperationsCenterAdminService;

Route::get('health', OperationalHealthController::class)->name('health');

Route::middleware('throttle:public-content')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('courses', [CourseCatalogController::class, 'index'])->name('public.courses.index');
    Route::get('course-watch', [LessonController::class, 'first'])->name('public.lessons.watch');
    Route::get('courses/{course}', [CourseCatalogController::class, 'show'])->name('public.courses.show');
    Route::get('courses/{course}/lessons/{lesson}', [LessonController::class, 'show'])->name('public.lessons.show');
    Route::get('blog', [BlogController::class, 'index'])->name('public.blog.index');
    Route::get('blog/{post}', [BlogController::class, 'show'])->name('public.blog.show');
    Route::get('bloggers', [BloggerProfileController::class, 'index'])->name('public.bloggers.index');
    Route::get('bloggers/{user}', [BloggerProfileController::class, 'show'])->whereNumber('user')->name('public.bloggers.show');
    Route::get('instructors', [InstructorProfileController::class, 'index'])->name('public.instructors.index');
    Route::get('instructors/{instructor}', [InstructorProfileController::class, 'show'])->name('public.instructors.show');
    Route::get('community/forums', [CommunityForumController::class, 'index'])->name('public.community.forums.index');
    Route::get('community/forums/{forum}', [CommunityForumController::class, 'show'])->whereNumber('forum')->name('public.community.forums.show');
    Route::get('community/threads/{thread}', [CommunityThreadController::class, 'show'])->whereNumber('thread')->name('public.community.threads.show');
    Route::get('about-us', [PageController::class, 'show'])->defaults('page', 'about')->name('public.pages.about');
    Route::get('about', [PageController::class, 'show'])->defaults('page', 'about')->name('public.pages.about.short');
    Route::get('contact-us', [PageController::class, 'show'])->defaults('page', 'contact')->name('public.pages.contact');
    Route::get('contact', [PageController::class, 'show'])->defaults('page', 'contact')->name('public.pages.contact.short');
    Route::get('pricing-plan', [PageController::class, 'show'])->defaults('page', 'pricing')->name('public.pages.pricing');
    Route::get('pricing', [PageController::class, 'show'])->defaults('page', 'pricing')->name('public.pages.pricing.short');
    Route::get('faq', [PageController::class, 'show'])->defaults('page', 'faq')->name('public.pages.faq');
    Route::get('become-an-instructor', [PageController::class, 'show'])->defaults('page', 'become-an-instructor')->name('public.pages.become-instructor');
    Route::get('testimonials', [PageController::class, 'show'])->defaults('page', 'testimonials')->name('public.pages.testimonials');
    Route::get('terms-and-conditions', [PageController::class, 'show'])->defaults('page', 'terms-and-conditions')->name('public.pages.terms');
    Route::get('privacy-policy', [PageController::class, 'show'])->defaults('page', 'privacy-policy')->name('public.pages.privacy');
    Route::get('cart', [PageController::class, 'show'])->defaults('page', 'cart')->name('public.pages.cart');
    Route::get('checkout', [PageController::class, 'show'])->defaults('page', 'checkout')->name('public.pages.checkout');
    Route::redirect('course-grid', '/courses')->name('public.template.course-grid');
    Route::redirect('course-list', '/courses')->name('public.template.course-list');
    Route::redirect('course-category', '/courses')->name('public.template.course-category');
    Route::redirect('course-category-2', '/courses')->name('public.template.course-category-2');
    Route::redirect('course-category-3', '/courses')->name('public.template.course-category-3');
    Route::redirect('blog-grid', '/blog')->name('public.template.blog-grid');
    Route::redirect('blog-2-grid', '/blog')->name('public.template.blog-2-grid');
    Route::redirect('blog-3-grid', '/blog')->name('public.template.blog-3-grid');
    Route::redirect('blog-masonry', '/blog')->name('public.template.blog-masonry');
    Route::redirect('blog-left-sidebar', '/blog')->name('public.template.blog-left-sidebar');
    Route::redirect('blog-right-sidebar', '/blog')->name('public.template.blog-right-sidebar');
    Route::redirect('blog-carousal', '/blog')->name('public.template.blog-carousal');
    Route::redirect('instructor-grid', '/instructors')->name('public.template.instructor-grid');
    Route::redirect('instructor-list', '/instructors')->name('public.template.instructor-list');
    Route::get('pages/{page}', [PageController::class, 'show'])->name('public.pages.show');
    Route::get('certificates/verify/{certificate:verification_code}', CertificateVerificationController::class)->name('certificates.verify');
    Route::get('r/{code}', AffiliateRedirectController::class)->name('growth.affiliate.redirect');
    Route::get('growth/experiments/{experiment:key}', [AbExperimentController::class, 'show'])->name('growth.experiments.show');
    Route::post('lead-magnets/{leadMagnet:slug}/submissions', [LeadMagnetSubmissionController::class, 'store'])->name('growth.lead-magnets.submissions.store');
    Route::get('checkout/recover/{recovery:recovery_token}', CheckoutRecoveryController::class)->name('checkout.recoveries.show');
    Route::get('copyright/takedown', [CopyrightTakedownController::class, 'show'])->name('copyright.takedown.show');
    Route::post('copyright/takedown', [CopyrightTakedownController::class, 'store'])->name('copyright.takedown.store');
    Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('robots.txt', RobotsController::class)->name('robots');
});

Route::get('admin/login', AdminLoginController::class)
    ->middleware('guest')
    ->name('admin.login');

Route::get('register/student', RegistrationPathController::class)
    ->defaults('accountType', 'student')
    ->middleware('guest')
    ->name('register.student');

Route::get('register/creator', RegistrationPathController::class)
    ->defaults('accountType', 'creator')
    ->middleware('guest')
    ->name('register.creator');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::post('checkout/prices/{price}', [PaymentCheckoutController::class, 'store'])
        ->whereNumber('price')
        ->name('checkout.prices.store');

    Route::post('checkout/courses/{course:slug}', [PaymentCheckoutController::class, 'course'])
        ->name('checkout.courses.store');

    Route::get('student/dashboard', StudentDashboardController::class)
        ->middleware('student')
        ->name('student.dashboard');

    Route::prefix('student')
        ->name('student.')
        ->middleware('student')
        ->group(function (): void {
            Route::post('lessons/{lesson}/progress', [StudentLessonProgressController::class, 'store'])
                ->whereNumber('lesson')
                ->name('lessons.progress.store');

            Route::post('lessons/{lesson}/notes', [StudentLessonNoteController::class, 'store'])
                ->whereNumber('lesson')
                ->name('lessons.notes.store');

            Route::delete('notes/{note}', [StudentLessonNoteController::class, 'destroy'])
                ->whereNumber('note')
                ->name('notes.destroy');

            Route::post('lessons/{lesson}/bookmarks', [StudentLessonBookmarkController::class, 'store'])
                ->whereNumber('lesson')
                ->name('lessons.bookmarks.store');

            Route::get('resources/{resource}/download', StudentResourceDownloadController::class)
                ->whereNumber('resource')
                ->name('resources.download');

            Route::post('quizzes/{quiz}/attempts', [StudentQuizAttemptController::class, 'store'])
                ->whereNumber('quiz')
                ->name('quizzes.attempts.store');

            Route::post('assignments/{assignment}/submissions', [StudentAssignmentSubmissionController::class, 'store'])
                ->whereNumber('assignment')
                ->name('assignments.submissions.store');

            Route::post('lessons/{lesson}/questions', [StudentLessonQuestionController::class, 'store'])
                ->whereNumber('lesson')
                ->name('lessons.questions.store');

            Route::post('questions/{question}/answers', [StudentLessonQuestionAnswerController::class, 'store'])
                ->whereNumber('question')
                ->name('questions.answers.store');

            Route::post('questions/{question}/answers/{answer}/accept', [StudentLessonQuestionAnswerController::class, 'accept'])
                ->whereNumber(['question', 'answer'])
                ->name('questions.answers.accept');

            Route::get('community/groups', [StudentCommunityGroupController::class, 'index'])
                ->name('community.groups.index');

            Route::get('community/groups/{group}', [StudentCommunityGroupController::class, 'show'])
                ->whereNumber('group')
                ->name('community.groups.show');

            Route::post('community/groups/{group}/join', [StudentCommunityGroupController::class, 'join'])
                ->whereNumber('group')
                ->name('community.groups.join');

            Route::post('community/forums/{forum}/threads', [StudentDiscussionController::class, 'thread'])
                ->whereNumber('forum')
                ->name('community.forums.threads.store');

            Route::post('community/threads/{thread}/posts', [StudentDiscussionController::class, 'post'])
                ->whereNumber('thread')
                ->name('community.threads.posts.store');

            Route::post('community/answers/{answer}/reactions', [CommunityReactionController::class, 'answer'])
                ->whereNumber('answer')
                ->name('community.answers.reactions.store');

            Route::post('community/threads/{thread}/reactions', [CommunityReactionController::class, 'thread'])
                ->whereNumber('thread')
                ->name('community.threads.reactions.store');

            Route::post('community/posts/{post}/reactions', [CommunityReactionController::class, 'post'])
                ->whereNumber('post')
                ->name('community.posts.reactions.store');

            Route::post('community/questions/{question}/reports', [ContentReportController::class, 'question'])
                ->whereNumber('question')
                ->name('community.questions.reports.store');

            Route::post('community/answers/{answer}/reports', [ContentReportController::class, 'answer'])
                ->whereNumber('answer')
                ->name('community.answers.reports.store');

            Route::post('community/threads/{thread}/reports', [ContentReportController::class, 'thread'])
                ->whereNumber('thread')
                ->name('community.threads.reports.store');

            Route::post('community/posts/{post}/reports', [ContentReportController::class, 'post'])
                ->whereNumber('post')
                ->name('community.posts.reports.store');
        });

    Route::prefix('creator')
        ->name('creator.')
        ->middleware('role:blogger')
        ->group(function (): void {
            Route::get('dashboard', CreatorDashboardController::class)->name('dashboard');
            Route::get('pending', BloggerPendingController::class)->name('pending');
            Route::get('guidelines', CreatorGuidelinesController::class)->name('guidelines');
            Route::patch('notifications/{notification}/read', [CreatorNotificationController::class, 'markRead'])->name('notifications.read');
            Route::patch('notifications/read-all', [CreatorNotificationController::class, 'markAllRead'])->name('notifications.read-all');

            Route::get('agreement', [CreatorAgreementController::class, 'show'])->name('agreement.show');
            Route::post('agreement', [CreatorAgreementController::class, 'store'])->name('agreement.store');

            Route::get('profile', [CreatorProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('profile', [CreatorProfileController::class, 'update'])->name('profile.update');

            Route::middleware('creator.agreement')->group(function (): void {
                Route::get('blogs', [CreatorBlogController::class, 'index'])->name('blogs.index');
                Route::get('blogs/create', [CreatorBlogController::class, 'create'])->name('blogs.create');
                Route::post('blogs', [CreatorBlogController::class, 'store'])->name('blogs.store');
                Route::get('blogs/{post}/edit', [CreatorBlogController::class, 'edit'])->whereNumber('post')->name('blogs.edit');
                Route::patch('blogs/{post}', [CreatorBlogController::class, 'update'])->whereNumber('post')->name('blogs.update');
                Route::post('blogs/{post}/submit', [CreatorBlogController::class, 'submit'])->whereNumber('post')->name('blogs.submit');
                Route::post('blogs/{post}/delete-request', [CreatorBlogController::class, 'requestDelete'])->whereNumber('post')->name('blogs.delete-request');

                Route::get('courses', [CreatorCourseController::class, 'index'])->name('courses.index');
                Route::get('courses/create', [CreatorCourseController::class, 'create'])->name('courses.create');
                Route::post('courses', [CreatorCourseController::class, 'store'])->name('courses.store');
                Route::get('courses/{course}/edit', [CreatorCourseController::class, 'edit'])->whereNumber('course')->name('courses.edit');
                Route::patch('courses/{course}', [CreatorCourseController::class, 'update'])->whereNumber('course')->name('courses.update');
                Route::patch('courses/{course}/ownership', [CreatorCourseController::class, 'updateOwnership'])->whereNumber('course')->name('courses.ownership.update');
                Route::post('courses/{course}/submit', [CreatorCourseController::class, 'submit'])->whereNumber('course')->name('courses.submit');
                Route::post('courses/{course}/delete-request', [CreatorCourseController::class, 'requestDelete'])->whereNumber('course')->name('courses.delete-request');
                Route::post('courses/{course}/sections', [CreatorCourseController::class, 'storeSection'])->whereNumber('course')->name('courses.sections.store');
                Route::post('courses/{course}/lessons', [CreatorCourseController::class, 'storeLesson'])->whereNumber('course')->name('courses.lessons.store');
                Route::post('courses/{course}/resources', [CreatorCourseController::class, 'storeResource'])->whereNumber('course')->name('courses.resources.store');
                Route::post('courses/{course}/faqs', [CreatorCourseController::class, 'storeFaq'])->whereNumber('course')->name('courses.faqs.store');
                Route::patch('course-sections/{section}', [CreatorCourseController::class, 'updateSection'])->whereNumber('section')->name('course-sections.update');
                Route::delete('course-sections/{section}', [CreatorCourseController::class, 'destroySection'])->whereNumber('section')->name('course-sections.destroy');
                Route::patch('course-lessons/{lesson}', [CreatorCourseController::class, 'updateLesson'])->whereNumber('lesson')->name('course-lessons.update');
                Route::delete('course-lessons/{lesson}', [CreatorCourseController::class, 'destroyLesson'])->whereNumber('lesson')->name('course-lessons.destroy');
                Route::patch('course-resources/{resource}', [CreatorCourseController::class, 'updateResource'])->whereNumber('resource')->name('course-resources.update');
                Route::delete('course-resources/{resource}', [CreatorCourseController::class, 'destroyResource'])->whereNumber('resource')->name('course-resources.destroy');
                Route::patch('course-faqs/{faq}', [CreatorCourseController::class, 'updateFaq'])->whereNumber('faq')->name('course-faqs.update');
                Route::delete('course-faqs/{faq}', [CreatorCourseController::class, 'destroyFaq'])->whereNumber('faq')->name('course-faqs.destroy');
            });
        });

    Route::redirect('blogger/dashboard', '/creator/dashboard')
        ->middleware('role:blogger')
        ->name('blogger.dashboard');

    Route::redirect('blogger/pending', '/creator/pending')
        ->middleware('role:blogger')
        ->name('blogger.pending');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['admin', 'throttle:admin-operations'])
        ->group(function (): void {
            Route::get('dashboard', AdminDashboardController::class)->name('dashboard');
            Route::get('reports', AdminReportController::class)
                ->middleware('permission:view_reports')
                ->name('reports.index');
            Route::get('review-center', AdminReviewCenterController::class)
                ->middleware('permission:approve_bloggers|manage_blogs|manage_courses|manage_cms')
                ->name('review-center.index');
            Route::prefix('review-center')
                ->middleware('permission:approve_bloggers')
                ->name('review-center.')
                ->group(function (): void {
                    Route::post('creator-applications/{profile}/approve', [AdminReviewCenterController::class, 'approveCreatorApplication'])
                        ->whereNumber('profile')
                        ->name('creator-applications.approve');
                    Route::post('creator-applications/{profile}/reject', [AdminReviewCenterController::class, 'rejectCreatorApplication'])
                        ->whereNumber('profile')
                        ->name('creator-applications.reject');
                });
            Route::prefix('review-center')
                ->middleware('permission:manage_cms|manage_blogs|manage_courses')
                ->name('review-center.')
                ->group(function (): void {
                    Route::post('copyright-takedowns/{takedownRequest}/reviewing', [AdminReviewCenterController::class, 'markCopyrightTakedownReviewing'])
                        ->whereNumber('takedownRequest')
                        ->name('copyright-takedowns.reviewing');
                    Route::post('copyright-takedowns/{takedownRequest}/resolve', [AdminReviewCenterController::class, 'resolveCopyrightTakedown'])
                        ->whereNumber('takedownRequest')
                        ->name('copyright-takedowns.resolve');
                    Route::post('copyright-takedowns/{takedownRequest}/dismiss', [AdminReviewCenterController::class, 'dismissCopyrightTakedown'])
                        ->whereNumber('takedownRequest')
                        ->name('copyright-takedowns.dismiss');
                });

            $adminResources = app(AdminResourceRegistry::class)->all();

            Route::prefix('operations')
                ->middleware('permission:manage_settings|admin.health_checks.view|admin.operational_alerts.view')
                ->name('operations-center.')
                ->group(function (): void {
                    Route::get('/', [OperationsCenterController::class, 'index'])->name('index');
                    Route::post('actions/backup', [OperationsCenterController::class, 'backup'])
                        ->defaults('operation', 'backup')
                        ->name('backup');
                    Route::post('actions/health', [OperationsCenterController::class, 'health'])
                        ->defaults('operation', 'health')
                        ->name('health');
                    Route::post('actions/monitor', [OperationsCenterController::class, 'monitor'])
                        ->defaults('operation', 'monitor')
                        ->name('monitor');
                });

            Route::prefix('blog-workflow')
                ->middleware('permission:manage_blogs')
                ->name('blog-workflow.')
                ->group(function (): void {
                    Route::post('blogs/{post}/approve', [AdminBlogWorkflowController::class, 'approve'])
                        ->whereNumber('post')
                        ->name('blogs.approve');
                    Route::post('blogs/{post}/changes-requested', [AdminBlogWorkflowController::class, 'requestChanges'])
                        ->whereNumber('post')
                        ->name('blogs.changes-requested');
                    Route::post('blogs/{post}/reject', [AdminBlogWorkflowController::class, 'reject'])
                        ->whereNumber('post')
                        ->name('blogs.reject');
                    Route::post('blogs/{post}/publish', [AdminBlogWorkflowController::class, 'publish'])
                        ->middleware('permission:admin.content.publish')
                        ->whereNumber('post')
                        ->name('blogs.publish');
                    Route::post('revisions/{revision}/approve', [AdminBlogWorkflowController::class, 'approveRevision'])
                        ->whereNumber('revision')
                        ->name('revisions.approve');
                    Route::post('revisions/{revision}/changes-requested', [AdminBlogWorkflowController::class, 'requestRevisionChanges'])
                        ->whereNumber('revision')
                        ->name('revisions.changes-requested');
                    Route::post('revisions/{revision}/reject', [AdminBlogWorkflowController::class, 'rejectRevision'])
                        ->whereNumber('revision')
                        ->name('revisions.reject');
                    Route::post('deletion-requests/{deletionRequest}/approve', [AdminBlogWorkflowController::class, 'approveDeletion'])
                        ->whereNumber('deletionRequest')
                        ->name('deletions.approve');
                    Route::post('deletion-requests/{deletionRequest}/reject', [AdminBlogWorkflowController::class, 'rejectDeletion'])
                        ->whereNumber('deletionRequest')
                        ->name('deletions.reject');
                });

            Route::prefix('course-workflow')
                ->middleware('permission:manage_courses')
                ->name('course-workflow.')
                ->group(function (): void {
                    Route::post('courses/{course}/approve', [AdminCourseWorkflowController::class, 'approve'])
                        ->middleware('permission:admin.content.publish')
                        ->whereNumber('course')
                        ->name('courses.approve');
                    Route::post('courses/{course}/changes-requested', [AdminCourseWorkflowController::class, 'requestChanges'])
                        ->whereNumber('course')
                        ->name('courses.changes-requested');
                    Route::post('courses/{course}/reject', [AdminCourseWorkflowController::class, 'reject'])
                        ->whereNumber('course')
                        ->name('courses.reject');
                    Route::post('revisions/{revision}/approve', [AdminCourseWorkflowController::class, 'approveRevision'])
                        ->whereNumber('revision')
                        ->name('revisions.approve');
                    Route::post('revisions/{revision}/changes-requested', [AdminCourseWorkflowController::class, 'requestRevisionChanges'])
                        ->whereNumber('revision')
                        ->name('revisions.changes-requested');
                    Route::post('revisions/{revision}/reject', [AdminCourseWorkflowController::class, 'rejectRevision'])
                        ->whereNumber('revision')
                        ->name('revisions.reject');
                    Route::post('deletion-requests/{deletionRequest}/approve', [AdminCourseWorkflowController::class, 'approveDeletion'])
                        ->whereNumber('deletionRequest')
                        ->name('deletions.approve');
                    Route::post('deletion-requests/{deletionRequest}/reject', [AdminCourseWorkflowController::class, 'rejectDeletion'])
                        ->whereNumber('deletionRequest')
                        ->name('deletions.reject');
                });

            Route::prefix('trash')
                ->middleware('permission:manage_courses|manage_blogs')
                ->name('trash.')
                ->group(function (): void {
                    Route::post('{type}/{id}/restore', [AdminTrashController::class, 'restore'])
                        ->whereIn('type', ['course', 'blog'])
                        ->whereNumber('id')
                        ->name('restore');
                    Route::delete('{type}/{id}/force', [AdminTrashController::class, 'destroy'])
                        ->middleware('permission:admin.content.force_delete')
                        ->whereIn('type', ['course', 'blog'])
                        ->whereNumber('id')
                        ->name('force-delete');
                });

            Route::post('course-categories/quick', [CatalogAdminController::class, 'quickStoreCategory'])
                ->middleware($adminResources['course_categories']->middlewareFor('manage'))
                ->defaults('resource', 'course_categories')
                ->name('course-categories.quick-store');

            Route::get('catalog/course-categories/{category}/subcategories', [CatalogAdminController::class, 'subcategories'])
                ->middleware($adminResources['courses']->middlewareFor('manage'))
                ->defaults('resource', 'courses')
                ->whereNumber('category')
                ->name('catalog.course-categories.subcategories');

            Route::get('catalog/courses/{course}/lessons', [CatalogAdminController::class, 'lessons'])
                ->middleware($adminResources['courses']->middlewareFor('manage'))
                ->defaults('resource', 'courses')
                ->whereNumber('course')
                ->name('catalog.courses.lessons');

            Route::prefix('courses/{course}/builder')
                ->middleware($adminResources['courses']->middlewareFor('manage'))
                ->name('courses.builder.')
                ->group(function (): void {
                    Route::get('/', [AdminCourseBuilderController::class, 'show'])->defaults('resource', 'courses')->whereNumber('course')->name('show');
                    Route::patch('/', [AdminCourseBuilderController::class, 'update'])->defaults('resource', 'courses')->whereNumber('course')->name('update');
                    Route::patch('ownership', [AdminCourseBuilderController::class, 'ownership'])->defaults('resource', 'courses')->whereNumber('course')->name('ownership');
                    Route::patch('publishing', [AdminCourseBuilderController::class, 'publishing'])->defaults('resource', 'courses')->whereNumber('course')->name('publishing');
                    Route::patch('product', [AdminCourseBuilderController::class, 'product'])->defaults('resource', 'courses')->whereNumber('course')->name('product');
                    Route::post('items/{kind}', [AdminCourseBuilderController::class, 'storeItem'])
                        ->defaults('resource', 'courses')
                        ->whereNumber('course')
                        ->whereIn('kind', AdminCourseBuilderService::ITEM_KINDS)
                        ->name('items.store');
                    Route::patch('items/{kind}/{id}', [AdminCourseBuilderController::class, 'updateItem'])
                        ->defaults('resource', 'courses')
                        ->whereNumber('course')
                        ->whereIn('kind', AdminCourseBuilderService::ITEM_KINDS)
                        ->whereNumber('id')
                        ->name('items.update');
                    Route::delete('items/{kind}/{id}', [AdminCourseBuilderController::class, 'destroyItem'])
                        ->defaults('resource', 'courses')
                        ->whereNumber('course')
                        ->whereIn('kind', AdminCourseBuilderService::ITEM_KINDS)
                        ->whereNumber('id')
                        ->name('items.destroy');
                    Route::patch('reorder', [AdminCourseBuilderController::class, 'reorder'])
                        ->defaults('resource', 'courses')
                        ->whereNumber('course')
                        ->name('reorder');
                });

            Route::prefix('menus/{menu}/builder')
                ->middleware($adminResources['menus']->middlewareFor('manage'))
                ->name('menus.builder.')
                ->group(function (): void {
                    Route::get('/', [AdminMenuBuilderController::class, 'show'])
                        ->whereNumber('menu')
                        ->name('show');
                    Route::patch('reorder', [AdminMenuBuilderController::class, 'reorder'])
                        ->whereNumber('menu')
                        ->name('reorder');
                });

            Route::prefix('editorial/revisions/{revision}')
                ->middleware($adminResources['editorial_revisions']->middlewareFor('view'))
                ->name('editorial.revisions.')
                ->group(function () use ($adminResources): void {
                    Route::get('review', [EditorialRevisionReviewController::class, 'show'])
                        ->whereNumber('revision')
                        ->name('review');
                    Route::post('comments', [EditorialRevisionReviewController::class, 'storeComment'])
                        ->middleware($adminResources['editorial_revisions']->middlewareFor('manage'))
                        ->whereNumber('revision')
                        ->name('comments.store');
                    Route::patch('comments/{comment}/resolve', [EditorialRevisionReviewController::class, 'resolveComment'])
                        ->middleware($adminResources['editorial_revisions']->middlewareFor('manage'))
                        ->whereNumber('revision')
                        ->whereNumber('comment')
                        ->name('comments.resolve');
                });

            Route::prefix('learning/records')
                ->name('learning.records.')
                ->group(function (): void {
                    Route::get('{type}/{id}', [LearningRecordReviewController::class, 'show'])
                        ->whereIn('type', array_keys(AbstractLearningRecordRequest::RESOURCE_BY_TYPE))
                        ->whereNumber('id')
                        ->name('show');
                    Route::patch('{type}/{id}', [LearningRecordReviewController::class, 'update'])
                        ->whereIn('type', array_keys(AbstractLearningRecordRequest::RESOURCE_BY_TYPE))
                        ->whereNumber('id')
                        ->name('update');
                });

            Route::prefix('operational-records')
                ->name('operational-records.')
                ->group(function (): void {
                    Route::get('export/{resource}', OperationalRecordExportController::class)
                        ->whereIn('resource', [
                            ...CommerceOperationsAdminService::RESOURCES,
                            ...CommunityOperationsAdminService::RESOURCES,
                            ...OperationsCenterAdminService::RESOURCES,
                        ])
                        ->name('export');
                    Route::get('{type}/{id}', [OperationalRecordActionController::class, 'show'])
                        ->whereIn('type', array_keys(AbstractOperationalRecordRequest::RESOURCE_BY_TYPE))
                        ->whereNumber('id')
                        ->name('show');
                    Route::patch('{type}/{id}', [OperationalRecordActionController::class, 'update'])
                        ->whereIn('type', array_keys(AbstractOperationalRecordRequest::RESOURCE_BY_TYPE))
                        ->whereNumber('id')
                        ->name('update');
                });

            foreach (CourseCatalogAdminService::RESOURCES as $catalogResource) {
                $catalogDefinition = $adminResources[$catalogResource];

                Route::patch($catalogDefinition->path.'/reorder', [CatalogAdminController::class, 'reorder'])
                    ->middleware($catalogDefinition->middlewareFor('manage'))
                    ->middleware('permission:admin.catalog.reorder')
                    ->defaults('resource', $catalogResource)
                    ->name($catalogDefinition->routeName.'.reorder');
            }

            foreach (EditorialCmsAdminService::REORDERABLE_RESOURCES as $editorialResource) {
                $editorialDefinition = $adminResources[$editorialResource];

                Route::patch($editorialDefinition->path.'/reorder', [EditorialAdminController::class, 'reorder'])
                    ->middleware($editorialDefinition->middlewareFor('manage'))
                    ->defaults('resource', $editorialResource)
                    ->name($editorialDefinition->routeName.'.reorder');
            }

            foreach ($adminResources as $resource => $adminResource) {
                $controller = match ($adminResource->domain) {
                    AdminDomain::Catalog => CatalogAdminController::class,
                    AdminDomain::Editorial => EditorialAdminController::class,
                    AdminDomain::Learning => LearningAdminController::class,
                    AdminDomain::Commerce => CommerceAdminController::class,
                    AdminDomain::Community => CommunityAdminController::class,
                    AdminDomain::Growth => GrowthAdminController::class,
                    AdminDomain::Operations => OperationsAdminController::class,
                };

                Route::get($adminResource->path, [$controller, 'index'])
                    ->middleware($adminResource->middlewareFor('view'))
                    ->defaults('resource', $resource)
                    ->name($adminResource->routeName.'.index');

                Route::post($adminResource->path, [$controller, 'store'])
                    ->middleware($adminResource->middlewareFor('manage'))
                    ->defaults('resource', $resource)
                    ->name($adminResource->routeName.'.store');

                Route::post($adminResource->path.'/bulk', [$controller, 'bulk'])
                    ->middleware($adminResource->middlewareFor('manage'))
                    ->defaults('resource', $resource)
                    ->name($adminResource->routeName.'.bulk');

                Route::patch($adminResource->path.'/{id}', [$controller, 'update'])
                    ->middleware($adminResource->middlewareFor('manage'))
                    ->defaults('resource', $resource)
                    ->whereNumber('id')
                    ->name($adminResource->routeName.'.update');

                Route::delete($adminResource->path.'/{id}', [$controller, 'destroy'])
                    ->middleware($adminResource->middlewareFor('manage'))
                    ->defaults('resource', $resource)
                    ->whereNumber('id')
                    ->name($adminResource->routeName.'.destroy');
            }
        });
});

require __DIR__.'/settings.php';
