<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Http\Resources\PostResource;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'about' => 'required|string',
                'vision' => 'required|string',
                'mission' => 'required|string',
            ]);

            $profile = CompanyProfile::first();

            if ($profile) {
                $profile->update($validated);
            } else {
                $profile = CompanyProfile::create($validated);
            }

            return new PostResource(true, 'Company profile saved successfully', $profile);
        } catch (ValidationException $e) {
            return new PostResource(false, $e->getMessage(), null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to save company profile', null);
        }
    }

    public function detail()
    {
        try {
            $profile = CompanyProfile::first();

            if ($profile) {
                return new PostResource(true, 'Company profile retrieved successfully', $profile);
            } else {
                return new PostResource(false, 'Company profile not found', null);
            }
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve company profile', null);
        }
    }
}