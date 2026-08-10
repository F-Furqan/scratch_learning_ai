# Modules

This project uses `nwidart/laravel-modules` v13 for future bounded contexts.

Recommended module split after the foundation:

- `Courses`
- `Blog`
- `Cms`
- `Media`
- `Ads`
- `Payments`
- `Reporting`

Keep cross-cutting identity, auth, and platform bootstrapping in `app/`. Move domain-specific controllers, actions, models, resources, policies, routes, and tests into modules when a domain becomes large enough to own its own lifecycle.

## Admin module

`Modules/Admin` is the first incremental module. It owns the administration architecture while existing domain models remain in `app/Models`:

- one registry for resource metadata, routes, permissions, and navigation
- Catalog, Editorial, Learning, Commerce, Community, Growth, and Operations controllers
- resource-level policies and dedicated request objects
- mutation actions, query factories, query filters, and table resources
- server-driven, permission-aware grouped navigation

New admin resources should be registered in `Modules/Admin/config/admin.php` and implemented inside the owning domain. Stable models should only move into a dedicated domain module when that domain needs an independent lifecycle; module adoption must not become a broad namespace-only rewrite.
