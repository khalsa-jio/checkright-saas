<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = fake()->randomElement(['google', 'facebook', 'instagram']);

        return [
            'user_id' => \App\Models\User::factory(),
            'provider' => $provider,
            'provider_id' => fake()->numerify('##########'),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'avatar' => fake()->imageUrl(200, 200, 'people'),
            'token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'expires_in' => 3600,
        ];
    }
}
