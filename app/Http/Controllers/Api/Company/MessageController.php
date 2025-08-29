<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'    => 'required|string|max:255',
                'email'   => 'required|email|max:255',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
            ]);

            try {
                $message = Message::create($validated);

                return new PostResource(
                    true,
                    'Message created successfully.',
                    $message
                );
            } catch (\Exception $e) {
                return new PostResource(
                    false,
                    'Failed to create message: ' . $e->getMessage(),
                    null
                );
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return new PostResource(
                false,
                'Validation failed: ' . $e->getMessage(),
                null
            );
        }
    }
}
