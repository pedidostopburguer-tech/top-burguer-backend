<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStore(): Store
    {
        return Store::factory()->create();
    }

    private function makeUserWithProfile(array $overrides = []): array
    {
        $store = $this->makeStore();
        $user = User::factory()->create(['password' => Hash::make('senha123456')]);
        $profile = Profile::factory()->create(array_merge([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'role' => 'store_owner',
            'is_active' => true,
        ], $overrides));

        return compact('user', 'profile', 'store');
    }

    // -------------------------------------------------------------------------
    // Register
    // -------------------------------------------------------------------------

    public function test_register_com_dados_validos(): void
    {
        $store = $this->makeStore();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'João Teste',
            'email' => 'joao@test.com',
            'password' => 'senha123456',
            'password_confirmation' => 'senha123456',
            'store_id' => $store->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['user' => ['id', 'name', 'email'], 'profile', 'token'],
            ])
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', ['email' => 'joao@test.com']);
        $this->assertDatabaseHas('profiles', ['store_id' => $store->id, 'role' => 'store_owner']);
    }

    public function test_register_com_email_duplicado(): void
    {
        $store = $this->makeStore();
        User::factory()->create(['email' => 'dup@test.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Outro',
            'email' => 'dup@test.com',
            'password' => 'senha123456',
            'password_confirmation' => 'senha123456',
            'store_id' => $store->id,
        ])->assertStatus(422);
    }

    public function test_register_com_store_id_invalido(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Teste',
            'email' => 'teste@test.com',
            'password' => 'senha123456',
            'password_confirmation' => 'senha123456',
            'store_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertStatus(422);
    }

    public function test_register_com_senha_fraca(): void
    {
        $store = $this->makeStore();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Teste',
            'email' => 'teste@test.com',
            'password' => '123',
            'password_confirmation' => '123',
            'store_id' => $store->id,
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    public function test_login_com_credenciais_corretas(): void
    {
        ['user' => $user] = $this->makeUserWithProfile();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user', 'profile', 'token']])
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_login_com_senha_errada(): void
    {
        ['user' => $user] = $this->makeUserWithProfile();

        // AuthService lança ValidationException->status(401)
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha_errada',
        ])->assertStatus(401);
    }

    public function test_login_com_conta_desativada(): void
    {
        ['user' => $user] = $this->makeUserWithProfile(['is_active' => false]);

        // AuthService lança ValidationException->status(403)
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha123456',
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function test_logout_autenticado(): void
    {
        ['user' => $user] = $this->makeUserWithProfile();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_logout_sem_token(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Me
    // -------------------------------------------------------------------------

    public function test_me_autenticado(): void
    {
        ['user' => $user] = $this->makeUserWithProfile();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['user', 'profile']])
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_me_sem_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Forgot Password
    // -------------------------------------------------------------------------

    public function test_forgot_password_email_existente(): void
    {
        ['user' => $user] = $this->makeUserWithProfile();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_forgot_password_email_inexistente(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'naoexiste@test.com'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Reset Password
    // -------------------------------------------------------------------------

    public function test_reset_password_com_token_valido(): void
    {
        // Mocka o broker — testamos o contrato HTTP, não a implementação interna do Laravel.
        // O broker real tem seus próprios testes na suite do framework.
        Password::shouldReceive('reset')
            ->once()
            ->andReturnUsing(function ($credentials, $callback) {
                // Simula o broker chamando o callback com um User fake
                $user = User::factory()->make();
                $callback($user, 'nova_senha_123');

                return Password::PASSWORD_RESET;
            });

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'token-valido-qualquer',
            'email' => 'usuario@test.com',
            'password' => 'nova_senha_123',
            'password_confirmation' => 'nova_senha_123',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_reset_password_com_token_invalido(): void
    {
        Password::shouldReceive('reset')
            ->once()
            ->andReturn(Password::INVALID_TOKEN);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'token-invalido',
            'email' => 'usuario@test.com',
            'password' => 'nova_senha_123',
            'password_confirmation' => 'nova_senha_123',
        ])->assertStatus(422);
    }
}
