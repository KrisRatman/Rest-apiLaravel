<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'description' => fake()->optional()->sentence(),
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Владелец команды всегда состоит в ней с ролью owner.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Team $team): void {
            $team->members()->attach($team->owner_id, ['role' => TeamRole::Owner]);
        });
    }
}
