<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('show', function () {
    it('returns the current user', function () {
        $user = signIn();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toJSON(),
            ]]);
    });

    it('returns 401 without a token', function () {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    });
});

describe('update', function () {
    it('updates only the passed fields', function () {
        $user = signIn(User::factory()->create(['name' => 'Old', 'email' => 'old@example.com']));

        $this->patchJson('/api/v1/me', ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.email', 'old@example.com');

        expect($user->fresh()->name)->toBe('New');
    });

    it('allows keeping the own email', function () {
        $user = signIn();

        $this->patchJson('/api/v1/me', ['email' => $user->email])->assertOk();
    });

    it('returns 422 when the email belongs to another user', function () {
        User::factory()->create(['email' => 'taken@example.com']);
        signIn();

        $this->patchJson('/api/v1/me', ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    });
});

describe('update password', function () {
    it('changes the password and revokes other tokens', function () {
        $user = User::factory()->create();
        $current = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $this->withToken($current)->putJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'new-secret-456',
            'password_confirmation' => 'new-secret-456',
        ])->assertNoContent();

        expect(Hash::check('new-secret-456', $user->fresh()->password))->toBeTrue()
            ->and($user->tokens()->pluck('name')->all())->toBe(['phone']);
    });

    it('returns 422 when the current password is wrong', function () {
        $user = User::factory()->create();

        $this->withToken($user->createToken('phone')->plainTextToken)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-secret-456',
                'password_confirmation' => 'new-secret-456',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password' => 'The password is incorrect.']);

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });
});
