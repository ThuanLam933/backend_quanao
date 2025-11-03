<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\ProductDetailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Đây là file routes/api.php — các route ở đây sẽ được tiền tố /api bởi
| RouteServiceProvider.
|
*/

// Public routes
Route::get('/user', [UserController::class, 'getAll']);
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::get('/products', [ProductController::class, 'products']);
Route::post('/addProduct', [ProductController::class, 'addProduct']);

// Categories
Route::get('/categories', [CategoriesController::class, 'index']);
Route::post('/categories', [CategoriesController::class, 'store']);

// Colors (public GET, public POST, protected update/delete)
Route::get('/colors', [ColorController::class, 'index']);
Route::get('/colors/{id}', [ColorController::class, 'show']);
Route::post('/colors', [ColorController::class, 'store']);

// Sizes (public GET, public POST, protected update/delete)
Route::get('/sizes', [SizeController::class, 'index']);
Route::get('/sizes/{id}', [SizeController::class, 'show']);
Route::post('/sizes', [SizeController::class, 'store']);

//product_detail
Route::get('/product-details', [ProductDetailController::class, 'index']);
Route::get('/product-details/{id}', [ProductDetailController::class, 'show']);
Route::post('/product-details', [ProductDetailController::class, 'store']);


// Protected routes (JWT - guard 'api')
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/refresh', [UserController::class, 'refresh']);
    Route::get('/me', [UserController::class, 'me']);

    // Colors - chỉ user auth mới sửa/xóa
    Route::put('/colors/{id}', [ColorController::class, 'update']);
    Route::delete('/colors/{id}', [ColorController::class, 'destroy']);

    // Sizes - chỉ user auth mới sửa/xóa
    Route::put('/sizes/{id}', [SizeController::class, 'update']);
    Route::delete('/sizes/{id}', [SizeController::class, 'destroy']);

    //product_detail 
    Route::put('/product-details/{id}', [ProductDetailController::class, 'update']);
    Route::delete('/product-details/{id}', [ProductDetailController::class, 'destroy']);

    // Nếu sau này bạn muốn chỉ user đã auth mới được tạo category,
    // di chuyển dòng POST /categories vào đây (bỏ dòng public phía trên).
});
