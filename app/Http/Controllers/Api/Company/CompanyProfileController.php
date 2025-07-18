<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyProfileDetailResource;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Models\CompanyStatistics;
use Illuminate\Validation\ValidationException;

class CompanyProfileController extends Controller
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
}