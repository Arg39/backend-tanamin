<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\Course\CourseAttributeController;
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
    // user api
    Route::post('register-instructor', [AuthController::class, 'registerInstructor']);
    Route::get('instructor-select', [UserProfileController::class, 'getInstructorForSelect']);

    // get image api
    Route::get('image/{path}/{filename}', [ImageController::class, 'getImage'])->where('path', '.*');
    
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{id}', [CategoryController::class, 'getCategoryById']);
    Route::match(['put', 'post'], 'categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
    Route::get('categories-select', [CategoryController::class, 'getCategoriesForSelect']);
    
    // course api
    Route::get('courses', [CourselamaController::class, 'index']);
    Route::post('courses', [CourselamaController::class, 'store']);
    Route::get('courses/{id}', [CourselamaController::class, 'show']);
    
    // instructor api
    Route::get('instructors', [UserProfileController::class, 'getInstructors']);
});

Route::middleware('role:instructor')->prefix('instructor')->group(function () {
    // course api
    Route::get('courses', [CourselamaController::class, 'getInstructorCourse']);
    Route::get('courses/{tab}/{id}', [CourselamaController::class,'getDetailCourse']);

    // update course api
    Route::match(['put', 'post'], 'courses/ringkasan/{id}/update', [CourselamaController::class, 'updateSummary']);
    Route::post('courses/info/{id}/add', [CourselamaController::class, 'addCourseInfo']);
    Route::get('courses/info/{id}/view', [CourselamaController::class, 'getInstructorCourseInfo']);
    Route::put('courses/info/{id}/update{id_info}', [CourselamaController::class, 'updateInstructorCourseInfo']);
    Route::delete('courses/info/{id}/delete{id_info}', [CourselamaController::class, 'deleteInstructorCourseInfo']);
});

Route::middleware('role:admin,instructor')->group(function () {
    Route::get('/attribute/{id}', [CourseAttributeController::class, 'index']);
});

Route::middleware('auth:api')->post('/image', [ImageController::class, 'postImage']);

// certificate api
Route::middleware(['isAdmin', 'disable.octane'])->group(function () {
    Route::get('certificates/{id}/pdf', [CertificateController::class, 'generatePdf']);
});