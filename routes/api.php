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
Route::get('categories', [CategoryController::class, 'index']);


// ───────────────────────────────
// Admin Role
// ───────────────────────────────
Route::middleware('role:admin')->group(function () {
    // user
    Route::post('register-instructor', [AuthController::class, 'registerInstructor']);
    Route::get('instructor-select', [UserProfileController::class, 'getInstructorForSelect']);

    // get image
    Route::get('image/{path}/{filename}', [ImageController::class, 'getImage'])->where('path', '.*');
    
    // category
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
// Instructor & admin Role
// ───────────────────────────────

Route::middleware('role:admin,instructor')->group(function () {
    // detail: overview course
    Route::get('course/{courseId}/overview', [OverviewCourseController::class, 'show']);
    Route::match(['put', 'post'], 'course/{courseId}/overview/update', [OverviewCourseController::class, 'update']);
    // detail: attribute course
    Route::get('course/{courseId}/attribute', [AttributeCourseController::class, 'index']);
    Route::get('course/{courseId}/attribute/{attributeId}/view', [AttributeCourseController::class, 'show']);
    // detail: module course
    Route::get('course/{courseId}/modules', [ModuleCourseController::class, 'index']);
    Route::get('course/{courseId}/module/{moduleId}', [ModuleCourseController::class, 'show']);
    // detail: material course => lesson
    Route::get('course/lesson/{lessonId}', [LessonCourseController::class, 'show']);
});

// ───────────────────────────────
// Instructor Role
// ───────────────────────────────
Route::middleware('role:instructor')->group(function () {
    // all course
    Route::get('/instructor/courses', [InstructorCourseController::class, 'index']);
    // instructor course
    // detail: attribute course
    Route::post('course/{courseId}/attribute', [AttributeCourseController::class, 'store']);
    Route::put('course/{courseId}/attribute/{attributeId}/update', [AttributeCourseController::class, 'update']);
    Route::delete('course/{courseId}/attribute/{attributeId}/delete', [AttributeCourseController::class, 'destroy']);
    // detail: module course
    Route::post('course/{courseId}/module', [ModuleCourseController::class, 'store']);
    Route::patch('course/{courseId}/module/{moduleId}', [ModuleCourseController::class, 'update']);
    Route::patch('course/module/updateOrder', [ModuleCourseController::class, 'updateByOrder']);
    Route::delete('course/{courseId}/module/{moduleId}', [ModuleCourseController::class, 'destroy']);
    // detail: material course => lesson
    Route::post('course/module/{moduleId}/lesson', [LessonCourseController::class, 'store']);
    Route::patch('course/lesson/updateOrder', [LessonCourseController::class, 'updateByOrder']);
    Route::put('course/lesson/{lessonId}', [LessonCourseController::class, 'update']);
    Route::delete('course/lesson/{lessonId}', [LessonCourseController::class, 'destroy']);
});

// ───────────────────────────────
// Admin & Instructor Role
// ───────────────────────────────
Route::middleware('role:admin,instructor')->group(function () {
    Route::get('/attribute/{id}', [AttributeCourseController::class, 'index']);
});

// ───────────────────────────────
// Instructor & Student Role
// ───────────────────────────────
Route::middleware('role:instructor,student')->group(function () {
    // profile
    Route::get('/profile', [UserProfileController::class, 'getProfile']);
    Route::match(['put', 'post'], '/profile', [UserProfileController::class, 'updateProfile']);
});

Route::middleware('auth:api')->post('/image', [ImageController::class, 'postImage']);
