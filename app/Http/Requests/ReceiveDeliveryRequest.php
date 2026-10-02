<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            // Deliberately *not* validated against purchase_order_lines
            // here: ownership of the target order is a business rule
            // (ReceiveDelivery/LineNotInOrderException), not a shape check.
            'lines.*.purchase_order_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'string', 'max:20'],
        ];
    }
}
