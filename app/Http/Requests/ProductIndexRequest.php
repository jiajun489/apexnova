<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'min_stock' => ['sometimes', 'integer', 'min:0'],
            'max_stock' => ['sometimes', 'integer', 'min:0', Rule::when($this->filled('min_stock'), 'gte:min_stock')],
            // in_stock: >10 units, low_stock: 1-10, out_of_stock: 0
            'stock_status' => ['sometimes', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
