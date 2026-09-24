<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('register', function () {
    it('creates a user and returns a token', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
            'device_name' => 'iPhone',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'anna@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password');

        $user = User::firstWhere('email', 'anna@example.com');
        expect(Hash::check('secret-pass-123', $user->password))->toBeTrue()
            ->and($user->tokens()->sole()->name)->toBe('iPhone');

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    });

    it('returns 422 when required fields are missing', function () {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    it('returns 422 when the email is already taken', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Anna',
            'email' => 'taken@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'secret-pass-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    });

    it('returns 422 when the password confirmation does not match', function () {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'password' => 'secret-pass-123',
            'password_confirmation' => 'other-pass-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password field confirmation does not match.']);
    });
});

describe('login', function () {
    it('returns a token for valid credentials', function () {
        $user = User::factory()->create(['email' => 'demo@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);

        expect($user->tokens()->sole()->name)->toBe('api');
    });

    it('returns 422 for a wrong password', function () {
        User::factory()->create(['email' => 'demo@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
    });

    it('returns 422 for an unknown email with the same message', function () {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
    });

    it('returns 429 after five failed attempts per minute', function () {
        User::factory()->create(['email' => 'demo@example.com']);
        $payload = ['email' => 'demo@example.com', 'password' => 'wrong-password'];

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too Many Attempts.');
    });
});

describe('logout', function () {
    it('revokes only the current token', function () {
        $user = User::factory()->create();
        $current = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withToken($current)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);
    });

    it('returns 401 without a token', function () {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    });
});
