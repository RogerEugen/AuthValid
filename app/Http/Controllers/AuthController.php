<?php
namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\AnonymousToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        // 1. Check user exists and is active
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Contact admin.',
            ], 403);
        }

        // 2. Attempt JWT login
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create token. Try again.',
            ], 500);
        }

        // 3. Update last login timestamp
        $user->update(['last_login_at' => now()]);

        // 4. Check if first login — force password change
        if ($user->must_change_password) {
            return response()->json([
                'success'             => true,
                'must_change_password' => true,
                'message'             => 'You must change your password before continuing.',
                'jwt'                 => $token,
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'role' => $user->role,
                ],
            ], 200);
        }

        // 5. Generate anonymous token
        $plainToken   = Str::random(64);
        $hashedToken  = hash('sha256', $plainToken);

        AnonymousToken::create([
            'token_hash'    => $hashedToken,
            'role'          => $user->role,
            'department_id' => $user->department_id,
            'expires_at'    => now()->addMinutes(30),
        ]);

        // 6. Load profile based on role
        $profile = $this->loadProfile($user);

        return response()->json([
            'success'         => true,
            'must_change_password' => false,
            'jwt'             => $token,
            'anonymous_token' => $plainToken,
            'expires_in'      => 1800, // 30 minutes in seconds
            'user'            => [
                'uuid'          => $user->uuid,
                'name'          => $user->first_name . ' ' . $user->last_name,
                'email'         => $user->email,
                'role'          => $user->role,
                'department_id' => $user->department_id,
                'profile'       => $profile,
            ],
        ], 200);
    }

    // ─────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully.',
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout. Try again.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // REFRESH TOKEN
    // ─────────────────────────────────────────────
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            $user = JWTAuth::setToken($newToken)->toUser();

            // Generate a fresh anonymous token too
            $plainToken  = Str::random(64);
            $hashedToken = hash('sha256', $plainToken);

            AnonymousToken::create([
                'token_hash'    => $hashedToken,
                'role'          => $user->role,
                'department_id' => $user->department_id,
                'expires_at'    => now()->addMinutes(30),
            ]);

            return response()->json([
                'success'         => true,
                'jwt'             => $newToken,
                'anonymous_token' => $plainToken,
                'expires_in'      => 1800,
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired. Please login again.',
            ], 401);
        }
    }

    // ─────────────────────────────────────────────
    // CHANGE PASSWORD (forced on first login)
    // ─────────────────────────────────────────────
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = JWTAuth::user();

        // Verify old password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        // New password must not be same as old
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'New password must be different from current password.',
            ], 422);
        }

        // Save new password and clear the force-change flag
        $user->update([
            'password'             => Hash::make($request->new_password),
            'must_change_password' => false,
        ]);

        // Now generate anonymous token since login is fully complete
        $plainToken  = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        AnonymousToken::create([
            'token_hash'    => $hashedToken,
            'role'          => $user->role,
            'department_id' => $user->department_id,
            'expires_at'    => now()->addMinutes(30),
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'Password changed successfully.',
            'anonymous_token' => $plainToken,
            'expires_in'      => 1800,
        ], 200);
    }

    // ─────────────────────────────────────────────
    // GET AUTHENTICATED USER
    // ─────────────────────────────────────────────
    public function me(): JsonResponse
    {
        $user    = JWTAuth::user();
        $profile = $this->loadProfile($user);

        return response()->json([
            'success' => true,
            'user'    => [
                'uuid'          => $user->uuid,
                'name'          => $user->first_name . ' ' . $user->last_name,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'email'         => $user->email,
                'role'          => $user->role,
                'department_id' => $user->department_id,
                'department'    => $user->department?->name,
                'is_active'     => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'profile'       => $profile,
            ],
        ], 200);
    }

    // ─────────────────────────────────────────────
    // PRIVATE HELPER — load correct profile by role
    // ─────────────────────────────────────────────
    private function loadProfile(User $user): array|null
    {
        return match (true) {
            $user->role === 'student' => $this->studentProfile($user),
            in_array($user->role, ['lecturer', 'hod', 'dean', 'rector', 'registrar', 'admin'])
                => $this->staffProfile($user),
            default => null,
        };
    }

    private function studentProfile(User $user): array|null
    {
        $profile = $user->studentProfile?->load('program.department.faculty');

        if (!$profile) return null;

        return [
            'registration_number' => $profile->registration_number,
            'year_of_study'       => $profile->year_of_study,
            'semester'            => $profile->semester,
            'academic_year'       => $profile->academic_year,
            'enrollment_status'   => $profile->enrollment_status,
            'program'             => $profile->program?->name,
            'program_code'        => $profile->program?->code,
            'department'          => $profile->program?->department?->name,
            'faculty'             => $profile->program?->department?->faculty?->name,
        ];
    }

    private function staffProfile(User $user): array|null
    {
        $profile = $user->staffProfile;

        if (!$profile) return null;

        return [
            'staff_number'     => $profile->staff_number,
            'title'            => $profile->title,
            'specialization'   => $profile->specialization,
            'employment_type'  => $profile->employment_type,
            'office_location'  => $profile->office_location,
        ];
    }
}