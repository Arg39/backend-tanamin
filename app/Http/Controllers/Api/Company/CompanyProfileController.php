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
                'about' => 'nullable|string',
                'vision' => 'nullable|string',
                'mission' => 'nullable|array|min:1',
                'mission.*' => 'nullable|string',
                'statistics' => 'nullable|array|min:1',
                'statistics.*.title' => 'nullable|string',
                'statistics.*.value' => 'nullable',
                'statistics.*.unit' => 'nullable|string',
            ]);

            $profile = CompanyProfile::first();
            $profileData = [
                'about' => $validated['about'] ?? null,
                'vision' => $validated['vision'] ?? null,
                'mission' => $validated['mission'] ?? [],
            ];

            if ($profile) {
                $profile->update($profileData);
            } else {
                $profile = CompanyProfile::create($profileData);
            }

            if (isset($validated['statistics'])) {
                CompanyStatistics::truncate();
                foreach ($validated['statistics'] as $stat) {
                    // Ubah string kosong menjadi null
                    $title = isset($stat['title']) && $stat['title'] !== '' ? $stat['title'] : null;
                    $value = isset($stat['value']) && $stat['value'] !== '' ? (int)$stat['value'] : null;
                    $unit = isset($stat['unit']) && $stat['unit'] !== '' ? $stat['unit'] : null;

                    // Jika semua field kosong, skip
                    if (is_null($title) && is_null($value) && is_null($unit)) {
                        continue;
                    }

                    CompanyStatistics::create([
                        'title' => $title,
                        'value' => $value,
                        'unit' => $unit,
                    ]);
                }
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
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to save company profile: ' . $e->getMessage(), null);
        }
    }
}
