<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyActivityResource;
use App\Http\Resources\CompanyPartnershipResource;
use App\Http\Resources\CompanyProfileDetailResource;
use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\CompanyActivity;
use App\Models\CompanyPartnership;
use App\Models\CompanyStatistics;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function detailCompanyProfile()
    {
        try {
            $profile = CompanyProfile::first();
            $statistics = CompanyStatistics::all();

            if ($profile) {
                return new PostResource(
                    true,
                    'Company profile retrieved successfully',
                    (new CompanyProfileDetailResource([
                        'profile' => $profile,
                        'statistics' => $statistics
                    ]))->resolve(request())
                );
            } else {
                return new PostResource(false, 'Company profile not found', null);
            }
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve company profile: ' . $e->getMessage(), null);
        }
    }

    public function storeOrUpdateCompanyProfile(Request $request)
    {
        try {
            $validated = $request->validate([
                'about' => 'required|string',
                'vision' => 'required|string',
                'mission' => 'required|array|min:1',
                'mission.*' => 'required|string',
                'statistics' => 'required|array|min:1',
                'statistics.*.title' => 'required|string',
                'statistics.*.value' => 'required',
                'statistics.*.unit' => 'nullable|string',
            ]);

            $profile = CompanyProfile::first();
            $profileData = [
                'about' => $validated['about'],
                'vision' => $validated['vision'],
                'mission' => $validated['mission'],
            ];

            if ($profile) {
                $profile->update($profileData);
            } else {
                $profile = CompanyProfile::create($profileData);
            }

            CompanyStatistics::truncate();
            foreach ($validated['statistics'] as $stat) {
                CompanyStatistics::create([
                    'title' => $stat['title'],
                    'value' => $stat['value'],
                    'unit' => $stat['unit'] ?? null,
                ]);
            }

            $statistics = CompanyStatistics::all();

            return new PostResource(
                true,
                'Company profile saved successfully',
                (new CompanyProfileDetailResource([
                    'profile' => $profile,
                    'statistics' => $statistics
                ]))->resolve(request())
            );
        } catch (ValidationException $e) {
            return new PostResource(false, $e->getMessage(), null);
        } catch (\Exception) {
            return new PostResource(false, 'Failed to save company profile', null);
        }
    }

    public function indexCompanyActivity(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $activities = CompanyActivity::orderBy('order')->paginate($perPage);

            $resourceCollection = CompanyActivityResource::collection($activities);

            return new TableResource(
                true,
                'Company activities retrieved successfully',
                ['data' => $resourceCollection],
                200
            );
        } catch (\Exception $e) {
            return new TableResource(false, 'Failed to retrieve company activities: ' . $e->getMessage(), null, 500);
        }
    }

    public function storeCompanyActivity(Request $request)
    {
        try {
            $validated = $request->validate([
                'image' => 'required|file|image|max:2048',
                'title' => 'required|string|max:255',
                'description' => 'required|string',
            ]);

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('company_activities', 'public');
            } else {
                throw new \Exception('Image file is required.');
            }

            $maxOrder = \App\Models\CompanyActivity::max('order');
            $nextOrder = $maxOrder ? $maxOrder + 1 : 1;

            $activity = \App\Models\CompanyActivity::create([
                'image' => $imagePath,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'order' => $nextOrder,
            ]);

            return new PostResource(
                true,
                'Company activity created successfully',
                (new CompanyActivityResource($activity))->resolve(request())
            );
        } catch (ValidationException $e) {
            return new PostResource(false, $e->getMessage(), null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create company activity: ' . $e->getMessage(), null);
        }
    }

    public function updateCompanyActivity(Request $request, $id)
    {
        try {
            $rules = [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
            ];
            if ($request->hasFile('image')) {
                $rules['image'] = 'nullable|file|image|max:2048';
            }

            $validated = $request->validate($rules);

            $activity = CompanyActivity::find($id);
            if (!$activity) {
                return new PostResource(false, 'Company activity not found', null);
            }

            // Only update image if a new file is uploaded
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($activity->image && Storage::disk('public')->exists($activity->image)) {
                    Storage::disk('public')->delete($activity->image);
                }
                $imagePath = $request->file('image')->store('company_activities', 'public');
                $validated['image'] = $imagePath;
            }

            $activity->update($validated);

            return new PostResource(
                true,
                'Company activity updated successfully',
                (new CompanyActivityResource($activity))->resolve(request())
            );
        } catch (ValidationException $e) {
            return new PostResource(false, $e->getMessage(), null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update company activity: ' . $e->getMessage(), null);
        }
    }

    public function indexCompanyPartnership(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $partnerships = CompanyPartnership::orderBy('partner_name')->paginate($perPage);

            $resourceCollection = CompanyPartnershipResource::collection($partnerships);

            return new TableResource(
                true,
                'Company partnerships retrieved successfully',
                ['data' => $resourceCollection],
                200
            );
        } catch (\Exception $e) {
            return new TableResource(false, 'Failed to retrieve company partnerships: ' . $e->getMessage(), null, 500);
        }
    }

    // I haven't used this function yet
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

    public function deleteCompanyPartnership($id)
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