<?php

namespace Database\Factories;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Faq>
 */
class FaqFactory extends Factory
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

        $competition = Competition::inRandomOrder()->first();

        return [
            'question' => collect($locales)
                ->mapWithKeys(function ($locale) {
                    return [$locale => $this->faker->sentence];
                })->toArray(),
            'competition_id' => $this->faker->boolean()
                ? optional($competition)->id ?? Competition::factory()->create()->id
                : null,
            'answer' => collect($locales)
                ->mapWithKeys(function ($locale) {
                    return [$locale => $this->faker->paragraph];
                })->toArray(),
        ];
    }
}
