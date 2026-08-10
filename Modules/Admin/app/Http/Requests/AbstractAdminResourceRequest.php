<?php

namespace Modules\Admin\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Admin\Data\AdminResourceDefinition;
use Modules\Admin\Policies\AdminResourcePolicy;
use Modules\Admin\Registry\AdminResourceRegistry;

abstract class AbstractAdminResourceRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $policy = app(AdminResourcePolicy::class);

        return $this->ability() === 'view'
            ? $policy->view($user, $this->definition())
            : $policy->manage($user, $this->definition());
    }

    public function definition(): AdminResourceDefinition
    {
        return app(AdminResourceRegistry::class)->get((string) $this->route('resource'));
    }
}
