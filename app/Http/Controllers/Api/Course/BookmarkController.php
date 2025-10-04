<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bookmark;
use App\Models\Course;
use App\Http\Resources\PostResource;

class BookmarkController extends Controller
{
    public function addBookmark(Request $request, $courseId)
    {
        $user = $request->user();

        if ($user->role !== 'student') {
            return new PostResource(false, 'Only students can bookmark courses.', null);
        }

        $course = Course::find($courseId);
        if (!$course) {
            return new PostResource(false, 'Course not found.', null);
        }

        $exists = Bookmark::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->exists();

        if ($exists) {
            return new PostResource(false, 'Course already bookmarked.', null);
        }

        $bookmark = Bookmark::create([
            'user_id' => $user->id,
            'course_id' => $courseId,
        ]);

        return new PostResource(true, 'Course bookmarked successfully.', $bookmark);
    }

    public function removeBookmark(Request $request, $courseId)
    {
        $user = $request->user();

        if ($user->role !== 'student') {
            return new PostResource(false, 'Only students can remove bookmarks.', null);
        }

        $bookmark = Bookmark::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->first();

        if (!$bookmark) {
            return new PostResource(false, 'Bookmark not found.', null);
        }

        $bookmark->delete();

        return new PostResource(true, 'Bookmark removed successfully.', null);
    }
}
