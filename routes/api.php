<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\Course\CourseAttributeController;
use App\Http\Controllers\Api\Course\InstructorCourseController;
use App\Http\Controllers\Api\Course\AdminCourseController;
use App\Http\Controllers\Api\Course\AttributeCourseController;
use App\Http\Controllers\Api\Course\Material\LessonCourseController;
use App\Http\Controllers\Api\Course\Material\ModuleCourseController;
use App\Http\Controllers\Api\Course\OverviewCourseController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\Material\MaterialCourseController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\OrderController;
use App\Models\CourseAttribute;

// ───────────────────────────────
// Public Routes
// ───────────────────────────────
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);

Route::post('/orders', [OrderController::class, 'store']);
Route::post('/midtrans/webhook', [OrderController::class, 'webhook']);


// ───────────────────────────────
// Authenticated Routes
// ───────────────────────────────
Route::middleware('auth:api')->group(function () {
    // ───────────────────────────────
    // Admin Role
    // ───────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // user
        Route::post('register-instructor', [AuthController::class, 'registerInstructor']);
        Route::get('instructor-select', [UserProfileController::class, 'getInstructorForSelect']);

        // get image
        Route::get('image/{path}/{filename}', [ImageController::class, 'getImage'])->where('path', '.*');
        
        // category
        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('category', [CategoryController::class, 'store']);
        Route::get('category/{id}', [CategoryController::class, 'getCategoryById']);
        Route::match(['put', 'post'], 'category/{id}', [CategoryController::class, 'update']);
        Route::delete('category/{id}', [CategoryController::class, 'destroy']);
        Route::get('categories-select', [CategoryController::class, 'getCategoriesForSelect']);
        
        // course
        Route::get('courses', [AdminCourseController::class, 'index']);
        Route::post('course', [AdminCourseController::class, 'store']);
        Route::get('course/{id}', [AdminCourseController::class, 'show']);
        Route::delete('course/{id}', [AdminCourseController::class, 'destroy']);
        
        // instructor
        Route::get('instructors', [UserProfileController::class, 'getInstructors']);

        // certificate
        Route::middleware('disable.octane')->group(function () {
            Route::get('certificates/{id}/pdf', [CertificateController::class, 'generatePdf']);
        });
    });

    // ───────────────────────────────
    // Instructor Role
    // ───────────────────────────────
    Route::middleware('role:instructor')->prefix('instructor')->group(function () {
        // all course
        Route::get('courses', [InstructorCourseController::class, 'index']);
        // instructor course
        Route::group(['prefix' => 'course'], function () {
            // detail: overview course
            Route::get('{courseId}/overview', [OverviewCourseController::class, 'show']);
            Route::match(['put', 'post'], '{courseId}/overview/update', [OverviewCourseController::class, 'update']);
            // detail: attribute course
            Route::get('{courseId}/attribute', [AttributeCourseController::class, 'index']);
            Route::post('{courseId}/attribute', [AttributeCourseController::class, 'store']);
            Route::get('{courseId}/attribute/{attributeId}/view', [AttributeCourseController::class, 'show']);
            Route::put('{courseId}/attribute/{attributeId}/update', [AttributeCourseController::class, 'update']);
            Route::delete('{courseId}/attribute/{attributeId}/delete', [AttributeCourseController::class, 'destroy']);
            // detail: material course
            Route::get('{courseId}/modules', [ModuleCourseController::class, 'index']);
            Route::post('{courseId}/module', [ModuleCourseController::class, 'store']);
            Route::put('{courseId}/module/{moduleId}', [ModuleCourseController::class, 'update']);
            Route::post('{courseId}/module/updateOrder', [ModuleCourseController::class, 'updateByOrder']);
            Route::delete('{courseId}/module/{moduleId}', [ModuleCourseController::class, 'destroy']);
            // detail: material course => lesson
            Route::post('{courseId}/module/{moduleId}/material', [LessonCourseController::class, 'store']);
            Route::get('material/{lessonId}', [LessonCourseController::class, 'show']);
        });
    });

    // ───────────────────────────────
    // Admin & Instructor Role
    // ───────────────────────────────
    Route::middleware('role:admin,instructor')->group(function () {
        Route::get('/attribute/{id}', [AttributeCourseController::class, 'index']);
    });

    Route::middleware('auth:api')->post('/image', [ImageController::class, 'postImage']);
});