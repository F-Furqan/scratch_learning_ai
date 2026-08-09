# Learning Platform

Production-oriented Laravel + Vue/Inertia foundation for a learning, blogging, CMS, media, advertising, payment, and subscription platform.

## Current Stack

- Laravel 13
- Vue 3
- Inertia.js
- Tailwind CSS 4
- Laravel Fortify authentication starter
- PHPStan, Pint, ESLint, Prettier, Vue type checking

## Product Direction

The platform will support four primary roles:

- `super_admin`: full platform ownership and system administration.
- `sub_admin`: admin access through assigned permissions only.
- `blogger`: public registration, approval workflow, and blog publishing.
- `student`: learning account, free content, paid course access, and subscriptions.

The backend must stay API-ready for future Flutter iOS and Android apps. Web routes should serve SEO-friendly public and admin experiences, while mobile-reusable behavior should be exposed through versioned `/api/v1` endpoints.

Payments will use Paddle first for one-time course purchases and monthly/yearly subscriptions, while the internal payment layer stays gateway-neutral for future providers.

## Industrial Build Plan

See [docs/industrial-platform-plan.md](/Users/muhammadfurqanbashir/Herd/scratch_learning_final/docs/industrial-platform-plan.md) for the phased delivery plan, architecture decisions, database modules, security requirements, testing strategy, and release checklist.

## Development Commands

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run dev
```

The default local super admin comes from `SUPER_ADMIN_*` environment values. Change `SUPER_ADMIN_PASSWORD` before using a shared or production environment.

Quality checks:

```bash
composer lint:check
composer types:check
npm run lint:check
npm run format:check
npm run types:check
php artisan test
```

## Safe Feature Tests

The PHPUnit suite is hard-isolated from the local application database. It
forces `APP_ENV=testing`, uses MySQL database `scratch-test`, ignores the normal
Laravel config cache, and refuses to start if any of those values are different.

Create `scratch-test` once in local MySQL. If the test database needs different
credentials from `.env`, configure them in an ignored `.env.testing` file using
`.env.testing.example` as the reference.

Run the named feature report to see exactly which workflows pass or fail:

```bash
composer test:features
```

The report covers authentication and roles, admin CRUD and buttons, creator
agreements and approvals, blog/course revisions, trash/restore, public pages and
APIs, learning progress, payments and entitlements, community moderation, ads,
growth, analytics, security controls, webhooks, and load checks.

Full project check:

```bash
composer test
```
