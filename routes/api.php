<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\ProductDetailController;
use App\Http\Controllers\ImageProductController;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (NO AUTH)
|--------------------------------------------------------------------------
*/

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// Public user list (controller tự check admin)
Route::get('/users', [UserController::class, 'getAll']);

// Public product + attributes
Route::get('/products', [ProductController::class, 'products']);
Route::post('/products', [ProductController::class, 'addProduct']); // nếu muốn, chuyển vào admin
Route::post('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

Route::get('/product-details', [ProductDetailController::class, 'index']);
Route::get('/product-details/{id}', [ProductDetailController::class, 'show']);

// Categories (public read)
Route::get('/categories', [CategoriesController::class, 'index']);
Route::get('/categories/{id}', [CategoriesController::class, 'show']);

Route::get('/colors', [ColorController::class, 'index']);
Route::get('/colors/{id}', [ColorController::class, 'show']);
Route::get('/sizes', [SizeController::class, 'index']);
Route::get('/sizes/{id}', [SizeController::class, 'show']);

// Images
Route::get('/image-products', [ImageProductController::class, 'index']);
Route::post('/image-products', [ImageProductController::class, 'store']);
Route::get('/image-products/{id}', [ImageProductController::class, 'show']);
Route::match(['put','patch','post'], '/image-products/{id}', [ImageProductController::class, 'update']);
Route::delete('/image-products/{id}', [ImageProductController::class, 'destroy']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (auth:api)
|--------------------------------------------------------------------------
|
| Các route tạo sửa xóa cần auth sẽ nằm ở đây. Controller có thể tiếp tục
| kiểm tra quyền admin nếu cần (ví dụ: chỉ admin được tạo categories).
|
*/

Route::middleware('auth:api')->group(function () {

    // Auth user info
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/refresh', [UserController::class, 'refresh']);
    Route::get('/me',        [UserController::class, 'me']);
    Route::put('/me',        [UserController::class, 'updateMe']);

    // Product details (you can move to admin)
    Route::post('/product-details', [ProductDetailController::class, 'store']);
    Route::put('/product-details/{id}', [ProductDetailController::class, 'update']);
    Route::delete('/product-details/{id}', [ProductDetailController::class, 'destroy']);

    // Colors & Sizes (you can restrict to admin)
    Route::post('/colors', [ColorController::class, 'store']);
    Route::put('/colors/{id}', [ColorController::class, 'update']);
    Route::delete('/colors/{id}', [ColorController::class, 'destroy']);

    Route::post('/sizes', [SizeController::class, 'store']);
    Route::put('/sizes/{id}', [SizeController::class, 'update']);
    Route::delete('/sizes/{id}', [SizeController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    // Create order
    Route::post('/orders', [OrderController::class, 'store']);

    // Show order (owner or admin, controller check)
    Route::get('/orders/{id}', [OrderController::class, 'show']);

    // List orders (admin only, controller check)
    Route::get('/orders', [OrderController::class, 'index']);

    // CRUD (admin)
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // 👉 Route mới bạn muốn – admin lấy tất cả orders
    Route::get('/orders-all', [OrderController::class, 'getAll']);

    /*
    |---------------------------------------------------------------------------
    | Categories (protected: require auth for create/update/delete)
    | Note: controller can still verify admin role if needed.
    |---------------------------------------------------------------------------
    */
    Route::post('/categories', [CategoriesController::class, 'store']);
    Route::put('/categories/{id}', [CategoriesController::class, 'update']);
    Route::patch('/categories/{id}', [CategoriesController::class, 'update']);
    Route::delete('/categories/{id}', [CategoriesController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (OPTIONAL)
|--------------------------------------------------------------------------
| Bạn có thể bỏ hẳn group này nếu không dùng prefix /admin.
| Vì tất cả controller đã tự kiểm tra admin.
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->prefix('admin')->group(function () {

    // Users
    Route::get('/users', [UserController::class, 'getAll']);
    Route::post('/users', [UserController::class, 'createByAdmin']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
});
