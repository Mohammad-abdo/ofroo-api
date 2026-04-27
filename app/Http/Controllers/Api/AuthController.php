<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\MobileRegisterRequest;
use App\Http\Resources\UserResource;
use App\Jobs\SendOtpEmail;
use App\Jobs\SendOtpPhoneNotification;
use App\Models\City;
use App\Models\LoginAttempt;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

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

        $pair = ApiTokenService::issuePair($user);

        return response()->json(ApiTokenService::mergeTokenResponse([
            'message' => 'User registered successfully',
            'user' => new UserResource($user),
        ], $pair), 201);
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

        $user->load(['role', 'cityRelation', 'governorateRelation']);

        $pair = ApiTokenService::issuePair($user);

        return response()->json(ApiTokenService::mergeTokenResponse([
            'message' => 'User registered successfully',
            'user' => new UserResource($user),
        ], $pair), 201);
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

        $user->load(['role', 'activeMerchantStaff']);

        $pair = ApiTokenService::issuePair($user);

        return response()->json(ApiTokenService::mergeTokenResponse([
            'message' => 'Login successful',
            'user' => new UserResource($user),
        ], $pair));
    }

    /**
     * Logout user. Requires auth (Bearer token). Returns 401 with message_ar/message_en if not authenticated.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated. You must be logged in to logout.',
                'message_ar' => 'يجب تسجيل الدخول. الرجاء تسجيل الدخول أولاً.',
                'message_en' => 'You must be logged in to logout.',
            ], 401);
        }

        $user->currentAccessToken()->delete();

        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->logLogout($user->id);

        return response()->json([
            'message' => 'Logged out successfully',
            'message_ar' => 'تم تسجيل الخروج بنجاح',
            'message_en' => 'Logged out successfully',
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

        if ($request->email && $request->phone) {
            return response()->json(['message' => 'Send either email or phone, not both'], 422);
        }

        $user = $request->email
            ? User::where('email', $request->email)->first()
            : User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $resendKey = 'otp-resend:'.$user->id;
        if (Cache::has($resendKey)) {
            return response()->json([
                'message' => 'Please wait before requesting another OTP',
                'message_ar' => 'يرجى الانتظار قبل طلب رمز تحقق جديد',
                'message_en' => 'Please wait before requesting another OTP',
            ], 429);
        }

        if (config('otp.test_mode')) {
            $otp = (string) config('otp.test_code', '123456');
        } else {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        // Store plain 6-digit OTP: column is VARCHAR(10); bcrypt hashes do not fit without widening the column.
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        Cache::put(
            $resendKey,
            true,
            now()->addSeconds((int) config('otp.resend_cooldown_seconds', 45))
        );

        if ($request->filled('phone')) {
            SendOtpPhoneNotification::dispatch($user->id, $otp, $user->language ?? 'ar');
        } else {
            SendOtpEmail::dispatch($user, $otp, $user->language ?? 'ar');
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'message_ar' => 'تم إرسال رمز التحقق',
            'message_en' => 'OTP sent successfully',
            'otp' => config('app.debug') ? $otp : null,
        ]);
    }
    /**
     * Verify OTP: توكن في الهيدر أو phone/email في الـ body + otp. 123456 مقبول دائماً للتجربة.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => 'required|string|size:6',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $user = null;
        $tokenString = $request->bearerToken();
        if ($tokenString) {
            $tokenString = trim($tokenString);
            $accessToken = PersonalAccessToken::findToken($tokenString);
            if ($accessToken) {
                $tokenable = $accessToken->tokenable;
                if ($tokenable instanceof User) {
                    $user = $tokenable;
                }
            }
        }

        if (!$user && ($request->filled('phone') || $request->filled('email'))) {
            $user = $request->phone
                ? User::where('phone', $request->phone)->first()
                : User::where('email', $request->email)->first();
        }

        if (!$user) {
            return response()->json([
                'message' => 'User not found. Send valid Bearer token in Authorization header, or phone/email in body.',
            ], 404);
        }

        $testBypass = (bool) config('app.otp_test_bypass')
            && $request->otp === (string) config('otp.test_code', '123456');

        $otpValid = $testBypass || (
            $user->otp_code
            && $user->otp_expires_at
            && $user->otp_expires_at->greaterThanOrEqualTo(now())
            && $this->otpMatchesStored($user, (string) $request->otp)
        );

        if (! $otpValid) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'email_verified_at' => now(),
        ]);

        $user->load('role');
        $pair = ApiTokenService::issuePair($user);

        return response()->json(ApiTokenService::mergeTokenResponse([
            'message' => 'OTP verified successfully',
            'user' => new UserResource($user),
        ], $pair));
    }

    /**
     * Exchange a valid (non-expired) refresh token for a new access + refresh pair.
     * The old refresh token is revoked (one-time rotation).
     *
     * Body: { "refresh_token": "..." } or header: Authorization: Bearer &lt;refresh_token&gt;
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $plain = $request->input('refresh_token');
        if (! is_string($plain) || trim($plain) === '') {
            $bearer = $request->bearerToken();
            $plain = is_string($bearer) ? trim($bearer) : '';
        } else {
            $plain = trim($plain);
        }

        if ($plain === '') {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'refresh_token' => ['The refresh token field is required (body refresh_token or Authorization: Bearer).'],
                ],
            ], 422);
        }

        $pat = PersonalAccessToken::findToken($plain);
        if (! $pat || ! ($pat->tokenable instanceof User)) {
            return response()->json([
                'message' => 'Invalid or expired refresh token',
                'message_ar' => 'رمز التحديث غير صالح أو منتهٍ.',
                'error' => 'invalid_refresh_token',
            ], 401);
        }

        if ($pat->name !== ApiTokenService::NAME_REFRESH) {
            return response()->json([
                'message' => 'Invalid token type. Send a refresh token, not an access token.',
                'message_ar' => 'أرسل refresh_token وليس توكن الوصول.',
                'error' => 'invalid_token_type',
            ], 422);
        }

        if ($pat->expires_at && $pat->expires_at->isPast()) {
            $pat->delete();

            return response()->json([
                'message' => 'Invalid or expired refresh token',
                'message_ar' => 'رمز التحديث غير صالح أو منتهٍ.',
                'error' => 'invalid_refresh_token',
            ], 401);
        }

        /** @var User $user */
        $user = $pat->tokenable;
        $pat->delete();

        $pair = ApiTokenService::issuePair($user);
        $user->load(['role', 'activeMerchantStaff']);

        return response()->json([
            'message' => 'Token refreshed',
            'message_ar' => 'تم تحديث الجلسة',
            'message_en' => 'Token refreshed',
            'token' => $pair['access_token'],
            'refresh_token' => $pair['refresh_token'],
            'expires_in' => $pair['expires_in'],
            'token_type' => 'Bearer',
            'refresh_expires_at' => $pair['refresh_expires_at'],
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Register merchant
     *
     * New accounts: create user + merchant. Existing accounts: same email+phone, verify password,
     * upgrade role to merchant, create or update merchant row (re-application allowed).
     */
    public function registerMerchant(\App\Http\Requests\MerchantRegisterRequest $request): JsonResponse
    {
        $merchantRole = Role::where('name', 'merchant')->first();
        if (! $merchantRole) {
            return response()->json([
                'message' => 'Merchant role not found. Please run RoleSeeder.',
            ], 422);
        }

        $email = strtolower(trim((string) $request->email));
        $phone = trim((string) $request->phone);

        $byEmail = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $byPhone = User::where('phone', $phone)->first();

        if ($byEmail && $byPhone && $byEmail->id !== $byPhone->id) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'email' => ['البريد ورقم الهاتف مرتبطان بحسابين مختلفين.'],
                    'phone' => ['البريد ورقم الهاتف مرتبطان بحسابين مختلفين.'],
                ],
            ], 422);
        }

        $existingUser = $byEmail ?? $byPhone;
        $storedPhone = '';

        if ($existingUser) {
            if (strcasecmp(trim((string) $existingUser->email), trim((string) $request->email)) !== 0) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'email' => ['البريد لا يطابق الحساب المرتبط بهذا الرقم.'],
                    ],
                ], 422);
            }
            $storedPhone = trim((string) ($existingUser->phone ?? ''));
            if ($storedPhone !== '' && $storedPhone !== $phone) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'phone' => ['رقم الهاتف لا يطابق الحساب المرتبط بهذا البريد.'],
                    ],
                ], 422);
            }
            if (! Hash::check($request->password, $existingUser->getAuthPassword())) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'password' => ['كلمة المرور غير صحيحة لهذا الحساب.'],
                    ],
                ], 422);
            }
        }

        $resolvedCompanyName = $request->company_name
            ?: $request->company_name_ar
            ?: $request->company_name_en
            ?: '';

        $addressAr = $request->address_ar ?? $request->address;
        $addressEn = $request->address_en;

        $merchantPayload = [
            'company_name' => $resolvedCompanyName,
            'company_name_ar' => $request->company_name_ar,
            'company_name_en' => $request->company_name_en,
            'description' => null,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'address' => null,
            'address_ar' => $addressAr,
            'address_en' => $addressEn,
            'phone' => $request->phone_merchant,
            'whatsapp_link' => $request->whatsapp_link,
            'category_id' => $request->category_id,
            'mall_id' => $request->mall_id ?? null,
            'branches_number' => $request->branches_number,
            'city' => $request->city ?? null,
            'country' => 'مصر',
            'accepted_terms' => true,
        ];

        $user = DB::transaction(function () use ($request, $merchantRole, $existingUser, $phone, $merchantPayload, $storedPhone) {
            if ($existingUser) {
                $user = $existingUser->fresh();
                $userUpdates = [
                    'name' => $request->name,
                    'language' => $request->language ?? $user->language ?? 'ar',
                    'role_id' => $merchantRole->id,
                    'city' => $request->city ?? $user->city,
                    'country' => 'مصر',
                ];
                if ($storedPhone === '') {
                    $userUpdates['phone'] = $phone;
                }
                $user->update($userUpdates);

                $merchant = $user->merchant;
                if ($merchant) {
                    $payload = $merchantPayload;
                    if (! $merchant->approved) {
                        $payload['approved'] = false;
                    }
                    $merchant->update($payload);
                } else {
                    Merchant::create(array_merge($merchantPayload, [
                        'user_id' => $user->id,
                        'approved' => false,
                    ]));
                }

                return $user->fresh();
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $phone,
                'password' => Hash::make($request->password),
                'language' => $request->language ?? 'ar',
                'role_id' => $merchantRole->id,
                'city' => $request->city ?? null,
                'country' => 'مصر',
            ]);

            Merchant::create(array_merge($merchantPayload, [
                'user_id' => $user->id,
                'approved' => false,
            ]));

            return $user;
        });

        $user->load('role');

        $pair = ApiTokenService::issuePair($user);

        return response()->json(ApiTokenService::mergeTokenResponse([
            'success' => true,
            'message' => 'تم إرسال طلبك بنجاح',
            'message_ar' => 'تم إرسال طلبك بنجاح، سيتم مراجعته من قِبَل الإدارة وستصلك إشعار بالقرار.',
            'message_en' => 'Your request has been submitted successfully. It will be reviewed by the admin and you will be notified of the decision.',
            'user' => new UserResource($user),
            'existing_account' => (bool) $existingUser,
        ], $pair), 201);
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
        $pair = ApiTokenService::issuePair($user);

        // Log login
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->logLogin($user->id);

        $tokenPayload = ApiTokenService::mergeTokenResponse([], $pair);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'message_ar' => 'تم تسجيل الدخول بنجاح',
            'message_en' => 'Login successful',
            'token' => $tokenPayload['token'],
            'refresh_token' => $tokenPayload['refresh_token'],
            'expires_in' => $tokenPayload['expires_in'],
            'token_type' => $tokenPayload['token_type'],
            'refresh_expires_at' => $tokenPayload['refresh_expires_at'],
            'data' => [
                'token' => $tokenPayload['token'],
                'refresh_token' => $tokenPayload['refresh_token'],
                'expires_in' => $tokenPayload['expires_in'],
                'token_type' => $tokenPayload['token_type'],
                'refresh_expires_at' => $tokenPayload['refresh_expires_at'],
                'user' => (new UserResource($user))->resolve(),
                'merchant' => [
                    'id' => $merchant->id,
                    'company_name' => $merchant->company_name,
                    'company_name_ar' => $merchant->company_name_ar,
                    'company_name_en' => $merchant->company_name_en,
                    'approved' => (bool) $merchant->approved,
                    'status' => $merchant->status ?? null,
                ],
            ],
            'user' => new UserResource($user),
            'merchant' => $merchant,
        ]);
    }

    /**
     * Legacy rows may store bcrypt; new requests store plain 6-digit OTP (fits VARCHAR(10)).
     */
    private function otpMatchesStored(User $user, string $submittedOtp): bool
    {
        $stored = $user->otp_code;
        if ($stored === null || $stored === '') {
            return false;
        }

        $stored = (string) $stored;
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$')) {
            return Hash::check($submittedOtp, $stored);
        }

        return hash_equals($stored, $submittedOtp);
    }
}
