<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyActivityResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use Illuminate\Http\Request;
use App\Models\CompanyActivity;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyActivityController extends Controller
{
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

    public function showCompanyActivity($id)
    {
        try {
            $activity = CompanyActivity::find($id);
            if (!$activity) {
                return new PostResource(false, 'Company activity not found', null);
            }

            return new PostResource(
                true,
                'Company activity retrieved successfully',
                (new CompanyActivityResource($activity))->resolve(request())
            );
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve company activity: ' . $e->getMessage(), null);
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

            $maxOrder = CompanyActivity::max('order');
            $nextOrder = $maxOrder ? $maxOrder + 1 : 1;

            $activity = CompanyActivity::create([
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

    public function destroyCompanyActivity($id)
    {
        try {
            $activity = CompanyActivity::find($id);
            if (!$activity) {
                return new PostResource(false, 'Company activity not found', null);
            }

            // Delete image file if exists
            if ($activity->image && Storage::disk('public')->exists($activity->image)) {
                Storage::disk('public')->delete($activity->image);
            }

            $activity->delete();

            return new PostResource(true, 'Company activity deleted successfully', null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to delete company activity: ' . $e->getMessage(), null);
        }
    }
}