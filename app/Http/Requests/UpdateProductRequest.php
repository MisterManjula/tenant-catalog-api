<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('products')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->ignore($this->route('product')),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'price_cents' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
