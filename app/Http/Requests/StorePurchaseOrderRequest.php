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

    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'lines' => 'order lines',
            'lines.*.ingredient_id' => 'ingredient',
            'lines.*.quantity' => 'quantity',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Please select a supplier.',
            'supplier_id.exists' => 'Please select a valid supplier.',
            'lines.required' => 'Add at least one ingredient line.',
            'lines.min' => 'Add at least one ingredient line.',
            'lines.*.ingredient_id.required' => 'Please select an ingredient.',
            'lines.*.ingredient_id.exists' => 'Please select a valid ingredient.',
            'lines.*.quantity.required' => 'Enter a quantity for each line.',
        ];
    }
}
