<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Store (POST: all required) and update (PUT/PATCH: only sent fields validated). */
class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'name' => [$required, 'string', 'max:255'],
            'price' => [$required, 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock' => [$required, 'integer', 'min:0'],
            'supplier_ids' => ['sometimes', 'array'],
            'supplier_ids.*' => ['integer', 'distinct', 'exists:suppliers,id'],
        ];
    }
}
