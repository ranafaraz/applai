<?php

namespace App\Http\Controllers\Api\App\Auth;

use App\Http\Controllers\Api\App\AppController;
use App\Http\Requests\App\LoginRequest;
use App\Http\Requests\App\RegisterRequest;
use App\Http\Resources\App\UserResource;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends AppController
{
    /** Access-token lifetime in minutes. */
    private const ACCESS_TTL = 60;

    // ── Registration & login ─────────────────────────────────────────────────

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            // Each registrant gets their own tenant so Tenantable scoping isolates
            // their data from every other account (mirrors production web signup).
            $tenant = Tenant::create([
                'name'          => $request->string('full_name') . "'s Workspace",
                'email'         => $request->string('email'),
                'plan'          => 'free',
                'status'        => 'trial',
                'max_users'     => 3,
                'trial_ends_at' => now()->addDays(14),
            ]);

            return User::create([
                'tenant_id'         => $tenant->id,
                'name'              => $request->string('full_name'),
                'email'             => $request->string('email'),
                'password'          => $request->string('password'),
                'role'              => 'admin',
                'is_active'         => true,
                'tracking_types'    => $request->input('tracking_types', []),
                'email_verified_at' => now(),
            ]);
        });

        return response()->json($this->tokenPayload($user), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        // 401 (not 422) so the app's "invalid credentials → re-login" path fires.
        // Thrown ValidationExceptions are forced to 422 centrally, so return directly.
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return $this->error('These credentials do not match our records.', 'INVALID_CREDENTIALS', 401, [
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            return $this->error('This account is disabled.', 'ACCOUNT_DISABLED', 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json($this->tokenPayload($user));
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => ['required', 'string']]);

        $token = RefreshToken::findValid($request->string('refresh_token'));
        if (! $token) {
            return $this->error('Invalid or expired refresh token.', 'INVALID_REFRESH_TOKEN', 401);
        }

        // Rotate: revoke the presented token and issue a fresh pair.
        $user = $token->user;
        $token->revoke();

        return response()->json($this->tokenPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        // Invalidate refresh tokens for this account so a stolen refresh token
        // can't outlive an explicit sign-out.
        $request->user()->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return response()->json(['data' => ['message' => 'Logged out.']]);
    }

    // ── Password reset (no user enumeration) ─────────────────────────────────

    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always 200 regardless of whether the address exists.
        try {
            Password::sendResetLink(['email' => $request->string('email')]);
        } catch (\Throwable) {
            // swallow — never reveal delivery state
        }

        return response()->json(['data' => ['message' => 'If that email exists, a reset link has been sent.']]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
                $user->refreshTokens()->update(['revoked_at' => now()]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(__($status), 'RESET_FAILED', 422, ['email' => [__($status)]]);
        }

        return response()->json(['data' => ['message' => 'Password has been reset.']]);
    }

    public function social(Request $request): JsonResponse
    {
        // Google/Apple OAuth client IDs are not provisioned for v1. Gate behind
        // config so the endpoint flips on once credentials are added, without a
        // code change. See handover notes.
        if (! config('services.google.client_id') && ! config('services.apple.client_id')) {
            return $this->error('Social login is not enabled.', 'NOT_IMPLEMENTED', 501);
        }

        return $this->error('Social login is not yet implemented.', 'NOT_IMPLEMENTED', 501);
    }

    // ── Profile / account (§7.1) ─────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        return $this->data(UserResource::make($request->user()));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name'        => ['sometimes', 'string', 'max:255'],
            'avatar_url'       => ['sometimes', 'nullable', 'url'],
            'tracking_types'   => ['sometimes', 'array'],
            'tracking_types.*' => ['string', 'in:job,phd,scholarship,grant,freelance'],
        ]);

        $user = $request->user();
        if (array_key_exists('full_name', $validated)) {
            $user->name = $validated['full_name'];
        }
        if (array_key_exists('avatar_url', $validated)) {
            $user->avatar_url = $validated['avatar_url'];
        }
        if (array_key_exists('tracking_types', $validated)) {
            $user->tracking_types = $validated['tracking_types'];
        }
        $user->save();

        return $this->data(UserResource::make($user));
    }

    public function changeEmail(Request $request): JsonResponse
    {
        $request->validate([
            'new_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'  => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! Hash::check($request->string('password'), $user->password)) {
            return $this->error('Incorrect password.', 'INVALID_PASSWORD', 422, ['password' => ['Incorrect password.']]);
        }

        $user->forceFill([
            'email'             => $request->string('new_email'),
            'email_verified_at' => now(),
        ])->save();

        return $this->data(UserResource::make($user));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = $request->user();
        if (! Hash::check($request->string('current_password'), $user->password)) {
            return $this->error('Incorrect password.', 'INVALID_PASSWORD', 422, ['current_password' => ['Incorrect password.']]);
        }

        $user->forceFill(['password' => Hash::make($request->string('new_password'))])->save();

        // Force re-auth on other devices.
        $user->tokens()->where('id', '!=', optional($user->currentAccessToken())->id)->delete();

        return response()->json(['data' => ['message' => 'Password changed.']]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();
        if (! Hash::check($request->string('password'), $user->password)) {
            return $this->error('Incorrect password.', 'INVALID_PASSWORD', 422, ['password' => ['Incorrect password.']]);
        }

        $user->tokens()->delete();
        $user->refreshTokens()->update(['revoked_at' => now()]);
        $user->delete(); // soft delete

        return response()->json(['data' => ['message' => 'Account deleted.']], 200);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Issue a Sanctum access token + opaque refresh token and build the §2 payload. */
    private function tokenPayload(User $user): array
    {
        $expiresAt = now()->addMinutes(self::ACCESS_TTL);
        $access    = $user->createToken('mobile', ['*'], $expiresAt);

        return [
            'access_token'  => $access->plainTextToken,
            'refresh_token' => RefreshToken::issueFor($user),
            'token_type'    => 'Bearer',
            'expires_in'    => self::ACCESS_TTL * 60,
            'user'          => UserResource::make($user),
        ];
    }
}
