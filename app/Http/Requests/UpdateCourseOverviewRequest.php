<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseOverviewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'title' => 'sometimes|string|max:255',
            'level' => 'sometimes|in:beginner,intermediate,advance',
            'price' => 'sometimes|numeric|min:0',
            'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'detail' => 'sometimes|string',
        ];
    }
}
