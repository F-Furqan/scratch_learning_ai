# Industrial Platform Plan

This plan turns the requested learning and blogging platform into a phased, production-friendly Laravel + Vue/Inertia build. It favors clear module boundaries, API readiness, policy-driven authorization, auditable admin actions, and a database design that can grow without blocking future mobile apps.

## Assumptions

- Laravel remains the system of record and owns authentication, authorization, content, payments, and entitlements.
- Vue/Inertia powers the web application, with SSR enabled before public SEO pages are released.
- Public pages must be crawlable, fast, and metadata-rich.
- Mobile apps will consume `/api/v1` endpoints later, so business rules belong in services, policies, actions, and resources instead of being locked inside Inertia controllers.
- Paddle is the first payment provider for subscriptions and one-time course purchases.
- Payment gateway code should still be abstracted behind contracts so Stripe, PayPal, or a local provider can be added later without rewriting entitlement logic.
- Rich text HTML must be sanitized before storage or rendering.

## Architecture

### Backend Layers

- **HTTP controllers**: thin request/response orchestration for web and API.
- **Form requests**: validation, authorization pre-checks, and normalized inputs.
- **Policies and middleware**: role, permission, ownership, status, and entitlement checks.
- **Actions/services**: reusable business workflows such as approving bloggers, publishing posts, enrolling students, and granting course access.
- **Repositories/query objects where useful**: complex read screens, filters, public listings, and reporting.
- **API resources**: stable JSON shapes for future mobile clients.
- **Events/listeners**: notifications, audit log entries, email dispatch, media cleanup, and payment lifecycle reactions.
- **Jobs**: video processing hooks, sitemap generation, email, ad metrics aggregation, and reporting exports.

### Frontend Areas

- **Public website**: SEO-friendly pages for home, courses, lessons, blogs, blogger profiles, ads, and CMS pages.
- **Student area**: enrolled courses, purchases, subscriptions, profile, questions, and comments.
- **Blogger area**: application status, profile, posts, drafts, submissions, and admin feedback.
- **Admin area**: operational dashboard, CRUD screens, approvals, permissions, content, settings, reports, and audit activity.
- **Shared UI**: tables, filters, editor, media picker, SEO fields, status badges, confirmation dialogs, and empty states.

## Core Packages

- `spatie/laravel-permission` for roles and permissions.
- `laravel/sanctum` for API tokens and future mobile authentication.
- `paddlehq/paddle-php-sdk` for Paddle server-side integration.
- `@paddle/paddle-js` for web checkout flows.
- A sanitization package such as `mewebstudio/purifier` or a locked-down equivalent.
- A rich editor such as Tiptap on the Vue side.

## Payment Provider: Paddle First

Paddle should be implemented as the initial payment provider because it supports one-time purchases, subscriptions, localized prices, discounts, refunds, checkout, customer portal flows, and subscription lifecycle webhooks.

### Paddle Integration Model

- Keep a local `PaymentGateway` contract and implement `PaddlePaymentGateway`.
- Store provider IDs on local records: `paddle_customer_id`, `paddle_product_id`, `paddle_price_id`, `paddle_transaction_id`, and `paddle_subscription_id`.
- Use Paddle products/prices for monthly plans, yearly plans, course purchases, bundles, and optional future add-ons.
- Use Paddle.js for overlay or inline checkout on the web.
- Create server-side checkout/transaction records before opening checkout.
- Pass local IDs in Paddle `custom_data`, such as `user_id`, `order_id`, `course_id`, and `plan_id`.
- Treat client-side checkout success as a UI signal only.
- Grant course or subscription access only after verified webhook events are processed.
- Add `POST /webhooks/paddle` for webhook delivery.
- Verify webhook signatures before processing events.
- Store every Paddle event in `payment_events` with event ID, event type, payload, processed status, and error text.
- Make webhook processing idempotent so duplicate delivery cannot double-grant access.
- Use the Paddle customer portal for subscription updates, cancellation, invoices, and payment-method changes.
- Use Paddle sandbox until checkout, subscriptions, refunds, and webhook replay testing are stable.

### Paddle Events To Support First

- `transaction.completed`: confirm purchase completion and start fulfillment.
- `transaction.paid`: reconcile payment state where needed.
- `transaction.payment_failed`: record failed payments and show useful student messaging.
- `subscription.created`: store subscription identifiers and grant access.
- `subscription.updated`: handle renewals, upgrades, downgrades, pauses, resumes, and status changes.
- `subscription.canceled`: remove future subscription access according to the paid-through date.
- `subscription.past_due`: warn the student and limit access when the grace period ends.
- `subscription.paused`: pause subscription entitlement.
- `subscription.resumed`: restore subscription entitlement.
- `customer.updated`: keep billing/customer details synchronized.
- `adjustment.created` and `adjustment.updated`: track refunds, credits, and manual adjustments.

### Payment Data Rules

- Local tables own learning access and audit history.
- Paddle owns checkout, taxes, payment collection, billing portal, invoices, and provider payment state.
- Never rely only on browser redirects to unlock paid content.
- Never store raw card data.
- Every payment state change should produce an audit entry visible to admins.

## Roles And Permissions

Seed these roles:

- `super_admin`
- `sub_admin`
- `blogger`
- `student`

Seed permissions:

- `manage_users`
- `manage_roles`
- `manage_permissions`
- `manage_cms`
- `manage_courses`
- `manage_lessons`
- `manage_blogs`
- `approve_bloggers`
- `manage_comments`
- `manage_faqs`
- `manage_media`
- `manage_ads`
- `manage_payments`
- `manage_subscriptions`
- `manage_email_settings`
- `manage_seo_settings`
- `manage_live_courses`
- `view_reports`
- `manage_settings`

Rules:

- `super_admin` bypasses ordinary permission checks through a gate before-hook.
- `sub_admin` can only act through assigned permissions.
- `super_admin` role cannot be deleted, renamed, or stripped from the last super admin.
- Blogger publishing requires `approved` status, and posts can still require admin approval.

## Database Modules

### Users And Profiles

- `users`: auth identity plus status fields.
- `blogger_profiles`: bio, phone, photo, expertise, LinkedIn URL, website URL, application reason, status, reviewed by, reviewed at, admin notes.
- `student_profiles`: preferences and optional student metadata.
- `admin_activity_logs`: actor, action, subject type/id, before/after metadata, IP, user agent.

### Courses

- `course_categories`
- `course_subcategories`
- `courses`
- `course_sections`
- `course_lessons`
- `course_faqs`
- `course_comments`
- `course_questions`

Course and lesson tables include slugs, publish status, ordering, free/paid flags, SEO fields, and author/admin ownership fields.

### Access And Payments

- `plans`: monthly/yearly subscription definitions.
- `orders`: checkout records.
- `payments`: provider transaction records.
- `course_purchases`: lifetime course access.
- `subscriptions`: active, canceled, expired, trialing states.
- `manual_entitlements`: admin-granted access.
- `refunds`: gateway and manual refund records.
- `payment_events`: raw provider webhook events with idempotency tracking.
- `payment_customers`: provider customer IDs linked to local users.

Access must flow through `CourseAccessService`:

- `canViewLesson(User $user, CourseLesson $lesson)`
- `canWatchVideo(User $user, CourseLesson $lesson)`
- `lessonPreview(User|null $user, CourseLesson $lesson)`

### Blog

- `blog_categories`
- `blog_tags`
- `blog_posts`
- `blog_post_tag`
- `blog_comments`
- `blog_faqs`

Blog posts support draft, pending, published, rejected, and archived states.

### CMS And SEO

- `pages`
- `menus`
- `menu_items`
- `content_blocks`
- `site_settings`
- `seo_metadata`
- `redirects`

SEO metadata should be reusable by pages, courses, lessons, blogs, and categories through a polymorphic relationship or a consistent embedded column strategy.

### Media

- `media_assets`
- `media_folders`
- `media_usages`

Store title, alt text, caption, mime type, size, disk, path, dimensions, uploader, visibility, and folder. Forms should select media by ID instead of copying URLs into unrelated tables.

### Advertisements

- `ad_zones`
- `ad_campaigns`
- `ad_creatives`
- `ad_impressions`
- `ad_clicks`
- `advertiser_requests`
- `ad_pricing_settings`

Campaigns include budget, CPM/CPC fields, start/end dates, approval status, and active state. Impression and click tables should be append-friendly and aggregated by scheduled jobs.

### Communication

- `email_settings`
- `email_templates`
- `notification_logs`
- `contact_messages`
- `newsletter_subscribers`

Sensitive SMTP values must be encrypted at rest.

## API Design

Use `/api/v1` for mobile-reusable endpoints:

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/me`
- `GET /api/v1/courses`
- `GET /api/v1/courses/{course:slug}`
- `GET /api/v1/lessons/{lesson:slug}`
- `GET /api/v1/blogs`
- `GET /api/v1/blogs/{post:slug}`
- `POST /api/v1/blogger/applications`
- `GET /api/v1/student/entitlements`
- `GET /api/v1/student/purchases`
- `GET /api/v1/student/subscription`

API responses must use Laravel resources, pagination metadata, stable error formats, and policy-backed access checks.

## Web Routes

Public:

- `/`
- `/courses`
- `/courses/{category:slug}`
- `/courses/{category:slug}/{subcategory:slug}`
- `/courses/{category:slug}/{subcategory:slug}/{course:slug}`
- `/courses/{category:slug}/{subcategory:slug}/{course:slug}/{lesson:slug}`
- `/blogs`
- `/blogs/category/{category:slug}`
- `/blogs/tag/{tag:slug}`
- `/blogs/{post:slug}`
- `/bloggers/{user:username}`
- `/advertise`
- CMS pages such as `/about`, `/contact`, `/privacy-policy`, `/terms`, and dynamic slugs.

Admin:

- `/admin/login`
- `/admin/dashboard`
- `/admin/users`
- `/admin/roles`
- `/admin/bloggers`
- `/admin/courses`
- `/admin/blogs`
- `/admin/cms`
- `/admin/media`
- `/admin/ads`
- `/admin/payments`
- `/admin/subscriptions`
- `/admin/settings`
- `/admin/reports`

## Delivery Phases

### Phase 1: Foundation

- Install Sanctum and Spatie Permission.
- Add roles, permissions, seeders, and super admin creation.
- Add user status, blogger profile, and student profile migrations.
- Add admin middleware, route groups, and role-based dashboard redirects.
- Add admin shell with sidebar, dashboard cards, and guarded navigation.
- Add README setup and architectural decisions.

### Phase 2: Content Data Model

- Add migrations, models, factories, policies, and seeders for courses, lessons, blogs, CMS pages, FAQs, comments, and media assets.
- Add slug generation with uniqueness guarantees.
- Add publish status workflows.
- Add SEO metadata fields and rendering helpers.
- Add API resources for public course and blog reads.

### Phase 3: Admin Operations

- Build admin CRUD for users, roles, permissions, blogger approvals, courses, lessons, blogs, CMS pages, menus, media, and settings.
- Add filters, search, pagination, bulk status actions, and audit logging.
- Add media picker integration for CMS, course, and blog forms.
- Add admin notes and approval histories where decisions are made.

### Phase 4: Public Website

- Replace starter welcome page with public home.
- Add course listings, course detail, lesson detail with access locking, blog listings, blog detail, blogger profiles, and CMS pages.
- Enable Inertia SSR and metadata rendering.
- Add structured data for courses, articles, breadcrumbs, FAQs, and organization data.
- Add sitemap and robots controls.

### Phase 5: Access, Payment, And Subscriptions

- Add payment contracts and provider-neutral checkout records.
- Implement Paddle as the first concrete gateway.
- Add Paddle products/prices mapping for courses, monthly plans, yearly plans, and future bundles.
- Add Paddle checkout for one-time course purchase and subscription signup.
- Add Paddle webhook endpoint with signature verification and idempotency.
- Add Paddle customer portal links for subscription management.
- Implement course purchases, plans, subscriptions, manual entitlements, refunds, and payment history.
- Implement `CourseAccessService`.
- Add locked lesson preview with configurable word limits and video lock behavior.
- Add student purchase/subscription screens and API endpoints.

### Phase 6: Ads And Reporting

- Add ad zones, campaigns, creatives, advertiser requests, pricing settings, impression tracking, click tracking, and campaign reports.
- Add scheduled aggregation jobs.
- Add admin revenue, engagement, and content reports.

### Phase 7: Hardening And Launch

- Add complete feature tests for roles, permissions, access rules, publishing, payments, and API contracts.
- Add browser smoke tests for public critical paths.
- Add rate limits, authorization tests, XSS sanitization tests, and payment webhook signature tests.
- Add backups, queues, scheduler, logging, monitoring, deployment notes, and rollback plan.
- Run load checks for public listing pages and admin tables.

## Advanced Feature Roadmap

These features can make the system more advanced after the core platform is stable.

### Learning Experience

- Course progress tracking by lesson, section, and course.
- Continue-watching dashboard for students.
- Notes, bookmarks, and saved lessons.
- Quizzes, assignments, grading, and pass/fail rules.
- Certificates with public verification URLs.
- Learning paths that combine multiple courses into structured tracks.
- Course bundles and skill tracks such as Laravel, Vue, DevOps, and Flutter.
- Drip content for cohort-based or subscription-based learning.
- Downloadable resources and protected file access.
- Code playground or embedded runnable examples for programming lessons.

### Instructor And Blogger Growth

- Instructor profiles separate from admin users.
- Blogger/instructor analytics for views, reads, enrollments, and conversions.
- Revenue share rules for future paid instructors.
- Editorial workflow with reviewer comments, revisions, and scheduled publishing.
- Author badges, verified expert labels, and featured author slots.

### Community

- Lesson Q&A with accepted answers.
- Discussion forums per course and per category.
- Student reactions, upvotes, and reputation points.
- Private member groups for paid subscribers.
- Moderation queue with spam detection and blocked words.
- Report content/comment flow.

### Monetization

- Paddle discounts, coupons, launch offers, and renewal offers.
- Course bundles and subscription-only premium library.
- Team/business plans for companies buying seats.
- Gift purchases for courses.
- Affiliate/referral tracking.
- Abandoned checkout recovery.
- Upsells from single course purchase to monthly or yearly membership.
- Ad-free premium plan.

### SEO And Growth

- Automated sitemap generation.
- Schema.org for courses, articles, FAQs, breadcrumbs, organization, and reviews.
- Internal linking suggestions for blog/course editors.
- Newsletter campaigns and lead magnets.
- Public roadmap or changelog page.
- A/B testing for pricing, landing sections, and course CTAs.
- Social share images generated per course/blog.

### AI-Assisted Features

- Semantic search across courses, lessons, blogs, and FAQs.
- Personalized course recommendations.
- AI lesson summaries for students.
- AI-generated quizzes from lesson content, reviewed before publishing.
- Admin writing assistant for SEO titles, descriptions, and outlines.
- Support assistant trained only on published platform content.

### Admin And Operations

- Audit log dashboard with filters by actor, model, and action.
- Content version history and restore.
- Approval pipelines for blogs, courses, ads, and media.
- Feature flags for staged rollouts.
- Health checks for queues, scheduler, mail, storage, and Paddle webhooks.
- Data export center for users, orders, subscriptions, reports, and content.
- Role templates for common sub-admin responsibilities.
- Impersonation with strict audit logging for support.

### Analytics And Reporting

- Student engagement analytics.
- Course completion and drop-off reports.
- Blog performance and author contribution reports.
- Revenue dashboard by course, subscription, plan, and date range.
- Paddle reconciliation reports.
- Ad performance reports by zone, campaign, impressions, clicks, CTR, and spend.
- Cohort retention for subscriptions.
- Funnel tracking from landing page to checkout to lesson completion.

## Recommended Advanced Build Order

1. Build the stable core: roles, course/blog/CMS data model, admin dashboard, public pages, and API basics.
2. Add Paddle payments, entitlements, and subscription management.
3. Add learning quality features: progress tracking, certificates, quizzes, notes, and bookmarks.
4. Add growth features: SEO automation, newsletters, referral tracking, bundles, and abandoned checkout recovery.
5. Add community features: Q&A, discussions, moderation, reputation, and private groups.
6. Add analytics: student progress, revenue, retention, content performance, and ad reports.
7. Add AI-assisted features only after content quality, permissions, and data privacy rules are solid.

## Security Requirements

- Use policies for every mutable model.
- Sanitize rich text HTML and restrict embeds.
- Escape public output by default.
- Encrypt sensitive settings such as SMTP credentials and payment provider secrets.
- Add rate limiting to auth, contact, comments, questions, ad clicks, and blogger applications.
- Log admin changes to important records.
- Validate uploaded files by MIME type, extension, size, and storage visibility.
- Protect payment webhooks with signature verification and idempotency keys.
- Process Paddle webhooks through queued jobs where safe, with retry limits and dead-letter visibility.
- Avoid direct object reference leaks in API endpoints.

## Testing Strategy

- Unit tests for services such as `CourseAccessService`, slugging, payment gateway contracts, and preview generation.
- Feature tests for auth, role redirects, admin permissions, blogger approval, publishing workflows, course access, and API endpoints.
- Policy tests for each admin-managed model.
- Database tests for cascade behavior and uniqueness rules.
- Frontend type checks and lint checks on every change.
- Regression tests for paid lesson preview and locked video behavior.

## Definition Of Done

A phase is complete only when:

- Migrations are reversible and tested.
- Factories and seeders support local review.
- Policies protect all admin and owner actions.
- Public responses have SEO metadata where applicable.
- API endpoints use resources and return stable shapes.
- Feature tests cover the main happy path and at least one denial path.
- Static analysis, linting, formatting, type checks, and tests pass.

## First Implementation Slice

Start with Phase 1. The recommended first pull request should include:

- Spatie Permission and Sanctum installation.
- Role and permission seeders.
- User status and profile migrations.
- `HasRoles` integration on `User`.
- Admin middleware and route groups.
- Admin login route placeholder.
- Role-based dashboard redirect action.
- Admin dashboard shell.
- Tests proving super admin access, sub admin permission checks, blogger pending lockout, and student dashboard access.
