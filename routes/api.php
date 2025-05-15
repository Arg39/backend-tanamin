<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\OrderController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);

Route::post('/orders', [OrderController::class, 'store']);
Route::post('/midtrans/webhook', [OrderController::class, 'webhook']);


// route using middleware for JWT token
Route::middleware('role:admin')->group(function () {
    // user api
    Route::post('/admin/register', [AuthController::class, 'adminRegister']);
    Route::get('instructor-select', [UserProfileController::class, 'getInstructorForSelect']);

    // get image api
    Route::get('image/{path}/{filename}', [ImageController::class, 'getImage'])->where('path', '.*');
    
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{id}', [CategoryController::class, 'getCategoryById']);
    Route::post('categories/{id}', [CategoryController::class, 'update']);
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
    Route::get('categories-select', [CategoryController::class, 'getCategoriesForSelect']);
    
    // course api
    Route::get('courses', [CourseController::class, 'index']);
    Route::post('courses', [CourseController::class, 'store']);
    Route::get('courses/{id}', [CourseController::class, 'show']);
    
    // instructor api
    Route::get('instructors', [UserProfileController::class, 'getInstructors']);
});

Route::middleware('role:instructor')->group(function () {
    Route::get('courses-instructor', [CourseController::class, 'getInstructorCourse']);
});

Route::middleware('role:admin,instructor')->group(function () {
    // api for instructor and admin
});

// certificate api
Route::middleware(['isAdmin', 'disable.octane'])->group(function () {
    Route::get('certificates/{id}/pdf', [CertificateController::class, 'generatePdf']);
});