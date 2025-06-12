<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_category' => 'required|exists:categories,id',
            'id_instructor' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'level' => 'nullable|in:beginner,intermediate,advance',
            'status' => 'nullable|in:new,edited,published',
            'detail' => 'nullable|string',
        ];
    }
}
