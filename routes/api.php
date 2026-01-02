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
use App\Http\Controllers\OrderDetailController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Controllers\ProductDiscountController;
use App\Http\Controllers\DiscountController;


/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (NO AUTH)
|--------------------------------------------------------------------------
*/

Route::post('/vnpay_create_payment', [App\Http\Controllers\VnPayController::class, 'createPayment']);
Route::get('/vnpay_return', [App\Http\Controllers\VnPayController::class, 'vnpayReturn']);
// Auth
Route::post('/register', [UserController::class, 'register']);
Route::post('/login',    [UserController::class, 'login']);

// Public users list (controller tự check admin)
Route::get('/users', [UserController::class, 'getAll']);

// Products
Route::get('/products',        [ProductController::class, 'products']);
Route::get('/products/{id}',   [ProductController::class, 'show']);
Route::post('/products',       [ProductController::class, 'addProduct']);
Route::post('/products/{id}',  [ProductController::class, 'update']);
Route::delete('/products/{id}',[ProductController::class, 'destroy']);

// Product details
Route::get('/product-details',      [ProductDetailController::class, 'index']);
Route::get('/product-details/{id}', [ProductDetailController::class, 'show']);

// Categories
Route::get('/categories',         [CategoriesController::class, 'index']);
Route::get('/categories/{id}',    [CategoriesController::class, 'show']);

// Colors & Sizes
Route::get('/colors',       [ColorController::class, 'index']);
Route::get('/colors/{id}',  [ColorController::class, 'show']);
Route::get('/sizes',        [SizeController::class, 'index']);
Route::get('/sizes/{id}',   [SizeController::class, 'show']);

// Images
Route::get('/image-products',         [ImageProductController::class, 'index']);
Route::post('/image-products',        [ImageProductController::class, 'store']);
Route::get('/image-products/{id}',    [ImageProductController::class, 'show']);
Route::match(['put','patch','post'], '/image-products/{id}', [ImageProductController::class, 'update']);
Route::delete('/image-products/{id}', [ImageProductController::class, 'destroy']);

// Returns (public read)
Route::get('/returns',    [ReturnRequestController::class, 'index']);
Route::get('/returns/{id}', [ReturnRequestController::class, 'show']);
Route::post('/discounts/apply', [DiscountController::class, 'apply']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (auth:api)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {

    // User actions
    Route::post('/logout',  [UserController::class, 'logout']);
    Route::post('/refresh', [UserController::class, 'refresh']);
    Route::get('/me',       [UserController::class, 'me']);
    Route::put('/me',       [UserController::class, 'updateMe']);
    Route::put('/change-password', [UserController::class, 'changePassword']);

    // Product details (protected)
    Route::post('/product-details',          [ProductDetailController::class, 'store']);
    Route::put('/product-details/{id}',      [ProductDetailController::class, 'update']);
    Route::patch('/product-details/{id}',    [ProductDetailController::class, 'update']);
    Route::delete('/product-details/{id}',   [ProductDetailController::class, 'destroy']);

    // Colors
    Route::post('/colors',       [ColorController::class, 'store']);
    Route::put('/colors/{id}',   [ColorController::class, 'update']);
    Route::delete('/colors/{id}',[ColorController::class, 'destroy']);

    // Sizes
    Route::post('/sizes',       [SizeController::class, 'store']);
    Route::put('/sizes/{id}',   [SizeController::class, 'update']);
    Route::delete('/sizes/{id}',[SizeController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    // User's own orders
    Route::get('/my-orders', [OrderController::class, 'myOrders']);

    // CRUD orders
    Route::get('/orders',      [OrderController::class, 'index']);
    Route::post('/orders',     [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // Admin all orders (used in admin panel)
    Route::get('/orders-all', [OrderController::class, 'getAll']);

    /*
    |--------------------------------------------------------------------------
    | Order Details (NEW)
    |--------------------------------------------------------------------------
    */

    Route::apiResource('order-details', OrderDetailController::class);

    /*
    |--------------------------------------------------------------------------
    | Categories (protected)
    |--------------------------------------------------------------------------
    */

    Route::post('/categories',              [CategoriesController::class, 'store']);
    Route::put('/categories/{id}',          [CategoriesController::class, 'update']);
    Route::patch('/categories/{id}',        [CategoriesController::class, 'update']);
    Route::delete('/categories/{id}',       [CategoriesController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Returns (protected write)
    |--------------------------------------------------------------------------
    */
    


    Route::post('/returns',              [ReturnRequestController::class, 'store']);
    Route::put('/returns/{id}',          [ReturnRequestController::class, 'update']);
    Route::delete('/returns/{id}',       [ReturnRequestController::class, 'destroy']);
});


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (auth + prefix /admin)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->prefix('admin')->group(function () {

    // Users
    Route::get('/users',  [UserController::class, 'getAll']);
    Route::post('/users', [UserController::class, 'createByAdmin']);

    // Orders
    Route::get('/orders',        [OrderController::class, 'index']);
    Route::put('/orders/{id}',   [OrderController::class, 'update']);
    Route::delete('/orders/{id}',[OrderController::class, 'destroy']);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Receipts
    Route::get('/receipts',           [ReceiptController::class, 'index']);
    Route::post('/receipts',          [ReceiptController::class, 'store']);
    Route::get('/receipts/{receipt}', [ReceiptController::class, 'show']);
    Route::delete('/receipts/{receipt}', [ReceiptController::class, 'destroy']);

    // Inventory
    Route::get('/inventory/logs',               [InventoryController::class, 'index']);
    Route::post('/inventory/adjust',            [InventoryController::class, 'adjust']);
    Route::post('/inventory/logs',              [InventoryController::class, 'createLogOnly']);
    Route::post('/inventory/revert-receipt/{receiptId}', [InventoryController::class, 'revertReceipt']);

    // Admin Returns
    Route::get('/returns',            [ReturnRequestController::class, 'index']);
    Route::put('/returns/{id}',       [ReturnRequestController::class, 'update']);
    Route::delete('/returns/{id}',    [ReturnRequestController::class, 'destroy']);

    /*
|--------------------------------------------------------------------------
| Product Discounts (protected)
|--------------------------------------------------------------------------
*/

Route::get('/product-discounts',        [ProductDiscountController::class, 'index']);
Route::post('/product-discounts',       [ProductDiscountController::class, 'store']);
Route::get('/product-discounts/{productDiscount}',   [ProductDiscountController::class, 'show']);
Route::put('/product-discounts/{productDiscount}',   [ProductDiscountController::class, 'update']);
Route::delete('/product-discounts/{productDiscount}',[ProductDiscountController::class, 'destroy']);

Route::get('/discounts',        [DiscountController::class, 'index']);
Route::post('/discounts',       [DiscountController::class, 'store']);
Route::get('/discounts/{id}',   [DiscountController::class, 'show']);
Route::put('/discounts/{id}',   [DiscountController::class, 'update']);
Route::delete('/discounts/{id}',[DiscountController::class, 'destroy']);
});
