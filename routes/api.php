<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\QpayController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\Admin\BannerAdminController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\CategoryAdminController;
use App\Http\Controllers\Api\Admin\ProductAdminController;

/*
|--------------------------------------------------------------------------
| API Routes (initial migrated subset)
|--------------------------------------------------------------------------
*/

Route::get('/organization', [OrganizationController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

Route::get('/products/customer', [ProductController::class, 'customer']);
Route::get('/products/customer/{id}', [ProductController::class, 'show']);
Route::get('/products/artist', [ProductController::class, 'artist']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/banners', [BannerController::class, 'index']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/register-email', [AuthController::class, 'registerWithEmail']);
Route::post('/auth/register/verify-otp', [AuthController::class, 'verifyRegisterOtp']);
Route::get('/auth/register/status/{registrationId}', [AuthController::class, 'registerStatus']);
Route::get('/auth/verify-mn/callback', [AuthController::class, 'verifyMnCallback']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'google']);
Route::post('/auth/facebook', [AuthController::class, 'facebook']);
Route::match(['get', 'post'], '/qpay/callback/{sender_invoice_no}', [QpayController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/qpay/token', [QpayController::class, 'token']);

    Route::get('/users/me', [UserController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    Route::get('/admin/organization', [OrganizationController::class, 'show']);
    Route::patch('/admin/organization', [OrganizationController::class, 'upsert']);
    Route::delete('/admin/organization', [OrganizationController::class, 'delete']);

    Route::get('/admin/categories', [CategoryAdminController::class, 'index']);
    Route::post('/admin/categories', [CategoryAdminController::class, 'store']);
    Route::get('/admin/categories/{id}', [CategoryAdminController::class, 'show']);
    Route::patch('/admin/categories/{id}', [CategoryAdminController::class, 'update']);
    Route::delete('/admin/categories/{id}', [CategoryAdminController::class, 'destroy']);

    Route::get('/admin/products', [ProductAdminController::class, 'index']);
    Route::post('/admin/products', [ProductAdminController::class, 'store']);
    Route::get('/admin/products/{id}', [ProductAdminController::class, 'show']);
    Route::patch('/admin/products/{id}', [ProductAdminController::class, 'update']);
    Route::delete('/admin/products/{id}', [ProductAdminController::class, 'destroy']);

    Route::get('/admin/banners', [BannerAdminController::class, 'index']);
    Route::post('/admin/banners', [BannerAdminController::class, 'store']);
    Route::patch('/admin/banners/{id}', [BannerAdminController::class, 'update']);
    Route::delete('/admin/banners/{id}', [BannerAdminController::class, 'destroy']);

    Route::get('/admin/orders', [AdminOrderController::class, 'index']);
    Route::get('/admin/orders/{id}', [AdminOrderController::class, 'show']);
    Route::patch('/admin/orders/{id}', [AdminOrderController::class, 'update']);

    // Booking module intentionally excluded from migration.
    Route::get('/admin/dashboard/analytics', [DashboardController::class, 'analytics']);

    Route::get('/orders/me', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);

    Route::post('/orders/{id}/qpay/invoice', [OrderController::class, 'createQpayInvoice']);
    Route::get('/orders/{id}/qpay/status', [OrderController::class, 'checkQpayStatus']);
});

Route::prefix('v1')->group(function (): void {
    Route::get('/organization', [OrganizationController::class, 'show']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    Route::get('/products/customer', [ProductController::class, 'customer']);
    Route::get('/products/customer/{id}', [ProductController::class, 'show']);
    Route::get('/products/artist', [ProductController::class, 'artist']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/banners', [BannerController::class, 'index']);

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/register-email', [AuthController::class, 'registerWithEmail']);
    Route::post('/auth/register/verify-otp', [AuthController::class, 'verifyRegisterOtp']);
    Route::get('/auth/register/status/{registrationId}', [AuthController::class, 'registerStatus']);
    Route::get('/auth/verify-mn/callback', [AuthController::class, 'verifyMnCallback']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [AuthController::class, 'google']);
    Route::post('/auth/facebook', [AuthController::class, 'facebook']);
    Route::match(['get', 'post'], '/qpay/callback/{sender_invoice_no}', [QpayController::class, 'callback']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::post('/qpay/token', [QpayController::class, 'token']);

        Route::get('/users/me', [UserController::class, 'me']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::patch('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::get('/admin/organization', [OrganizationController::class, 'show']);
        Route::patch('/admin/organization', [OrganizationController::class, 'upsert']);
        Route::delete('/admin/organization', [OrganizationController::class, 'delete']);

        Route::get('/admin/categories', [CategoryAdminController::class, 'index']);
        Route::post('/admin/categories', [CategoryAdminController::class, 'store']);
        Route::get('/admin/categories/{id}', [CategoryAdminController::class, 'show']);
        Route::patch('/admin/categories/{id}', [CategoryAdminController::class, 'update']);
        Route::delete('/admin/categories/{id}', [CategoryAdminController::class, 'destroy']);

        Route::get('/admin/products', [ProductAdminController::class, 'index']);
        Route::post('/admin/products', [ProductAdminController::class, 'store']);
        Route::get('/admin/products/{id}', [ProductAdminController::class, 'show']);
        Route::patch('/admin/products/{id}', [ProductAdminController::class, 'update']);
        Route::delete('/admin/products/{id}', [ProductAdminController::class, 'destroy']);

        Route::get('/admin/banners', [BannerAdminController::class, 'index']);
        Route::post('/admin/banners', [BannerAdminController::class, 'store']);
        Route::patch('/admin/banners/{id}', [BannerAdminController::class, 'update']);
        Route::delete('/admin/banners/{id}', [BannerAdminController::class, 'destroy']);

        Route::get('/admin/orders', [AdminOrderController::class, 'index']);
        Route::get('/admin/orders/{id}', [AdminOrderController::class, 'show']);
        Route::patch('/admin/orders/{id}', [AdminOrderController::class, 'update']);

        Route::get('/admin/dashboard/analytics', [DashboardController::class, 'analytics']);

        Route::get('/orders/me', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);

        Route::post('/orders/{id}/qpay/invoice', [OrderController::class, 'createQpayInvoice']);
        Route::get('/orders/{id}/qpay/status', [OrderController::class, 'checkQpayStatus']);
    });
});
