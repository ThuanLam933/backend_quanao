<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\ProductDetailController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartDetailController;
use App\Http\Controllers\ImageProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes grouped as:
| - Public (anyone)
| - Authenticated (auth:api) for normal authenticated users
| - Admin-only (auth:api + is_admin middleware) for management
|
| NOTE:
| - This file assumes you use an "api" guard that returns a user via auth('api') or JWTAuth.
| - Create and register `is_admin` middleware that returns 403 if authenticated user is not admin.
|
*/

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (no auth)
|--------------------------------------------------------------------------
*/
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// Public data endpoints
Route::get('/products', [ProductController::class, 'products']);
Route::post('/products',  [ProductController::class, 'addProduct']);
Route::post('/products/{id}', [ProductController::class, 'update'] ?? function(){});
Route::delete('/products/{id}', [ProductController::class, 'destroy'] ?? function(){});
Route::get('/product-details', [ProductDetailController::class, 'index']);
Route::get('/product-details/{id}', [ProductDetailController::class, 'show']);

Route::get('/categories', [CategoriesController::class, 'index']);
 Route::post('/categories', [CategoriesController::class, 'store'] ?? function(){});
    Route::put('/categories/{id}', [CategoriesController::class, 'update'] ?? function(){});
    Route::delete('/categories/{id}', [CategoriesController::class, 'destroy'] ?? function(){});
Route::get('/colors', [ColorController::class, 'index']);
Route::get('/colors/{id}', [ColorController::class, 'show']);
Route::get('/sizes', [SizeController::class, 'index']);
Route::get('/sizes/{id}', [SizeController::class, 'show']);

// Images - list & view are public (you can change to protected if required)
Route::get('/image-products', [ImageProductController::class, 'index']); // ?product_detail_id=...
Route::post('/image-products', [ImageProductController::class, 'store']);
Route::get('/image-products/{id}', [ImageProductController::class, 'show']);
Route::match(['put','patch','post'], '/image-products/{id}', [ImageProductController::class, 'update']);
Route::delete('/image-products/{id}', [ImageProductController::class, 'destroy']);

// Carts & cart details (guest + authenticated): public by default.
// If you want carts tied to users, move these into the auth group below.
Route::get('/carts', [CartController::class, 'index']);
Route::get('/carts/{id}', [CartController::class, 'show']);
Route::post('/carts', [CartController::class, 'store']);
Route::put('/carts/{id}', [CartController::class, 'update']);
Route::delete('/carts/{id}', [CartController::class, 'destroy']);

Route::get('/cart-details', [CartDetailController::class, 'index']);
Route::get('/cart-details/{id}', [CartDetailController::class, 'show']);
Route::post('/cart-details', [CartDetailController::class, 'store']);
Route::put('/cart-details/{id}', [CartDetailController::class, 'update']);
Route::delete('/cart-details/{id}', [CartDetailController::class, 'destroy']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (auth:api)
|--------------------------------------------------------------------------
| Routes available to any authenticated user.
*/
Route::middleware('auth:api')->group(function () {
    // Auth helpers
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/refresh', [UserController::class, 'refresh']);
    Route::get('/me', [UserController::class, 'me']);

    // Product details (authenticated can create/update/delete if you want)
    Route::post('/product-details', [ProductDetailController::class, 'store']);
    Route::put('/product-details/{id}', [ProductDetailController::class, 'update']);
    Route::delete('/product-details/{id}', [ProductDetailController::class, 'destroy']);

    // Colors & Sizes (allow create for authenticated - consider restricting to admin)
    Route::post('/colors', [ColorController::class, 'store']);
    Route::put('/colors/{id}', [ColorController::class, 'update']);
    Route::delete('/colors/{id}', [ColorController::class, 'destroy']);

    Route::post('/sizes', [SizeController::class, 'store']);
    Route::put('/sizes/{id}', [SizeController::class, 'update']);
    Route::delete('/sizes/{id}', [SizeController::class, 'destroy']);

    // If you want carts / cart-details only for logged-in users:
    // uncomment these and remove the public ones above.
    // Route::post('/carts', [CartController::class, 'store']);
    // Route::put('/carts/{id}', [CartController::class, 'update']);
    // Route::delete('/carts/{id}', [CartController::class, 'destroy']);
    //
    // Route::post('/cart-details', [CartDetailController::class, 'store']);
    // Route::put('/cart-details/{id}', [CartDetailController::class, 'update']);
    // Route::delete('/cart-details/{id}', [CartDetailController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (auth:api + is_admin)
|--------------------------------------------------------------------------
| Routes only accessible by admins. Use prefix '/admin' for clarity.
| Make sure to create `is_admin` middleware.
*/
Route::middleware(['auth:api', 'is_admin'])->prefix('admin')->group(function () {
    // User management
    Route::get('/users', [UserController::class, 'getAll']); // list users
    Route::post('/users', [UserController::class, 'createByAdmin']); // create user with role
    Route::put('/users/{id}', [UserController::class, 'updateByAdmin'] ?? [UserController::class, 'update']); // implement if available
    Route::delete('/users/{id}', [UserController::class, 'destroy'] ?? function($id){ return response()->json(['message'=>'Not implemented'],501); });

    // Product management (full CRUD)


    // Categories management
    Route::post('/categories', [CategoriesController::class, 'store'] ?? function(){});
    Route::put('/categories/{id}', [CategoriesController::class, 'update'] ?? function(){});
    Route::delete('/categories/{id}', [CategoriesController::class, 'destroy'] ?? function(){});



    // Orders / Returns / Stock / Comments endpoints (admin)
    // Add controllers for returns/stock/comments if exist
    Route::get('/orders', [CartController::class, 'index']);
    Route::put('/orders/{id}', [CartController::class, 'update']);
    // returns, stock-entries, comments: if you implement controllers, add them here
});
