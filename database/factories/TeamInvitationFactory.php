<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => TeamRole::Member,
            'token_hash' => TeamInvitation::hashToken(Str::random(40)),
            'invited_by' => fn (array $attributes) => Team::find($attributes['team_id'])?->owner_id,
            'expires_at' => now()->addDays(TeamInvitation::LIFETIME_DAYS),
        ];
    }

    /**
     * Приглашение с известным токеном — чтобы принять его в тесте.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn () => ['token_hash' => TeamInvitation::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
