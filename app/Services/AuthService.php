<?php
namespace App\Services;

use App\Models\Profile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Registra um novo usuário com perfil de store_owner.
     *
     * @return array{user: User, profile: Profile, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $profile = Profile::create([
            'user_id'  => $user->id,
            'store_id' => $data['store_id'],
            'role'     => 'store_owner',
            'is_active' => true,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return compact('user', 'profile', 'token');
    }

    /**
     * Autentica um usuário e retorna token + perfil.
     *
     * @return array{user: User, profile: Profile, token: string}
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ])->status(401);
        }

        $profile = $user->profile;

        if ($profile && ! $profile->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Sua conta está desativada. Entre em contato com o suporte.'],
            ])->status(403);
        }

        $token = $user->createToken('api')->plainTextToken;

        return compact('user', 'profile', 'token');
    }

    /**
     * Revoga o token atual do usuário autenticado.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Envia e-mail de reset de senha.
     * Sempre retorna true para não revelar se o e-mail existe.
     */
    public function forgotPassword(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
        // Ignora o resultado intencionalmente (proteção contra enumeração)
    }

    /**
     * Redefine a senha usando o token do e-mail.
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            [
                'email'                 => $data['email'],
                'password'              => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token'                 => $data['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }
    }
}
