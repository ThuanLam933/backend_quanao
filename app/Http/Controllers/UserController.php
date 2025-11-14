<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserController extends Controller
{
    /**
     * ----------------------------------------------------
     * Helper: Chỉ cho admin truy cập (dùng trong admin routes)
     * ----------------------------------------------------
     */
    protected function ensureAdmin()
    {
        try {
            $user = auth('api')->user() ?? JWTAuth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401)->send();
            }

            if (($user->role ?? '') !== 'admin') {
                return response()->json(['message' => 'Forbidden'], 403)->send();
            }

        } catch (\Throwable $e) {
            Log::error('ensureAdmin error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error'], 500)->send();
        }
    }


    /**
     * ----------------------------------------------------
     * Lấy tất cả user (admin only)
     * ----------------------------------------------------
     */
    public function getAll(Request $request)
    {
        // Kiểm tra quyền admin
        $check = $this->ensureAdmin();
        if ($check) return $check;

        try {
            $users = User::all();
            return response()->json($users, 200);

        } catch (\Throwable $e) {
            Log::error('getAll users error: '.$e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }


    /**
     * ----------------------------------------------------
     * Update thông tin user hiện tại
     * ----------------------------------------------------
     */
    public function updateMe(Request $request)
    {
        try {
            $user = $request->user() ?? JWTAuth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $data = $request->only(['name', 'email', 'phone']);

            $rules = [
                'name'  => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'sometimes|string|max:30',
            ];

            $validated = Validator::make($data, $rules);

            if ($validated->fails()) {
                return response()->json(['message' => 'Validation failed', 'errors' => $validated->errors()], 422);
            }

            $user->fill($validated->validated());
            $user->save();

            return response()->json($user, 200);

        } catch (\Throwable $e) {
            Log::error('updateMe error: '.$e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }


    /**
     * ----------------------------------------------------
     * Register user — mặc định role=user
     * ----------------------------------------------------
     */
    public function register(Request $request)
    {
        try {
            $data = $request->validate([
                'name'     => 'required|string|max:255',
                'phone'    => 'nullable|string|max:30',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);

            Log::info('Registering user: ' . $data['email']);

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => bcrypt($data['password']),
                'phone'    => $data['phone'] ?? null,
                'role'     => 'user',
                'status'   => 1,
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
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Register error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }


    /**
     * ----------------------------------------------------
     * Admin-only: tạo user
     * ----------------------------------------------------
     */
    public function createByAdmin(Request $request)
    {
        // Kiểm tra quyền admin
        $check = $this->ensureAdmin();
        if ($check) return $check;

        try {
            $data = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'phone'    => 'nullable|string|max:30',
                'role'     => 'nullable|string|in:user,admin',
                'status'   => 'nullable|integer',
            ]);

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => bcrypt($data['password']),
                'phone'    => $data['phone'] ?? null,
                'role'     => $data['role'] ?? 'user',
                'status'   => $data['status'] ?? 1,
            ]);

            return response()->json(['message' => 'User created by admin', 'user' => $user], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('createByAdmin error: '.$e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }


    /**
     * ----------------------------------------------------
     * Login
     * ----------------------------------------------------
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        try {
            $guard = auth('api');
            $token = $guard->attempt($credentials) ?: JWTAuth::attempt($credentials);

            if (!$token) {
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
     * ----------------------------------------------------
     * Logout
     * ----------------------------------------------------
     */
    public function logout(Request $request)
    {
        try {
            $guard = auth('api');
            if (method_exists($guard, 'logout')) {
                $guard->logout();
            } else {
                $token = JWTAuth::getToken();
                if ($token) JWTAuth::invalidate($token);
            }

            return response()->json(['message' => 'Đã đăng xuất']);

        } catch (JWTException $e) {
            Log::error('JWT logout error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi khi logout'], 500);

        } catch (\Throwable $e) {
            Log::error('Logout error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }


    /**
     * ----------------------------------------------------
     * Refresh Token
     * ----------------------------------------------------
     */
    public function refresh()
    {
        try {
            $guard = auth('api');
            $newToken = $guard->refresh() ?? JWTAuth::refresh();
            return $this->respondWithToken($newToken);

        } catch (JWTException $e) {
            Log::error('JWT refresh error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi khi refresh token'], 500);
        } catch (\Throwable $e) {
            Log::error('Refresh error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }


    /**
     * ----------------------------------------------------
     * Me
     * ----------------------------------------------------
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user() ?? JWTAuth::user();
            return response()->json($user);

        } catch (\Throwable $e) {
            Log::error('Me error: '.$e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }




    /* ====================================================
       TOKEN HELPERS
       ==================================================== */

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $this->getTtlSeconds(),
            'user'         => $this->meUserForResponse(),
        ]);
    }


    protected function createTokenForUser(User $user)
    {
        $guard = auth('api');
        return $guard->login($user) ?: JWTAuth::fromUser($user);
    }


    protected function meUserForResponse()
    {
        $guard = auth('api');
        return $guard->user() ?? JWTAuth::user();
    }


    protected function getTtlSeconds()
    {
        try {
            $guard = auth('api');
            return $guard->factory()->getTTL() * 60;
        } catch (\Throwable $e) {
            return 3600; // fallback 1h
        }
    }
}
