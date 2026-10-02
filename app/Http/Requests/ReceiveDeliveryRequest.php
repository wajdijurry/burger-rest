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

    public function attributes(): array
    {
        return [
            'lines' => 'delivery lines',
            'lines.*.purchase_order_line_id' => 'order line',
            'lines.*.quantity' => 'quantity',
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one delivery line.',
            'lines.min' => 'Add at least one delivery line.',
            'lines.*.purchase_order_line_id.required' => 'Each delivery line must reference an order line.',
            'lines.*.quantity.required' => 'Enter a received quantity for each line.',
        ];
    }
}
