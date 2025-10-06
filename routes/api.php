<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutCourseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Course\CertificateController;
use App\Http\Controllers\Api\Company\CompanyActivityController;
use App\Http\Controllers\Api\Company\CompanyContactController;
use App\Http\Controllers\Api\Company\CompanyContactUsController;
use App\Http\Controllers\Api\Company\CompanyPartnershipController;
use App\Http\Controllers\Api\Company\CompanyProfileController;
use App\Http\Controllers\Api\Company\MessageController;
use App\Http\Controllers\Api\Course\CourseAttributeController;
use App\Http\Controllers\Api\Course\InstructorCourseController;
use App\Http\Controllers\Api\Course\AdminCourseController;
use App\Http\Controllers\Api\Course\AttributeCourseController;
use App\Http\Controllers\Api\Course\BookmarkController;
use App\Http\Controllers\Api\Course\CartController;
use App\Http\Controllers\Api\Course\FilteringCardController;
use App\Http\Controllers\Api\Course\Material\LessonCourseController;
use App\Http\Controllers\Api\Course\Material\LessonProgressCourseController;
use App\Http\Controllers\Api\Course\Material\ModuleCourseController;
use App\Http\Controllers\Api\Course\OverviewCourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\Material\MaterialCourseController;
use App\Http\Controllers\Api\Course\Material\QuizCourseController;
use App\Http\Controllers\Api\Course\ReviewCourseController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\CardCourseController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetailCourseController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\OrderController;
use App\Models\CourseAttribute;
use App\Models\Notification;

// ───────────────────────────────
// Public Routes
// ───────────────────────────────

// authenticate
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);

Route::get('categories', [CategoryController::class, 'index']);
Route::post('/enrollments/midtrans/callback', [EnrollmentController::class, 'midtransCallback']);

// course
Route::get('/instructor', [DashboardController::class, 'getInstructor']);
Route::get('/tanamin-courses', [CardCourseController::class, 'index']);
Route::get('/tanamin-courses/best', [CardCourseController::class, 'getBestCourses']);
Route::get('/tanamin-course/{courseId}', [DetailCourseController::class, 'showDetail']);
Route::get('/tanamin-courses/{courseId}/attribute', [DetailCourseController::class, 'getDetailAttribute']);
Route::get('/tanamin-courses/{courseId}/material', [DetailCourseController::class, 'getMaterialPublic']);
Route::get('/tanamin-courses/{courseId}/instructor', [DetailCourseController::class, 'getDetailInstructor']);
Route::get('/tanamin-courses/{courseId}/ratings', [ReviewCourseController::class, 'getRatingsCourse']);
Route::get('/tanamin-courses/{courseId}/other-instructor-courses', [DetailCourseController::class, 'getOtherCoursesInstructor']);
Route::get('/tanamin-courses/instructor-list', [UserProfileController::class, 'getInstructorListByCategory']);
Route::get('/tanamin-courses/instructor-courses/{courseId}', [DetailCourseController::class, 'getCoursesFromInstructorId']);
Route::get('/tanamin-courses/filtering', [FilteringCardController::class, 'getFilteringCard']);
Route::get('/profile/{id}', [UserProfileController::class, 'getProfileById']);
// verify certificate
Route::get('/certificates/verify/{certificateCode}', [CertificateController::class, 'verifyCertificateCode']);

// faq
Route::get('/faq-tanamin', [FaqController::class, 'indexPublic']);

// about company
Route::get('/company/contact', [CompanyContactController::class, 'detailCompanyContact']);
Route::get('/company/profile', [CompanyProfileController::class, 'detailCompanyProfile']);
Route::get('/company/activities', [CompanyActivityController::class, 'indexCompanyActivity']);
Route::get('/company/partnerships', [CompanyPartnershipController::class, 'indexCompanyPartnership']);

// contact
Route::post('/message/add', [MessageController::class, 'store']);
// contact us
Route::post('/company/message', [CompanyContactUsController::class, 'store']);

// ───────────────────────────────
// Student Role
// ───────────────────────────────
Route::middleware('role:student')->group(function () {
    // enrollment
    Route::get('/checkout/buy-now/{courseId}', [CheckoutCourseController::class, 'checkoutBuyNowContent']);
    Route::post('/enrollments/buy-now/{courseId}', [EnrollmentController::class, 'buyNow']);
    Route::post('/enrollments/cart/checkout', [EnrollmentController::class, 'checkoutCart']);
    Route::post('/bookmark/{courseId}/add', [BookmarkController::class, 'addBookmark']);
    Route::post('/bookmark/{courseId}/remove', [BookmarkController::class, 'removeBookmark']);
    Route::get('/tanamin-course/{courseId}/modules', [ModuleCourseController::class, 'indexForStudent']);
    Route::get('/tanamin-course/lesson/{lessonId}/material', [LessonCourseController::class, 'show']);
    Route::get('/tanamin-course/lesson/{lessonId}/quiz', [QuizCourseController::class, 'showQuiz']);
    Route::post('/tanamin-course/useCoupon/{courseId}', [CouponController::class, 'useCoupon']);
    Route::post('tanamin-course/lesson/{lessonId}/quiz/answer', [QuizCourseController::class, 'submitAnswers']);
    Route::post('/tanamin-course/lesson/progress', [LessonProgressCourseController::class, 'storeProgressLesson']);
    Route::get('/tanamin-course/certificate/{courseId}', [CertificateController::class, 'getInformationCertificate']);
    Route::get('/tanamin-courses/my-courses', [CardCourseController::class, 'myCourses']);
    Route::get('/tanamin-courses/bookmarked', [CardCourseController::class, 'bookmarkedCourses']);
    Route::get('/tanamin-courses/purchase-history', [CardCourseController::class, 'purchaseHistory']);

    Route::get('/cart', [CartController::class, 'getCartCourses']);
    Route::post('/cart/add/{courseId}', [CartController::class, 'addToCart']);
    Route::get('/checkout/cart', [CheckoutCourseController::class, 'checkoutCartContent']);
    Route::post('/checkout/cart', [EnrollmentController::class, 'checkoutCart']);

    // certificate
    Route::middleware('disable.octane')->group(function () {
        Route::get('certificates/{certificateCode}/pdf', [CertificateController::class, 'generatePdf']);
    });

    // review course
    Route::post('/tanamin-course/{courseId}/review', [ReviewCourseController::class, 'makeReviewCourse']);
    Route::get('/tanamin-course/{courseId}/reviews', [ReviewCourseController::class, 'viewReviewCourse']);
});

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

    // transaction
    Route::get('transactions', [EnrollmentController::class, 'latestTransactions']);

    // income
    Route::get('dailyIncome', [IncomeController::class, 'dailyIncome']);

    // instructor
    Route::get('instructors', [UserProfileController::class, 'getInstructors']);
    Route::patch('/profile/{id}', [UserProfileController::class, 'updateStatus']);
    Route::delete('/profile/{id}', [UserProfileController::class, 'destroy']);

    // student
    Route::get('students', [UserProfileController::class, 'getStudents']);

    // company profile
    Route::post('/company/profile', [CompanyProfileController::class, 'storeOrUpdateCompanyProfile']);
    // company contact
    Route::post('/company/contact', [CompanyContactController::class, 'storeOrUpdateCompanyContact']);
    // company activity
    Route::get('/company/activity/{id}', [CompanyActivityController::class, 'showCompanyActivity']);
    Route::post('/company/activity', [CompanyActivityController::class, 'storeCompanyActivity']);
    Route::match(['put', 'post'], '/company/activity/{id}', [CompanyActivityController::class, 'updateCompanyActivity']);
    Route::delete('/company/activity/{id}', [CompanyActivityController::class, 'destroyCompanyActivity']);
    // company partnership
    Route::get('/company/partnership/{id}', [CompanyPartnershipController::class, 'showCompanyPartnership']);
    Route::post('/company/partnership', [CompanyPartnershipController::class, 'storeCompanyPartnership']);
    Route::match(['put', 'post'], '/company/partnership/{id}', [CompanyPartnershipController::class, 'updateCompanyPartnership']);
    Route::delete('/company/partnership/{id}', [CompanyPartnershipController::class, 'destroyCompanyPartnership']);

    // faq
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::get('/faq/{id}', [FaqController::class, 'show']);
    Route::post('/faq', [FaqController::class, 'store']);
    Route::put('/faq/{id}', [FaqController::class, 'update']);
    Route::delete('/faq/{id}', [FaqController::class, 'destroy']);

    // message
    Route::get('/company/messages', [CompanyContactUsController::class, 'index']);
});

// ───────────────────────────────
// Instructor & admin Role
// ───────────────────────────────
Route::middleware('role:admin,instructor')->group(function () {
    // detail: overview course
    Route::get('course/{courseId}/overview', [OverviewCourseController::class, 'show']);
    Route::match(['put', 'post'], 'course/{courseId}/overview/update', [OverviewCourseController::class, 'update']);
    Route::match('put', 'course/{courseId}/overview/update-price', [OverviewCourseController::class, 'updatePriceAndDiscount']);
    // detail: attribute course
    Route::get('course/{courseId}/attribute', [AttributeCourseController::class, 'index']);
    // detail: module course
    Route::get('course/{courseId}/modules', [ModuleCourseController::class, 'index']);
    Route::get('course/{courseId}/module/{moduleId}', [ModuleCourseController::class, 'show']);
    // detail: material course => lesson
    Route::get('course/lesson/{lessonId}', [LessonCourseController::class, 'show']);
    // Coupon
    Route::get('coupons', [CouponController::class, 'index']);
    Route::post('coupon', [CouponController::class, 'store']);
    Route::get('coupon/{id}', [CouponController::class, 'show']);
    Route::put('coupon/{id}', [CouponController::class, 'update']);
    Route::delete('coupon/{id}', [CouponController::class, 'destroy']);
});

// ───────────────────────────────
// Instructor Role
// ───────────────────────────────
Route::middleware('role:instructor')->group(function () {
    // all course
    Route::get('/instructor/courses', [InstructorCourseController::class, 'index']);
    // instructor course
    // detail: attribute course
    Route::post('course/{courseId}/attribute', [AttributeCourseController::class, 'storeOrUpdateAttribute']);
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
// Instructor & Student Role
// ───────────────────────────────
Route::middleware('role:instructor,student')->group(function () {
    // profile
    Route::get('/profile', [UserProfileController::class, 'getProfile']);
    Route::match(['put', 'post'], '/profile', [UserProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [UserProfileController::class, 'updatePassword']);
});

// ───────────────────────────────
// All Role
// ───────────────────────────────
Route::middleware('role:admin,instructor,student')->group(function () {
    Route::get('/unread-messages-count', [NotificationController::class, 'getUnreadCountNotifications']);
    Route::get('/notifications', [NotificationController::class, 'indexNotifications']);
    Route::post('/notifications/{notificationId}/mark-as-read', [NotificationController::class, 'markAsReadNotification']);
});

Route::middleware('auth:api')->post('/image', [ImageController::class, 'postImage']);
