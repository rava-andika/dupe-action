<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
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
        $competition = Competition::inRandomOrder()->first();
        return [
            'name' => $this->faker->word,
            'invite_code' => null,
            'competition_id' => optional($competition)->id ?? Competition::factory()->create()->id,
            'leader_id' => User::factory()->create()->id,

        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure()
    {
        return $this->afterCreating(function (Team $team) {
            $team->members()->attach($team->leader_id);
        });
    }
}
