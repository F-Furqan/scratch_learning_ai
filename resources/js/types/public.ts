export type PublicMedia = {
    id: number;
    url: string | null;
    title: string | null;
    alt_text: string | null;
    caption: string | null;
};

export type PublicTaxonomy = {
    id: number;
    name: string;
    slug: string;
};

export type PublicUser = {
    id: number;
    name: string;
    url: string | null;
};

export type SeoPayload = {
    title: string;
    description: string;
    canonical_url: string;
    image: string | null;
    open_graph: {
        title: string;
        description: string;
        image: string | null;
    };
    twitter: {
        title: string;
        description: string;
        image: string | null;
    };
    structured_data: Record<string, unknown>[];
};

export type FaqItem = {
    id: number;
    question: string;
    answer: string;
};

export type HomeHeroSlide = {
    id: number;
    eyebrow: string | null;
    title: string;
    subtitle: string | null;
    button_label: string | null;
    target_url: string;
    image_url: string | null;
    image_alt: string | null;
    text_position: 'left' | 'center' | 'right';
    opens_in_new_tab: boolean;
};

export type HomeHero = {
    mode: 'design' | 'slider';
    design: {
        eyebrow: string;
        heading: string;
        highlight_terms: string[];
        description: string | null;
        search_enabled: boolean;
        stats_enabled: boolean;
        featured_course_enabled: boolean;
    };
    slides: HomeHeroSlide[];
};

export type HomePageSectionType =
    | 'course_categories'
    | 'featured_courses'
    | 'learning_paths'
    | 'why_scratch_learning'
    | 'testimonials'
    | 'latest_blogs'
    | 'faq'
    | 'newsletter_lead_magnet'
    | 'final_cta';

export type HomePageSectionCard = {
    title?: string | null;
    body?: string | null;
    icon?: string | null;
    url?: string | null;
};

export type HomePageTestimonial = {
    name?: string | null;
    role?: string | null;
    quote?: string | null;
    rating?: number | string | null;
};

export type HomePageMetric = {
    metric?: string | null;
    label?: string | null;
};

export type HomeCourseCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    url: string;
    course_count: number;
};

export type HomeLearningPath = {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    course_count: number;
    url: string;
};

export type HomeLeadMagnet = {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    form_headline: string | null;
    delivery_url: string | null;
    asset_url: string | null;
    action_url: string;
};

export type HomePageSection = {
    id: number;
    key: string;
    type: HomePageSectionType;
    eyebrow: string | null;
    title: string | null;
    subtitle: string | null;
    body: string | null;
    cta_label: string | null;
    cta_url: string | null;
    background: 'white' | 'soft' | 'dark' | 'accent' | string;
    sort_order: number;
    payload: Record<string, unknown>;
    data: {
        categories?: HomeCourseCategory[];
        courses?: CourseCard[];
        paths?: HomeLearningPath[];
        cards?: HomePageSectionCard[];
        items?: HomePageTestimonial[];
        posts?: BlogPostCard[];
        faqs?: FaqItem[];
        lead_magnet?: HomeLeadMagnet | null;
        metrics?: HomePageMetric[];
    };
};

export type CourseCard = {
    id: number;
    title: string;
    slug: string;
    url: string;
    short_description: string | null;
    level: string | null;
    language: string | null;
    price: number;
    formatted_price: string;
    is_free: boolean;
    lesson_count: number;
    category: PublicTaxonomy | null;
    thumbnail: PublicMedia | null;
    published_at: string | null;
};

export type CourseDetail = CourseCard & {
    description: string | null;
    intro_video_url: string | null;
    subcategory: PublicTaxonomy | null;
    creator: PublicUser | null;
    payment_plan: {
        id: number;
        name: string;
        formatted_amount: string;
        checkout_url: string;
    } | null;
    sections: CourseSection[];
    faqs: FaqItem[];
};

export type CourseSection = {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    sort_order: number;
    lessons: LessonCard[];
};

export type LessonCard = {
    id: number;
    course_id: number;
    section_id: number | null;
    title: string;
    slug: string;
    url: string;
    order_number: number;
    is_free: boolean;
    is_paid: boolean;
    is_locked: boolean;
    preview: string | null;
};

export type LessonDetail = LessonCard & {
    content: string | null;
    video_type: string | null;
    video_url: string | null;
    section: PublicTaxonomy | null;
    allow_comments: boolean;
    allow_questions: boolean;
    faqs: FaqItem[];
    learning: LessonLearning;
    community: LessonCommunity;
};

export type LessonLearning = {
    actions: {
        progress_url: string | null;
        bookmark_url: string | null;
        note_url: string | null;
    };
    drip: {
        is_released: boolean;
        available_at: string | null;
    };
    progress: {
        status: string | null;
        progress_seconds: number;
        duration_seconds: number | null;
        progress_percent: number;
        completed_at: string | null;
    } | null;
    bookmark: {
        id: number;
        label: string | null;
        saved_at: string | null;
    } | null;
    notes: {
        id: number;
        body: string;
        created_at: string | null;
    }[];
    resources: {
        id: number;
        title: string;
        description: string | null;
        type: string;
        access_level: string | null;
        download_url: string | null;
    }[];
    quizzes: {
        id: number;
        title: string;
        description: string | null;
        pass_score: number;
        max_attempts: number;
        time_limit_minutes: number | null;
        is_required: boolean;
        attempt_url: string | null;
        questions: {
            id: number;
            question: string;
            type: string | null;
            points: number;
            options: unknown[] | null;
        }[];
    }[];
    assignments: {
        id: number;
        title: string;
        instructions: string | null;
        pass_score: number;
        max_points: number;
        due_days_after_enrollment: number | null;
        allow_file_uploads: boolean;
        is_required: boolean;
        submission_url: string | null;
    }[];
};

export type LessonCommunity = {
    actions: {
        question_url: string | null;
    };
    questions: {
        id: number;
        title: string | null;
        body: string | null;
        author: PublicUser | null;
        created_at: string | null;
        answer_url: string | null;
        report_url: string | null;
        accepted_answer_id: number | null;
        answers: {
            id: number;
            body: string | null;
            author: PublicUser | null;
            upvotes_count: number;
            is_accepted: boolean;
            accept_url: string | null;
            reaction_url: string | null;
            report_url: string | null;
            created_at: string | null;
        }[];
    }[];
};

export type BlogPostCard = {
    id: number;
    title: string;
    slug: string;
    url: string;
    excerpt: string | null;
    is_featured: boolean;
    published_at: string | null;
    category: PublicTaxonomy | null;
    author: PublicUser | null;
    featured_image: PublicMedia | null;
    tags: PublicTaxonomy[];
};

export type BlogPostDetail = BlogPostCard & {
    content: string | null;
    faqs: FaqItem[];
};

export type BloggerProfile = {
    id: number;
    name: string;
    url: string;
    bio: string | null;
    expertise: string | null;
    linkedin_url: string | null;
    website_url: string | null;
    profile_photo_path: string | null;
    post_count: number;
    is_verified_creator: boolean;
    is_verified_expert: boolean;
    badges: AuthorBadge[];
};

export type AuthorBadge = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
    color: string;
    marks_verified_expert: boolean;
};

export type InstructorProfile = {
    id: number;
    user_id: number;
    display_name: string;
    slug: string;
    url: string;
    headline: string | null;
    bio: string | null;
    credentials: string | null;
    expertise: string | null;
    website_url: string | null;
    linkedin_url: string | null;
    avatar: PublicMedia | null;
    is_verified_expert: boolean;
    badges: AuthorBadge[];
    course_count: number;
    post_count: number;
};

export type CmsBlock = {
    id: number;
    key: string;
    title: string | null;
    body: string | null;
    settings: Record<string, unknown> | null;
};

export type CmsPage = {
    id: number;
    title: string;
    slug: string;
    url: string;
    excerpt: string | null;
    content: string | null;
    template: string | null;
    blocks: CmsBlock[];
    author: PublicUser | null;
    published_at: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
