<?php

namespace Database\Factories;

use App\Models\Registration;
use App\Models\Team;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $team = Team::whereHas('registrations', function ($query) {
            $query->where('status', 'approved');
        })
            ->inRandomOrder()
            ->first() ?? Registration::factory()->create()->team;
        $user = User::inRandomOrder()->first();
        $status = $this->faker->randomElement(['pending', 'reviewed']);

        return [
            'team_id' => $team->id,
            'competition_id' => $team->competition_id,
            'status' => $status,
            'submission' => $this->faker->imageUrl,
            'reviewed_by' => $status !== 'pending' ?
                optional($user)->id ?? User::factory()->create()->id :
                null,
            'submitted_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'feedback' => $status !== 'pending' ? array_map(function () {
                return $this->faker->imageUrl;
            }, range(1, 3)) :
                null,
        ];
    }
}
