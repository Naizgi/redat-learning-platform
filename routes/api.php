<?php

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
use App\Http\Controllers\Admin\AdminUserController; // <-- New User Management Controller





Route::get('departments', [DepartmentController::class, 'index']);

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
    Route::post('/payments/submit', [PaymentController::class, 'submit']);
});

/* ================= ADMIN PAYMENT APPROVAL ================= */
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('/payments/pending', [PaymentApprovalController::class, 'getPendingPayments']);
    Route::post('/payments/{id}/approve', [PaymentApprovalController::class, 'approve']);
    Route::post('/payments/{id}/deny', [PaymentApprovalController::class, 'deny']);

});

/* ================= MATERIALS, LIKES, COMMENTS & PROGRESS ================= */
Route::middleware(['auth:sanctum','subscription.active'])->group(function () {
    Route::get('/', [MaterialController::class, 'index']); // GET /api/materials
    Route::get('/{material}', [MaterialController::class, 'show']); // GET /api/materials/{id}
    Route::get('/{material}/stream', [MaterialController::class, 'stream']); // GET /api/materials/{id}/stream
    Route::get('/{material}/download', [MaterialController::class, 'download']); // GET /api/materials/{id}/download
    Route::post('/{material}/like', [MaterialController::class, 'like']); // POST /api/materials/{id}/like
    Route::post('/{material}/comment', [MaterialController::class, 'comment']); // POST /api/materials/{id}/comment
    Route::post('/{material}/progress', [MaterialController::class, 'updateProgress']); // POST /api/materials/{id}/progress
    Route::get('/{material}/stats', [MaterialController::class, 'getStats']); // GET /api/materials/{id}/stats
    Route::get('/recommended', [MaterialController::class, 'getRecommended']);

    Route::post('materials/{material}/like', [MaterialLikeController::class,'toggle']);
    Route::post('materials/{material}/comment', [MaterialCommentController::class,'store']);
    Route::post('materials/{material}/progress', [ProgressController::class,'update']);


    Route::get('/progress', [ProgressController::class, 'index']);
    Route::get('/progress/{material}', [ProgressController::class, 'show']);
});

/* ================= ADMIN MATERIAL MANAGEMENT ================= */
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    // CRUD for materials
    Route::get('materials', [AdminMaterialController::class, 'index']);
    Route::post('materials', [AdminMaterialController::class,'store']);         // Create
    Route::put('materials/{material}', [AdminMaterialController::class,'update']); // Update
    Route::delete('materials/{material}', [AdminMaterialController::class,'destroy']); // Delete
    Route::post('materials/{material}/publish', [AdminMaterialController::class,'publish']); // Publish/Unpublish

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
    Route::get('users', [AdminUserController::class,'index']);          // List users
    Route::post('users', [AdminUserController::class,'store']);         // Add new user
    Route::put('users/{user}', [AdminUserController::class,'update']);  // Update user
    Route::delete('users/{user}', [AdminUserController::class,'destroy']); // Delete user
});


Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::get('departments/{department}', [DepartmentController::class, 'show']);
    Route::put('departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);
});

