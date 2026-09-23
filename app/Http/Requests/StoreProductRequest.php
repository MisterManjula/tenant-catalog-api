<?php

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * tenant_id is deliberately not a rule: anything the client sends for it is
 * dropped by validated(), and the model takes it from the context anyway.
 */
class StoreProductRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => [
                'required', 'string', 'max:64',
                // The unique rule runs a plain query, outside Eloquent: no global scope,
                // so the tenant condition has to be explicit.
                Rule::unique('products')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'name' => ['required', 'string', 'max:255'],
            // Null is allowed on purpose: an unresolved price. Zero never is.
            'price_cents' => ['nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
