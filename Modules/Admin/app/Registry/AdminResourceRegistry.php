<?php

namespace Modules\Admin\Registry;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use Modules\Admin\Data\AdminResourceDefinition;
use Modules\Admin\Enums\AdminDomain;
use RuntimeException;

final class AdminResourceRegistry
{
    /** @var Collection<string, AdminResourceDefinition> */
    private Collection $resources;

    /**
     * @var array<string, array{label: string, icon: string, order: int}>
     */
    private array $domains;

    public function __construct(Repository $config)
    {
        /** @var array<string, array{label: string, icon: string, order: int}> $domains */
        $domains = $config->get('admin.domains', []);
        /** @var array<string, array<string, mixed>> $resources */
        $resources = $config->get('admin.resources', []);

        $this->domains = $domains;
        $this->resources = collect($resources)
            ->map(fn (array $definition, string $key): AdminResourceDefinition => $this->makeDefinition($key, $definition));

        $this->assertUniqueMetadata();
    }

    /**
     * @return Collection<string, AdminResourceDefinition>
     */
    public function all(): Collection
    {
        return $this->resources;
    }

    /**
     * @return Collection<string, AdminResourceDefinition>
     */
    public function forDomain(AdminDomain $domain): Collection
    {
        return $this->resources->filter(
            fn (AdminResourceDefinition $definition): bool => $definition->domain === $domain,
        );
    }

    public function get(string $key): AdminResourceDefinition
    {
        return $this->resources->get($key)
            ?? throw new RuntimeException("Unknown admin resource [{$key}].");
    }

    /**
     * @return array{label: string, icon: string, order: int}
     */
    public function domain(AdminDomain $domain): array
    {
        return $this->domains[$domain->value]
            ?? throw new RuntimeException("Missing admin domain metadata [{$domain->value}].");
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return $this->resources
            ->flatMap(fn (AdminResourceDefinition $definition): array => [
                $definition->viewPermission(),
                $definition->managePermission(),
            ])
            ->merge($this->sensitivePermissionNames())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function sensitivePermissionNames(): array
    {
        return [
            'admin.content.publish',
            'admin.content.force_delete',
            'admin.catalog.reassign',
            'admin.catalog.reorder',
            'admin.learning.correct_records',
            'admin.learning.grade_submissions',
            'admin.learning.revoke_certificates',
            'admin.payments.reconcile',
            'admin.payments.retry_webhook',
            'admin.payments.refund',
            'admin.payments.revoke_access',
            'admin.records.export',
            'admin.community.moderate',
            'admin.community.accept_answers',
            'admin.community.adjust_reputation',
            'admin.community.manage_members',
            'admin.operations.backup',
            'admin.operations.manage_queue',
            'admin.operations.run_health',
            'admin.operations.run_monitor',
            'admin.operations.manage_alerts',
            'admin.operations.view_logs',
            'admin.roles.assign',
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function makeDefinition(string $key, array $definition): AdminResourceDefinition
    {
        $domain = AdminDomain::tryFrom((string) ($definition['domain'] ?? ''));

        if (! $domain) {
            throw new RuntimeException("Invalid admin domain for resource [{$key}].");
        }

        /** @var list<string> $permissions */
        $permissions = array_values($definition['permissions'] ?? []);

        return new AdminResourceDefinition(
            key: $key,
            domain: $domain,
            title: (string) ($definition['title'] ?? str($key)->headline()),
            singular: (string) ($definition['singular'] ?? str($key)->singular()->headline()),
            path: (string) $definition['path'],
            routeName: (string) $definition['route'],
            legacyPermissions: $permissions,
            navigationVisible: (bool) ($definition['navigation'] ?? true),
            navigationOrder: (int) ($definition['order'] ?? 100),
        );
    }

    private function assertUniqueMetadata(): void
    {
        foreach (['path', 'routeName'] as $property) {
            $values = $this->resources->pluck($property);

            if ($values->duplicates()->isNotEmpty()) {
                throw new RuntimeException("Admin resource {$property} values must be unique.");
            }
        }
    }
}
