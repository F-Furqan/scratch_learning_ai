<?php

namespace Modules\Admin\Data;

use Modules\Admin\Enums\AdminDomain;

final readonly class AdminResourceDefinition
{
    /**
     * @param  list<string>  $legacyPermissions
     */
    public function __construct(
        public string $key,
        public AdminDomain $domain,
        public string $title,
        public string $singular,
        public string $path,
        public string $routeName,
        public array $legacyPermissions,
        public bool $navigationVisible = true,
        public int $navigationOrder = 100,
    ) {}

    public function viewPermission(): string
    {
        return "admin.{$this->key}.view";
    }

    public function managePermission(): string
    {
        return "admin.{$this->key}.manage";
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(string $ability): array
    {
        return array_values(array_unique($ability === 'manage' ? [
            ...$this->legacyPermissions,
            $this->managePermission(),
        ] : [
            ...$this->legacyPermissions,
            $this->viewPermission(),
        ]));
    }

    public function middlewareFor(string $ability): string
    {
        return 'permission:'.implode('|', $this->permissionsFor($ability));
    }
}
