<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Student\PaymentController;
use App\Http\Controllers\Admin\PaymentApprovalController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialLikeController;
use App\Http\Controllers\MaterialCommentController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\Admin\AdminMaterialController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\Admin\AdminUserController;

Route::options('/{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('any', '.*');

/* ================= PUBLIC ROUTES (NO AUTH REQUIRED) ================= */
Route::get('departments', [DepartmentController::class, 'index']);
Route::post('student/payments/submit', [PaymentController::class, 'submit']);

// PUBLIC STREAMING ENDPOINT - MUST BE OUTSIDE ALL AUTH MIDDLEWARE
Route::get('/materials/{material}/stream', [MaterialController::class, 'stream'])->name('materials.stream');
// Add this route for video streaming
Route::get('/video/stream/{filename}', [VideoController::class, 'stream'])->name('video.stream');

/* ================= AUTH ROUTES ================= */
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/password/reset', [PasswordResetController::class, 'reset']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::get('/check-auth', [AuthController::class, 'checkAuth']);
    Route::put('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

/* ================= STUDENT ROUTES ================= */
Route::middleware(['auth:sanctum','role:student'])->prefix('student')->group(function () {
    Route::get('/payments', [PaymentController::class, 'index']);
});

/* ================= ADMIN PAYMENT APPROVAL ================= */
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('/payments/pending', [PaymentApprovalController::class, 'getPendingPayments']);
    Route::post('/payments/{id}/approve', [PaymentApprovalController::class, 'approve']);
    Route::post('/payments/{id}/deny', [PaymentApprovalController::class, 'deny']);
});

/* ================= MATERIALS, LIKES, COMMENTS & PROGRESS ================= */
// NOTE: The stream route has been moved to PUBLIC ROUTES above
Route::middleware(['auth:sanctum','subscription.active'])->group(function () {
    Route::get('/materials', [MaterialController::class, 'index']);
    Route::get('/materials/{material}', [MaterialController::class, 'show']);
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');
    Route::post('/materials/{material}/like', [MaterialController::class, 'like']);
    Route::post('/materials/{material}/comment', [MaterialController::class, 'comment']);
    Route::post('/materials/{material}/progress', [MaterialController::class, 'updateProgress']);
    Route::get('/materials/{material}/stats', [MaterialController::class, 'getStats']);
    Route::get('/materials/recommended', [MaterialController::class, 'getRecommended']);

    // Keep this for backward compatibility
    Route::post('materials/{material}/progress', [ProgressController::class,'update']);

    Route::get('/progress', [ProgressController::class, 'index']);
    Route::get('/progress/{material}', [ProgressController::class, 'show']);
});

/* ================= ADMIN MATERIAL MANAGEMENT ================= */
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    // CRUD for materials
    Route::get('materials', [AdminMaterialController::class, 'index']);
    Route::post('materials', [AdminMaterialController::class,'store']);
    Route::put('materials/{material}', [AdminMaterialController::class,'update']);
    Route::delete('materials/{material}', [AdminMaterialController::class,'destroy']);
    Route::post('materials/{material}/publish', [AdminMaterialController::class,'publish']);

    // General upload
    Route::post('/upload', [UploadController::class, 'upload']);
});

/* ================= ADMIN DASHBOARD ================= */
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/stats/simple', [DashboardController::class, 'statsSimple']);
});

/* ================= ADMIN USER MANAGEMENT ================= */
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('users', [AdminUserController::class,'index']);
    Route::post('users', [AdminUserController::class,'store']);
    Route::put('users/{user}', [AdminUserController::class,'update']);
    Route::delete('users/{user}', [AdminUserController::class,'destroy']);
    Route::post('users/{user}/resend-credentials', [AdminUserController::class,'resendCredentials']);
});

Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::get('departments/{department}', [DepartmentController::class, 'show']);
    Route::put('departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);
});