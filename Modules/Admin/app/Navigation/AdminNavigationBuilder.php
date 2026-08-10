<?php

namespace Modules\Admin\Navigation;

use App\Models\User;
use Modules\Admin\Data\AdminResourceDefinition;
use Modules\Admin\Enums\AdminDomain;
use Modules\Admin\Policies\AdminResourcePolicy;
use Modules\Admin\Registry\AdminResourceRegistry;

final readonly class AdminNavigationBuilder
{
    public function __construct(
        private AdminResourceRegistry $registry,
        private AdminResourcePolicy $policy,
    ) {}

    /**
     * @return list<array{key: string, label: string, icon: string, items: list<array{title: string, href: string}>}>
     */
    public function for(User $user): array
    {
        return collect(AdminDomain::cases())
            ->map(function (AdminDomain $domain) use ($user): array {
                $metadata = $this->registry->domain($domain);
                $items = $this->registry->forDomain($domain)
                    ->filter(fn (AdminResourceDefinition $definition): bool => $definition->navigationVisible
                        && $this->policy->view($user, $definition))
                    ->sortBy('navigationOrder')
                    ->map(fn (AdminResourceDefinition $definition): array => [
                        'title' => $definition->title,
                        'href' => '/admin/'.$definition->path,
                    ])
                    ->values()
                    ->all();

                if ($domain === AdminDomain::Operations && $items !== []) {
                    array_unshift($items, [
                        'title' => 'Operations Center',
                        'href' => '/admin/operations',
                    ]);
                }

                return [
                    'key' => $domain->value,
                    'label' => $metadata['label'],
                    'icon' => $metadata['icon'],
                    'order' => $metadata['order'],
                    'items' => $items,
                ];
            })
            ->filter(fn (array $domain): bool => $domain['items'] !== [])
            ->sortBy('order')
            ->map(fn (array $domain): array => [
                'key' => $domain['key'],
                'label' => $domain['label'],
                'icon' => $domain['icon'],
                'items' => $domain['items'],
            ])
            ->values()
            ->all();
    }
}
