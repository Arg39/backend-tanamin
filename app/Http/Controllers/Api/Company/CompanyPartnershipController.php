<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyPartnershipResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use Illuminate\Http\Request;
use App\Models\CompanyPartnership;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class CompanyPartnershipController extends Controller
{
    public function indexCompanyPartnership(Request $request)
    {
        try {
            $user = null;
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Exception $e) {
                // Not logged in, $user remains null
            }

            if ($user && $user->role === 'admin') {
                $perPage = $request->get('per_page', 10);
                $partnerships = CompanyPartnership::orderBy('partner_name')->paginate($perPage);

                if ($partnerships->count() === 0) {
                    return new PostResource(false, 'No company partnerships found', null);
                }

                $resourceCollection = CompanyPartnershipResource::collection($partnerships);

                return new TableResource(
                    true,
                    'Company partnerships retrieved successfully',
                    ['data' => $resourceCollection],
                    200
                );
            } else {
                $partnerships = CompanyPartnership::orderBy('partner_name')->get();
                if ($partnerships->count() === 0) {
                    return new PostResource(false, 'No company partnerships found', null);
                }
                $data = $partnerships->map(function ($partnership) {
                    return [
                        'logo' => $partnership->logo_url,
                        'name' => $partnership->partner_name,
                        'website' => $partnership->website_url,
                    ];
                })->toArray();

                return new PostResource(
                    true,
                    'Company partnership logos retrieved successfully',
                    $data
                );
            }
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve company partnerships: ' . $e->getMessage(), null, 500);
        }
    }

    public function showCompanyPartnership($id)
    {
        try {
            $partnership = CompanyPartnership::find($id);
            if (!$partnership) {
                return new PostResource(false, 'Company partnership not found', null);
            }

            return new PostResource(
                true,
                'Company partnership detail retrieved successfully',
                (new CompanyPartnershipResource($partnership))->resolve(request())
            );
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve company partnership detail: ' . $e->getMessage(), null);
        }
    }

    public function storeCompanyPartnership(Request $request)
    {
        try {
            $validated = $request->validate([
                'partner_name' => 'required|string|max:255',
                'logo' => 'required|file|image|max:2048',
                'website_url' => 'nullable|string|max:255',
            ]);

            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('company_partnerships', 'public');
            } else {
                throw new \Exception('Logo file is required.');
            }

            $partnership = CompanyPartnership::create([
                'partner_name' => $validated['partner_name'],
                'logo' => $logoPath,
                'website_url' => $validated['website_url'] ?? null,
            ]);

            return new PostResource(
                true,
                'Company partnership created successfully',
                (new CompanyPartnershipResource($partnership))->resolve(request())
            );
        } catch (ValidationException $e) {
            return new PostResource(false, $e->getMessage(), null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create company partnership: ' . $e->getMessage(), null);
        }
    }

    public function updateCompanyPartnership(Request $request, $id)
    {
        try {
            $rules = [
                'partner_name' => 'required|string|max:255',
                'website_url' => 'nullable|string|max:255',
            ];
            if ($request->hasFile('logo')) {
                $rules['logo'] = 'nullable|file|image|max:2048';
            }

            $validated = $request->validate($rules);

            $partnership = CompanyPartnership::find($id);
            if (!$partnership) {
                return new PostResource(false, 'Company partnership not found', null);
            }

            // Only update logo if a new file is uploaded
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($partnership->logo && Storage::disk('public')->exists($partnership->logo)) {
                    Storage::disk('public')->delete($partnership->logo);
                }
                $logoPath = $request->file('logo')->store('company_partnerships', 'public');
                $validated['logo'] = $logoPath;
            }

            $partnership->update($validated);

            return new PostResource(
                true,
                'Company partnership updated successfully',
                (new CompanyPartnershipResource($partnership))->resolve(request())
            );
        } catch (ValidationException $e) {
            return new PostResource(false, $e->getMessage(), null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update company partnership: ' . $e->getMessage(), null);
        }
    }

    public function destroyCompanyPartnership($id)
    {
        try {
            $partnership = CompanyPartnership::find($id);
            if (!$partnership) {
                return new PostResource(false, 'Company partnership not found', null);
            }

            // Delete logo file if exists
            if ($partnership->logo && Storage::disk('public')->exists($partnership->logo)) {
                Storage::disk('public')->delete($partnership->logo);
            }

            $partnership->delete();

            return new PostResource(true, 'Company partnership deleted successfully', null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to delete company partnership: ' . $e->getMessage(), null);
        }
    }
}
