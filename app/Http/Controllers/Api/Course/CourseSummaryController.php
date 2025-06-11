<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Course;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class CourseSummaryController extends Controller
{
    // updateSummary()
    public function update(Request $request, $id)
    {

        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'level' => 'required|in:beginner,intermediate,advance',
                'price' => 'required|numeric|min:0',
                'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'detail' => 'required|string',
            ]);

            $course = Course::where('id', $id)
                ->where('id_instructor', $user->id)
                ->firstOrFail();

            // Handle image upload
            if ($request->hasFile('image')) {
                $newImagePath = $request->file('image')->store('course', 'public');

                // Check if the new image is different from the current one
                if ($course->image && $course->image !== $newImagePath) {
                    // Delete the old image from storage
                    if (Storage::disk('public')->exists($course->image)) {
                        Storage::disk('public')->delete($course->image);
                    }
                }

                // Update the course's image attribute
                $course->image = $newImagePath;
            }

            // Update course attributes
            $course->title = $validated['title'];
            $course->level = $validated['level'];
            $course->price = $validated['price'];

            $oldDetail = $course->detail ?? '';
            $newDetail = $validated['detail'];

            $imagesToDelete = $this->getImagesToDeleteFromDetail($oldDetail, $newDetail);
            $newDetailCleaned = $this->removeDeletedImagesFromDetail($newDetail, $imagesToDelete);

            foreach ($imagesToDelete as $imgPath) {
                if (Storage::disk('public')->exists($imgPath)) {
                    Storage::disk('public')->delete($imgPath);
                }
            }

            $course->detail = $newDetailCleaned;
            $course->save();

            $data = [
                'id' => $course->id,
                'title' => $course->title,
                'level' => $course->level,
                'price' => $course->price,
                'image' => $course->image ? asset('storage/' . $course->image) : null,
                'detail' => $newDetailCleaned,
                'updated_at' => $course->updated_at,
            ];

            return new PostResource(true, 'Course summary updated successfully', $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return new PostResource(false, 'Validation failed', $e->errors());
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update course summary: ' . $e->getMessage(), null);
        }
    }

    private function getImagesToDeleteFromDetail($oldDetail, $newDetail)
    {
        $extractLocalImages = function($html) {
            $images = [];
            preg_match_all('/<img[^>]+src="([^">]+)"/', $html, $matches);
            if (isset($matches[1])) {
                foreach ($matches[1] as $imgUrl) {
                    if (strpos($imgUrl, '/storage/wysiwyg/') !== false) {
                        $path = preg_replace('#^.*?/storage/#', '', $imgUrl);
                        $images[] = $path;
                    }
                }
            }
            return $images;
        };

        $oldImages = $extractLocalImages($oldDetail);
        $newImages = $extractLocalImages($newDetail);

        $toDelete = array_diff($oldImages, $newImages);

        return array_values($toDelete);
    }

    private function removeDeletedImagesFromDetail($detailHtml, $imagesToDelete)
    {
        if (empty($imagesToDelete)) return $detailHtml;

        foreach ($imagesToDelete as $imgPath) {
            $pattern = '#<img[^>]+src="[^">]*' . preg_quote($imgPath, '#') . '[^">]*"[^>]*>#i';
            $detailHtml = preg_replace($pattern, '', $detailHtml);
        }
        return $detailHtml;
    }

}
