<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\CompanyContact;
use Illuminate\Http\Request;

class CompanyContactController extends Controller
{
    /**
     * Get the company contact information.
     */
    public function detailCompanyContact()
    {
        try {
            $contact = CompanyContact::first();

            if ($contact) {
                return new PostResource(
                    true,
                    'Company contact retrieved successfully',
                    $contact
                );
            } else {
                $defaultContact = [
                    'telephone'    => null,
                    'email'        => null,
                    'address'      => null,
                    'social_media' => null,
                ];
                return new PostResource(
                    false,
                    'Company contact has not been added yet',
                    $defaultContact
                );
            }
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve company contact: ' . $e->getMessage(), null);
        }
    }

    /**
     * Update the company contact information.
     */
    public function storeOrUpdateCompanyContact(Request $request)
    {
        $validated = $request->validate([
            'telephone'    => 'nullable|string',
            'email'        => 'nullable|string',
            'address'      => 'nullable|string',
            'social_media' => 'nullable|array',
        ]);

        // Convert empty strings to null
        foreach (['telephone', 'email', 'address'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        // Handle social_media empty strings
        if (isset($validated['social_media']) && is_array($validated['social_media'])) {
            foreach ($validated['social_media'] as $key => $value) {
                if ($value === '') {
                    $validated['social_media'][$key] = null;
                }
            }
        }

        // Only one record, get first or create
        $contact = CompanyContact::first();
        if (!$contact) {
            $contact = CompanyContact::create($validated);
        } else {
            $contact->update($validated);
        }

        return new PostResource(true, 'Contact updated successfully', $contact);
    }
}
