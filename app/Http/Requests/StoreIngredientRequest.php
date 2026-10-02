<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            // Short canonical unit label (g, ml, piece, ...); exact-decimal
            // precision/positivity rules for *quantities* live in Quantity,
            // not here.
            'unit' => ['required', 'string', 'min:1', 'max:20'],
        ];
    }
}
