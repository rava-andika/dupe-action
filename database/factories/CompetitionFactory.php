<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    public function definition(): array
    {
        $locales = app()->bound('supportedLocales') && !empty(app('supportedLocales')['active'])
            ? app('supportedLocales')['active']
            : ['en', 'id'];

        return [
            'name' => $this->faker->unique()->word,
            'description' => collect($locales)
                ->mapWithKeys(function ($locale) {
                    return [$locale => $this->faker->paragraph];
                })->toArray(),
            'short_desc' => collect($locales)
                ->mapWithKeys(function ($locale) {
                    return [$locale => $this->faker->sentence];
                })->toArray(),
            'image' => $this->faker->imageUrl,
            'guidebook' => $this->faker->url,
            'price' => $this->faker->numberBetween(100000, 500000),
            'contacts' => array_map(function () {
                return [
                    'name' => $this->faker->name,
                    'phoneNumber' => $this->faker->numberBetween(100000000000, 9999999999999),
                ];
            }, range(1, $this->faker->numberBetween(1, 4))),
            'max_team_size' => $max = $this->faker->numberBetween(2, 5),
            'min_team_size' => $this->faker->numberBetween(1, $max),
            'enforce_single_team_rule' => $this->faker->boolean,
            'timeline' => collect(range(1, $this->faker->numberBetween(6, 10)))
                ->map(function () {
                    $start = $this->faker->dateTimeBetween('now', '+1 weeks');
                    $end = $this->faker->dateTimeBetween((clone $start)->modify('+1 day'), (clone $start)->modify('+5 days'));
                    return [
                        'start' => $start->format('Y-m-d'),
                        'end' => $end->format('Y-m-d'),
                        'description' => $this->faker->sentence,
                        'is_registration' => $this->faker->boolean,
                        'is_submission' => $this->faker->boolean,
                    ];
                })
                ->sortBy('start') // Sort by start date
                ->values() // Reset keys
                ->toArray(),
        ];    
    }
}
