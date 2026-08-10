<?php

namespace Modules\Admin\Http\Requests\CourseBuilder;

use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends AbstractCourseBuilderRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_free' => ['required', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'name' => ['required_unless:is_free,true', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required_unless:is_free,true', 'nullable', Rule::enum(PaymentProductType::class)],
            'status' => ['required_unless:is_free,true', 'nullable', Rule::enum(PaymentProductStatus::class)],
            'paddle_product_id' => ['nullable', 'string', 'max:255'],
            'tax_category' => ['nullable', 'string', 'max:255'],
        ];
    }
}
