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
