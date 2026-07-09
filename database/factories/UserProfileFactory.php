<?php

namespace Database\Factories;

use App\Models\GeneralInfo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserProfile>
 */
class UserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::inRandomOrder()->first();
        $generalInfo = GeneralInfo::first() ?? GeneralInfo::factory()->create();
        return [
            'user_id' => optional($user)->id ?? User::factory()->create()->id,
            'birth_date' => $this->faker->date,
            'phone_number' => $this->faker->phoneNumber,
            'province' => $this->faker->state,
            'address' => $this->faker->address,
            'institution' => $this->faker->company,
            'student_id' => $this->faker->unique()->numerify('########'),
            'institution_card' => $this->faker->imageUrl,
            'follow_proof' => array_map(function () {
                return $this->faker->imageUrl;
            }, range(1, count($generalInfo->social_media_to_follow))),
            'twibbon_proof' => $this->faker->imageUrl
        ];
    }
}
