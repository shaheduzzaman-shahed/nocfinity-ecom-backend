<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserPasswordUpdated;
use App\Events\Auth\UserProfileUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\AdminLoginRequest;
use App\Http\Requests\Api\V1\Admin\Auth\UpdateStaffPasswordRequest;
use App\Http\Requests\Api\V1\Admin\Auth\UpdateStaffProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $throttleKey = 'admin-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ])->status(429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return $this->errorResponse('The provided credentials are incorrect.', 401);
        }

        if (! $user->isStaff()) {
            RateLimiter::hit($throttleKey, 60);

            return $this->errorResponse('The provided credentials are incorrect.', 401);
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);

            return $this->errorResponse('Your account has been deactivated.', 403);
        }

        RateLimiter::clear($throttleKey);

        $user->tokens()->delete();
        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

        UserLoggedIn::dispatch($user, true, $request->ip());

        return $this->successResponse([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->effectivePermissionNames(),
            'token' => $token,
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        UserLoggedOut::dispatch($user, true, $request->ip());

        return $this->successResponse(null, 'Logged out successfully.');
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->effectivePermissionNames(),
        ], 'Profile retrieved successfully.');
    }

    public function updateProfile(UpdateStaffProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        UserProfileUpdated::dispatch($user, array_keys($request->validated()), true);

        return $this->successResponse([
            'user' => new UserResource($user->fresh()),
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->effectivePermissionNames(),
        ], 'Profile updated successfully.');
    }

    public function updatePassword(UpdateStaffPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => $request->validated('password')]);

        $currentTokenId = $user->currentAccessToken()?->getKey();

        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        UserPasswordUpdated::dispatch($user, true);

        return $this->successResponse(null, 'Password updated successfully.');
    }
}
