<?php

namespace Modules\Admin\Http\Requests\Learning;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Admin\Policies\AdminResourcePolicy;
use Modules\Admin\Registry\AdminResourceRegistry;

abstract class AbstractLearningRecordRequest extends FormRequest
{
    /** @var array<string, string> */
    public const RESOURCE_BY_TYPE = [
        'quiz-attempt' => 'quiz_attempts',
        'assignment-submission' => 'assignment_submissions',
        'lesson-progress' => 'lesson_progress',
        'certificate' => 'certificates',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        $resource = self::RESOURCE_BY_TYPE[$this->recordType()] ?? null;

        if (! $user instanceof User || ! is_string($resource)) {
            return false;
        }

        $definition = app(AdminResourceRegistry::class)->get($resource);
        $policy = app(AdminResourcePolicy::class);

        if ($this->isMethod('get')) {
            return $policy->view($user, $definition);
        }

        return $policy->manage($user, $definition)
            && $user->can($this->sensitivePermission());
    }

    public function recordType(): string
    {
        return (string) $this->route('type');
    }

    public function resourceKey(): string
    {
        return self::RESOURCE_BY_TYPE[$this->recordType()] ?? '';
    }

    private function sensitivePermission(): string
    {
        return match ($this->recordType()) {
            'assignment-submission' => 'admin.learning.grade_submissions',
            'certificate' => 'admin.learning.revoke_certificates',
            default => 'admin.learning.correct_records',
        };
    }
}
