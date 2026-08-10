<?php

namespace Modules\Admin\Http\Requests\CourseBuilder;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Admin\Policies\AdminResourcePolicy;
use Modules\Admin\Registry\AdminResourceRegistry;

abstract class AbstractCourseBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && app(AdminResourcePolicy::class)->manage(
            $user,
            app(AdminResourceRegistry::class)->get('courses'),
        );
    }
}
