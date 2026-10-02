<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'lines.*.quantity' => ['required', 'string', 'max:20'],
        ];
    }
}
