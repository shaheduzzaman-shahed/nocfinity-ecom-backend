<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterCustomerAction;
use App\Enums\AccountType;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Events\Auth\UserPasswordReset;
use App\Events\Auth\UserProfileUpdated;
use App\Events\Auth\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterCustomerAction $action)
    {
        $user = $action->execute($request->validated());

        $token = $user->createToken('auth-token')->plainTextToken;

        UserRegistered::dispatch($user);

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Registration successful.', 201);
    }

    public function login(LoginRequest $request)
    {
        $throttleKey = 'login:'.$request->ip();

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

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);

            return $this->errorResponse('Your account has been deactivated.', 403);
        }

        if ($user->account_type !== AccountType::Customer) {
            RateLimiter::hit($throttleKey, 60);

            return $this->errorResponse('The provided credentials are incorrect.', 401);
        }

        RateLimiter::clear($throttleKey);

        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        UserLoggedIn::dispatch($user, false, $request->ip());

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful.');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        UserLoggedOut::dispatch($user, false, $request->ip());

        return $this->successResponse(null, 'Logged out successfully.');
    }

    public function profile(Request $request)
    {
        return $this->successResponse(
            new UserResource($request->user()),
            'Profile retrieved successfully.',
        );
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        unset($data['current_password'], $data['password_confirmation']);

        $user->update($data);

        UserProfileUpdated::dispatch($user, array_keys($data), false);

        return $this->successResponse(
            new UserResource($user->fresh()),
            'Profile updated successfully.',
        );
    }

    public function activityLogs(Request $request)
    {
        $user = $request->user();
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $logs = $user->activityLogs()
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'payload' => $log->payload,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ]);

        return $this->paginatedResponse($logs, 'Activity logs retrieved successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        Password::sendResetLink($request->only('email'));

        return $this->successResponse(
            null,
            'If an account exists with that email, a password reset link has been sent.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                UserPasswordReset::dispatch($user);

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse(null, 'Password has been reset successfully.');
        }

        return $this->errorResponse('Unable to reset password.', 422, [
            'email' => [__($status)],
        ]);
    }
}
