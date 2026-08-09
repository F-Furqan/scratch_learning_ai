<?php

namespace App\Enums;

enum HomePageSectionType: string
{
    case CourseCategories = 'course_categories';
    case FeaturedCourses = 'featured_courses';
    case LearningPaths = 'learning_paths';
    case WhyScratchLearning = 'why_scratch_learning';
    case Testimonials = 'testimonials';
    case LatestBlogs = 'latest_blogs';
    case Faq = 'faq';
    case NewsletterLeadMagnet = 'newsletter_lead_magnet';
    case FinalCta = 'final_cta';
}
