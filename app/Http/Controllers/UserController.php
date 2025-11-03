<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

// Use JWT facade (tymon or php-open-source-saver)
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserController extends Controller
{
    /**
     * Lấy tất cả user
     */
    public function getAll()
    {
        return response()->json(User::all());
    }

    /**
     * Register - tạo user mới và trả token JWT
     */
    public function register(Request $request)
    {
        try {
            $data = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);

            Log::info('Registering user: ' . $data['email']);

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => bcrypt($data['password']),
            ]);

            Log::info('User registered with ID: ' . $user->id);

            $token = $this->createTokenForUser($user);

            return response()->json([
                'message'      => 'Register success',
                'user'         => $user,
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => $this->getTtlSeconds(),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Register error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Server error',
            ], 500);
        }
    }

    /**
     * Login - nhận email + password, trả token nếu đúng
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Thử dùng auth('api')->attempt
        try {
            $guard = auth('api');
            $token = null;

            if (is_object($guard) && method_exists($guard, 'attempt')) {
                $token = $guard->attempt($credentials);
            }

            // fallback to JWTAuth
            if (!$token) {
                $token = JWTAuth::attempt($credentials);
            }

            if (! $token) {
                return response()->json(['message' => 'Email hoặc mật khẩu không đúng'], 401);
            }

            return $this->respondWithToken($token);

        } catch (JWTException $e) {
            Log::error('JWT Exception on login: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi khi tạo token'], 500);
        } catch (\Throwable $e) {
            Log::error('Login error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Logout - invalidates token
     */
    public function logout(Request $request)
    {
        // try {
        //     $guard = auth('api');

        //     if (is_object($guard) && method_exists($guard, 'logout')) {
        //         $guard->logout();
        //     } else {
        //         $token = JWTAuth::getToken();
        //         if ($token) {
        //             JWTAuth::invalidate($token);
        //         }
        //     }

        //     return response()->json(['message' => 'Đã đăng xuất']);
        // } catch (JWTException $e) {
        //     Log::error('JWT logout error: '.$e->getMessage());
        //     return response()->json(['message' => 'Lỗi khi logout'], 500);
        // } catch (\Throwable $e) {
        //     Log::error('Logout error: '.$e->getMessage());
        //     return response()->json(['message' => 'Lỗi server'], 500);
        // }
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        // try {
        //     $guard = auth('api');

        //     if (is_object($guard) && method_exists($guard, 'refresh')) {
        //         $newToken = $guard->refresh();
        //     } else {
        //         $newToken = JWTAuth::refresh();
        //     }

        //     return $this->respondWithToken($newToken);
        // } catch (JWTException $e) {
        //     Log::error('JWT refresh error: '.$e->getMessage());
        //     return response()->json(['message' => 'Lỗi khi refresh token'], 500);
        // } catch (\Throwable $e) {
        //     Log::error('Refresh error: '.$e->getMessage());
        //     return response()->json(['message' => 'Lỗi server'], 500);
        // }
    }

    /**
     * Thông tin user hiện tại
     */
    public function me()
    {
        try {
            $guard = auth('api');

            if (is_object($guard) && method_exists($guard, 'user')) {
                $user = $guard->user();
            } else {
                $user = JWTAuth::user();
            }

            return response()->json($user);
        } catch (\Throwable $e) {
            Log::error('Me error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Helper: trả token với metadata
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $this->getTtlSeconds(),
            'user'         => $this->meUserForResponse(),
        ]);
    }

    /**
     * Tạo token cho user (login from user)
     */
    protected function createTokenForUser(User $user)
    {
        $guard = auth('api');

        if (is_object($guard) && method_exists($guard, 'login')) {
            return $guard->login($user);
        }

        return JWTAuth::fromUser($user);
    }

    /**
     * Lấy user cho response
     */
    protected function meUserForResponse()
    {
        $guard = auth('api');

        if (is_object($guard) && method_exists($guard, 'user')) {
            return $guard->user();
        }

        return JWTAuth::user();
    }

    /**
     * Lấy TTL token (giây)
     */
    protected function getTtlSeconds()
    {
        try {
            $guard = auth('api');
            if (is_object($guard) && method_exists($guard, 'factory')) {
                return $guard->factory()->getTTL() * 60;
            }
            return JWTAuth::factory()->getTTL() * 60;
        } catch (\Throwable $e) {
            return 60 * 60; // fallback 1 hour
        }
    }
}
