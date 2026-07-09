<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GeneralInfo>
 */
class GeneralInfoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sponsors' => array_map(function () {
                return [
                    'name' => $this->faker->name,
                    'url' => $this->faker->url,
                    'image' => $this->faker->imageUrl
                ];
            }, range(1, $this->faker->numberBetween(1, 4))),
            'contact' => [
                'email' => $this->faker->email,
                'phoneNumber' => $this->faker->numberBetween(100000000000, 9999999999999)
            ],
            'social_media' => collect(['facebook', 'twitter', 'instagram', 'youtube', 'tiktok'])
                ->mapWithKeys(function ($medsos) {
                    return [$medsos => $this->faker->url];
                })->toArray(),
            'social_media_to_follow' => function () {
                $socialMediaObject = [];
                $iterations = $this->faker->numberBetween(2, 4);
                for ($i = 0; $i < $iterations; $i++) {
                    $key = $this->faker->words(2, true);
                    $url = $this->faker->url;
                    $socialMediaObject[$key] = $url;
                }
                return $socialMediaObject;
            },
            'twibbon_url' => $this->faker->url,
            'payment_methods'=> array_map(function () {
                return [
                    'method' => $this->faker->randomElement(['BCA', 'BNI', 'BRI', 'Mandiri', 'DANA', 'GoPay']),
                    'accountNumber' => (string) $this->faker->numberBetween(100000000000, 9999999999999),
                    'holderName' => $this->faker->name,
                ];
            }, range(1, $this->faker->numberBetween(1, 4))),
        ];
    }
}
