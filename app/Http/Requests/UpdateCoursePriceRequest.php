<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoursePriceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'price' => 'nullable|integer|min:0',
            'discount_type' => 'nullable|in:percent,nominal',
            'discount_value' => 'nullable|integer|min:0',
            'discount_start_at' => 'nullable|date',
            'discount_end_at' => 'nullable|date',
            'is_discount_active' => 'nullable|boolean',
        ];
    }
}