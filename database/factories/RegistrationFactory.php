<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $team = Team::inRandomOrder()->first() ?? Team::factory()->create();
        $user = User::inRandomOrder()->first();
        $locales = app()->bound('supportedLocales') && !empty(app('supportedLocales')['active'])
            ? app('supportedLocales')['active']
            : ['en', 'id'];
        $status = $this->faker->randomElement(['pending', 'approved', 'rejected']);

        // Add members and give them profile
        $minSize = $team->competition->min_team_size;
        $membersToAddCount = $minSize > 1 ? $minSize - 1 : 0;
        if ($membersToAddCount > 0) {
            $newMembers = User::factory()->count($membersToAddCount)->create();
            foreach ($newMembers as $member) {
                $member->profile()->create(
                    UserProfile::factory()->definition()
                );
                $team->members()->attach($member->id);
            }
        }

        return [
            'team_id' => $team->id,
            'competition_id' => $team->competition_id,
            'status' => $status,
            'payment_proof' => $this->faker->imageUrl,
            'reviewed_by' => $status !== 'pending' ?
                optional($user)->id ?? User::factory()->create()->id :
                null,
            'submitted_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'notes' => $status === 'rejected' ? collect($locales)
                ->mapWithKeys(function ($locale) {
                    return [$locale => $this->faker->paragraph];
                })->toArray() : null,
            'group_link' => $this->faker->url,
            'price_at_registration' => $team->competition->price,
        ];
    }
}
