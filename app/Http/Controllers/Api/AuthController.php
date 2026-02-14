<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\MobileRegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\City;
use App\Models\LoginAttempt;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $userRole = Role::where('name', 'user')->first();
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'language' => $request->language ?? 'ar',
            'role_id' => $userRole->id,
            'city' => $request->city ?? null,
            'country' => 'مصر', // Default to Egypt
        ]);

        $user->load('role');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * تسجيل مستخدم من تطبيق الموبايل (يدعم اختيار المحافظة والمدينة من اند بوينت المحافظات/المدن)
     */
    public function registerMobile(MobileRegisterRequest $request): JsonResponse
    {
        $userRole = Role::where('name', 'user')->first();

        $cityName = null;
        if ($request->city_id) {
            $city = City::find($request->city_id);
            $cityName = $city ? $city->name_ar : null;
        }
        if ($cityName === null && $request->city) {
            $cityName = $request->city;
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'language' => $request->language ?? 'ar',
            'role_id' => $userRole->id,
            'country' => 'مصر',
            'city' => $cityName,
            'city_id' => $request->city_id,
            'governorate_id' => $request->governorate_id ?? ($request->city_id ? City::find($request->city_id)?->governorate_id : null),
            'gender' => $request->gender,
        ]);

        $user->load('role');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'phone', 'password']);
        
        // Find user by email or phone
        $user = null;
        if ($request->email) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->phone) {
            $user = User::where('phone', $request->phone)->first();
        }

        // Log login attempt
        LoginAttempt::create([
            'user_id' => $user ? $user->id : null,
            'email' => $request->email,
            'phone' => $request->phone,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success' => false,
        ]);

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Update login attempt as successful
        $loginAttempt = LoginAttempt::where('user_id', $user->id)
            ->where('email', $request->email ?? null)
            ->where('phone', $request->phone ?? null)
            ->latest()
            ->first();
        if ($loginAttempt) {
            $loginAttempt->update(['success' => true]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Log login activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->logLogin($user->id);

        // Track device
        if ($request->has('device_id')) {
            \App\Models\UserDevice::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_id' => $request->device_id,
                ],
                [
                    'device_type' => $request->get('device_type', 'web'),
                    'device_name' => $request->get('device_name'),
                    'os_version' => $request->get('os_version'),
                    'app_version' => $request->get('app_version'),
                    'fcm_token' => $request->get('fcm_token'),
                    'ip_address' => $request->ip(),
                    'last_active_at' => now(),
                    'is_active' => true,
                ]
            );
        }

        $user->load('role');

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        // Log logout activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->logLogout($user->id);

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Request OTP
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required_without:phone|email|exists:users,email',
            'phone' => 'required_without:email|string|exists:users,phone',
        ]);

        $user = null;
        if ($request->email) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->phone) {
            $user = User::where('phone', $request->phone)->first();
        }

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via email queue
        \App\Jobs\SendOtpEmail::dispatch($user, $otp, $user->language ?? 'ar');

        return response()->json([
            'message' => 'OTP sent successfully',
        ]);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required_without:phone|email|exists:users,email',
            'phone' => 'required_without:email|string|exists:users,phone',
            'otp' => 'required|string|size:6',
        ]);

        $user = null;
        if ($request->email) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->phone) {
            $user = User::where('phone', $request->phone)->first();
        }

        $otpValid = $user && $user->otp_code === $request->otp && $user->otp_expires_at && $user->otp_expires_at >= now();
        // للتجربة: قبول 123456 عند تفعيل OTP_TEST_BYPASS=true في .env
        if (!$otpValid && $user && (bool) env('OTP_TEST_BYPASS', false) && $request->otp === '123456') {
            $otpValid = true;
        }
        if (!$user || !$otpValid) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'email_verified_at' => now(),
        ]);

        $user->load('role');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'OTP verified successfully',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Register merchant
     */
    public function registerMerchant(\App\Http\Requests\MerchantRegisterRequest $request): JsonResponse
    {
        $merchantRole = Role::where('name', 'merchant')->first();
        
        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'language' => $request->language ?? 'ar',
            'role_id' => $merchantRole->id,
            'city' => $request->city ?? null,
            'country' => 'مصر', // Default to Egypt
        ]);

        // Create merchant (pending approval)
        $merchant = \App\Models\Merchant::create([
            'user_id' => $user->id,
            'company_name' => $request->company_name,
            'company_name_ar' => $request->company_name_ar,
            'company_name_en' => $request->company_name_en,
            'description' => $request->description,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'address' => $request->address,
            'address_ar' => $request->address_ar,
            'address_en' => $request->address_en,
            'phone' => $request->phone_merchant,
            'whatsapp_link' => $request->whatsapp_link,
            'city' => $request->city ?? null,
            'country' => 'مصر', // Default to Egypt
            'approved' => false, // Requires admin approval
        ]);

        $user->load('role');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Merchant registered successfully. Waiting for admin approval.',
            'user' => new UserResource($user),
            'merchant' => $merchant,
            'token' => $token,
        ], 201);
    }

    /**
     * Merchant login with PIN
     */
    public function loginWithPin(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'pin' => 'required|string|size:4',
        ]);

        $merchant = \App\Models\Merchant::findOrFail($request->merchant_id);
        $merchantPin = \App\Models\MerchantPin::where('merchant_id', $merchant->id)->first();

        if (!$merchantPin) {
            return response()->json([
                'message' => 'PIN not set for this merchant',
            ], 400);
        }

        if ($merchantPin->isLocked()) {
            return response()->json([
                'message' => 'Account is locked. Try again later.',
                'locked_until' => $merchantPin->locked_until->toIso8601String(),
            ], 423);
        }

        if (!$merchantPin->verifyPin($request->pin)) {
            return response()->json([
                'message' => 'Invalid PIN',
                'failed_attempts' => $merchantPin->failed_attempts,
            ], 401);
        }

        $user = $merchant->user;
        $user->load('role');
        $token = $user->createToken('auth_token')->plainTextToken;

        // Log login
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->logLogin($user->id);

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'merchant' => $merchant,
            'token' => $token,
        ]);
    }
}
