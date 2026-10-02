<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:160'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            // Decimal format/precision/positivity/bound are enforced by the
            // Quantity value object inside CreateMenuItem, not duplicated
            // here; this only guards the request shape.
            'lines.*.quantity' => ['required', 'string', 'max:20'],
        ];
    }
}
