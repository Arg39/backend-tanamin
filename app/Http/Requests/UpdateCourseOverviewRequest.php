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
            'title' => 'nullable|string|max:255',
            'level' => 'nullable|in:beginner,intermediate,advance',
            'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'detail' => 'nullable|string',
            'status' => 'nullable|in:new,edited,awaiting_approval,published',
        ];
    }
}
