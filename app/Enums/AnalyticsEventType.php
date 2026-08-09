<?php

namespace App\Enums;

enum AnalyticsEventType: string
{
    case LandingPageView = 'landing_page_view';
    case CourseListingView = 'course_listing_view';
    case CourseView = 'course_view';
    case BlogListingView = 'blog_listing_view';
    case BlogView = 'blog_view';
    case CheckoutStarted = 'checkout_started';
    case CheckoutCompleted = 'checkout_completed';
    case LessonProgress = 'lesson_progress';
    case LessonCompleted = 'lesson_completed';
}
