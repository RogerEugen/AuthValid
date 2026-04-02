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
use Illuminate\Http\Request;

class AuthController extends Controller
{
    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

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

        $user->update(['last_login_at' => now()]);

        // First login — force password change
        if ($user->must_change_password) {
            return response()->json([
                'success'              => true,
                'must_change_password' => true,
                'message'              => 'You must change your password before continuing.',
                'jwt'                  => $token,
                'user'                 => [
                    'uuid'          => $user->uuid,
                    'name'          => $user->first_name . ' ' . $user->last_name,
                    'role'          => $user->role,
                    'department_id' => $user->department_id,
                ],
            ], 200);
        }

        // Normal login — generate anonymous token
        $anonToken = $this->generateAnonToken($user);

        return response()->json([
            'success'              => true,
            'must_change_password' => false,
            'jwt'                  => $token,
            'anonymous_token'      => $anonToken,
            'expires_in'           => 1800,
            'user'                 => $this->buildUserPayload($user),
        ], 200);
    }

    // ─────────────────────────────────────────────
    // CHANGE PASSWORD
    // ─────────────────────────────────────────────
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $user = JWTAuth::user();
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid or expired. Please login again.',
            ], 401);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        // New password must differ from current
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'New password must be different from current password.',
            ], 422);
        }

        // Update password in DB
        $user->password             = Hash::make($request->new_password);
        $user->must_change_password = false;
        $user->save();

        // Invalidate old token and issue fresh JWT
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            // Continue even if invalidation fails
        }

        $freshUser = User::findOrFail($user->id);
        $newJwt    = JWTAuth::fromUser($freshUser);

        // Generate anonymous token
        
        $anonToken = $this->generateAnonToken($freshUser);

        return response()->json([
            'success'              => true,
            'must_change_password' => false,
            'message'              => 'Password changed successfully.',
            'jwt'                  => $newJwt,
            'anonymous_token'      => $anonToken,
            'expires_in'           => 1800,
            'user'                 => $this->buildUserPayload($freshUser),
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
                'message' => 'Failed to logout.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────
    // REFRESH TOKEN
    // ─────────────────────────────────────────────
    public function refresh(): JsonResponse
    {
        try {
            $newToken  = JWTAuth::refresh(JWTAuth::getToken());
            $user      = JWTAuth::setToken($newToken)->toUser();
            $anonToken = $this->generateAnonToken($user);

            return response()->json([
                'success'         => true,
                'jwt'             => $newToken,
                'anonymous_token' => $anonToken,
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
    // GET ME
    // ─────────────────────────────────────────────
    public function me(): JsonResponse
    {
        $user = JWTAuth::user();

        return response()->json([
            'success' => true,
            'user'    => $this->buildUserPayload($user),
        ], 200);
    }

    // ─────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────

    // Roles that NEVER have a department
    private function isGlobalRole(string $role): bool
    {
        return in_array($role, ['admin', 'rector', 'registrar']);
    }

    private function generateAnonToken(User $user): string
    {
        $plain  = Str::random(64);
        $hashed = hash('sha256', $plain);

        AnonymousToken::create([
            'token_hash'    => $hashed,
            'role'          => $user->role,
            'department_id' => $this->isGlobalRole($user->role)
                                ? null
                                : $user->department_id,
            'expires_at'    => now()->addMinutes(30),
        ]);

        return $plain;
    }

    private function buildUserPayload(User $user): array
    {
        return [
            'uuid'          => $user->uuid,
            'name'          => $user->first_name . ' ' . $user->last_name,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'email'         => $user->email,
            'role'          => $user->role,
            'department_id' => $this->isGlobalRole($user->role) ? null : $user->department_id,
            'is_active'     => $user->is_active,
            'last_login_at' => $user->last_login_at,
            'profile'       => $this->loadProfile($user),
        ];
    }

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
            'staff_number'    => $profile->staff_number,
            'title'           => $profile->title,
            'specialization'  => $profile->specialization,
            'employment_type' => $profile->employment_type,
            'office_location' => $profile->office_location,
        ];
    }
    // ─────────────────────────────────────────────
// VALIDATE ANONYMOUS TOKEN (called by feedback service)
// ─────────────────────────────────────────────
public function validateAnonToken(Request $request): JsonResponse
{
    $plain = $request->anonymous_token;

    if (!$plain) {
        return response()->json(['valid' => false, 'message' => 'No token provided.'], 422);
    }

    $hashed = hash('sha256', $plain);
    $token  = AnonymousToken::where('token_hash', $hashed)->first();

    if (!$token) {
        return response()->json(['valid' => false, 'message' => 'Token not found.'], 401);
    }

    if ($token->is_used) {
        return response()->json(['valid' => false, 'message' => 'Token already used.'], 401);
    }

    if ($token->is_revoked) {
        return response()->json(['valid' => false, 'message' => 'Token revoked.'], 401);
    }

    if ($token->expires_at->isPast()) {
        return response()->json(['valid' => false, 'message' => 'Token expired.'], 401);
    }

    // Mark token as used — one time only
    $token->update([
        'is_used' => true,
        'used_at' => now(),
    ]);

    return response()->json([
        'valid'         => true,
        'role'          => $token->role,
        'department_id' => $token->department_id,
    ]);
}
}