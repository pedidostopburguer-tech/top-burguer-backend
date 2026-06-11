<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return $this->created([
            'user' => $this->formatUser($result['user']),
            'profile' => $this->formatProfile($result['profile']),
            'token' => $result['token'],
        ], 'Conta criada com sucesso.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return $this->success([
            'user' => $this->formatUser($result['user']),
            'profile' => $this->formatProfile($result['profile']),
            'token' => $result['token'],
        ], 'Login realizado com sucesso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return $this->success(null, 'Logout realizado com sucesso.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');
        $profile = $user->profile;

        return $this->success([
            'user' => $this->formatUser($user),
            'profile' => $this->formatProfile($profile),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->forgotPassword($request->validated('email'));

        return $this->success(
            null,
            'Se o e-mail existir, você receberá as instruções em breve.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        // validated() não inclui password_confirmation (usado só na validação).
        // Password::reset() interno do Laravel precisa dele para revalidar — passamos via only().
        $this->auth->resetPassword($request->only(['token', 'email', 'password', 'password_confirmation']));

        return $this->success(null, 'Senha redefinida com sucesso.');
    }

    // -------------------------------------------------------------------------

    private function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function formatProfile($profile): ?array
    {
        if (! $profile) {
            return null;
        }

        return [
            'id' => $profile->id,
            'role' => $profile->role,
            'store_id' => $profile->store_id,
            'is_active' => $profile->is_active,
        ];
    }
}
