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
}
