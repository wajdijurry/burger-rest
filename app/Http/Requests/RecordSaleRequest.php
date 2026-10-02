<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'uuid'],
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            // Positive integer, bounded to 10,000 per event (brief section 4).
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'event_id' => 'sale event',
            'menu_item_id' => 'menu item',
        ];
    }

    public function messages(): array
    {
        return [
            'menu_item_id.required' => 'Please select a menu item.',
            'menu_item_id.exists' => 'Please select a valid menu item.',
            'quantity.min' => 'Sale quantity must be at least 1.',
            'quantity.max' => 'Sale quantity may not exceed 10,000.',
        ];
    }
}
