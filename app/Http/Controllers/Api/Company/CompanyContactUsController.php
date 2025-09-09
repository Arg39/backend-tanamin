<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactUsMessage;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use Illuminate\Support\Str;

class CompanyContactUsController extends Controller
{
    /**
     * Display a paginated listing of the contact us messages.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $messages = ContactUsMessage::orderBy('created_at', 'desc')->paginate($perPage);

        return new TableResource(
            true,
            'Contact us messages retrieved successfully.',
            ['data' => $messages]
        );
    }

    /**
     * Store a newly created contact us message.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $message = ContactUsMessage::create([
            'id'      => (string) Str::uuid(),
            'name'    => $validated['name'],
            'email'   => $validated['email'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ]);

        return new PostResource(true, 'Contact us message stored successfully.', $message);
    }
}
