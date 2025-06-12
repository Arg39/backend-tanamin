<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\Course\CourseAttributeController;
use App\Http\Controllers\Api\Course\InstructorCourseController;
use App\Http\Controllers\Api\Course\AdminCourseController;
use App\Http\Controllers\Api\Course\OverviewCourseController;
use App\Http\Controllers\Api\CourselamaController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\OrderController;
use App\Models\CourseAttribute;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);

Route::post('/orders', [OrderController::class, 'store']);
Route::post('/midtrans/webhook', [OrderController::class, 'webhook']);


// route using middleware for JWT token
Route::middleware('role:admin')->prefix('admin')->group(function () {
    // user
    Route::post('register-instructor', [AuthController::class, 'registerInstructor']);
    Route::get('instructor-select', [UserProfileController::class, 'getInstructorForSelect']);

    // get image
    Route::get('image/{path}/{filename}', [ImageController::class, 'getImage'])->where('path', '.*');
    
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{id}', [CategoryController::class, 'getCategoryById']);
    Route::match(['put', 'post'], 'categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
    Route::get('categories-select', [CategoryController::class, 'getCategoriesForSelect']);
    
    // course
    Route::get('courses', [AdminCourseController::class, 'index']);
    Route::post('courses', [AdminCourseController::class, 'store']);
    Route::get('courses/{id}', [AdminCourseController::class, 'show']);
    Route::delete('courses/{id}', [AdminCourseController::class, 'destroy']);
    
    // instructor
    Route::get('instructors', [UserProfileController::class, 'getInstructors']);
});

Route::middleware('role:instructor')->prefix('instructor')->group(function () {
    // course
    Route::get('courses', [InstructorCourseController::class, 'index']);
    // detail course
    Route::get('courses/overview/{id}', [OverviewCourseController::class, 'showOverview']);
    Route::match(['put', 'post'], 'courses/overview/{id}/update', [OverviewCourseController::class, 'updateOverview']);

    // update course
    Route::post('courses/info/{id}/add', [CourselamaController::class, 'addCourseInfo']);
    Route::get('courses/info/{id}/view', [CourselamaController::class, 'getInstructorCourseInfo']);
    Route::put('courses/info/{id}/update{id_info}', [CourselamaController::class, 'updateInstructorCourseInfo']);
    Route::delete('courses/info/{id}/delete{id_info}', [CourselamaController::class, 'deleteInstructorCourseInfo']);
});

Route::middleware('role:admin,instructor')->group(function () {
    Route::get('/attribute/{id}', [CourseAttributeController::class, 'index']);
});

Route::middleware('auth:api')->post('/image', [ImageController::class, 'postImage']);

// certificate
Route::middleware(['isAdmin', 'disable.octane'])->group(function () {
    Route::get('certificates/{id}/pdf', [CertificateController::class, 'generatePdf']);
});