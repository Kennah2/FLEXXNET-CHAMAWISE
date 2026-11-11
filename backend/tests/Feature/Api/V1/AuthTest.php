<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_receives_a_usable_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Grace',
            'username' => 'Grace.W',
            'email' => 'grace@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'test-suite',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'username', 'email']]);

        $user = User::firstWhere('email', 'grace@example.test');
        $this->assertNotNull($user);
        $this->assertSame('grace.w', $user->username);

        $token = $response->json('token');

        // Prove the issued token actually authenticates a subsequent request, not just that
        // registration returned something token-shaped.
        $me = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');
        $me->assertForbidden(); // unverified email — "verified" middleware blocks it, same as web.
    }

    public function test_a_user_can_log_in_with_either_username_or_email_and_the_token_authenticates(): void
    {
        $user = User::factory()->create([
            'username' => 'kamau',
            'email' => 'kamau@example.test',
            'password' => bcrypt('secret123'),
            'email_verified_at' => now(),
        ]);

        $byUsername = $this->postJson('/api/v1/auth/login', [
            'login' => 'KAMAU',
            'password' => 'secret123',
            'device_name' => 'phone-1',
        ]);
        $byUsername->assertOk();
        $byUsername->assertJsonStructure(['token', 'user']);

        $byEmail = $this->postJson('/api/v1/auth/login', [
            'login' => 'kamau@example.test',
            'password' => 'secret123',
            'device_name' => 'phone-2',
        ]);
        $byEmail->assertOk();

        $token = $byUsername->json('token');
        $me = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');
        $me->assertOk();
        $me->assertJsonPath('data.id', $user->id);
    }

    public function test_an_incorrect_password_is_rejected_without_issuing_a_token(): void
    {
        User::factory()->create(['username' => 'kamau', 'password' => bcrypt('secret123'), 'email_verified_at' => now()]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'kamau',
            'password' => 'wrong-password',
            'device_name' => 'phone-1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('login');
    }

    public function test_a_request_with_no_token_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_request_with_an_invalid_token_is_unauthenticated(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_the_token_so_it_no_longer_authenticates(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('phone')->plainTextToken;

        $logout = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/auth/logout');
        $logout->assertOk();
        $this->assertSame(0, $user->tokens()->count());

        // Laravel's RequestGuard caches the resolved user on itself for the lifetime of the
        // guard instance, and the auth manager caches guard instances per name — harmless for
        // real traffic (a fresh PHP request gets a fresh container and therefore a fresh guard),
        // but within one test method, two HTTP calls share the same container, so without
        // forgetting the cached guard this second call would wrongly "stay logged in" as whoever
        // the first call resolved, regardless of the token having just been deleted above.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_an_unverified_user_can_log_in_but_is_blocked_from_verified_only_endpoints(): void
    {
        $user = User::factory()->create(['username' => 'unverified', 'password' => bcrypt('secret123'), 'email_verified_at' => null]);

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => 'unverified',
            'password' => 'secret123',
            'device_name' => 'phone-1',
        ]);
        $login->assertOk();

        $token = $login->json('token');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/chamas')
            ->assertForbidden();
    }
}
