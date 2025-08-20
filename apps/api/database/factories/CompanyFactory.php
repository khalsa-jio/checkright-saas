<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        $domain = strtolower(str_replace([' ', '.', ',', '&'], '', $name)) . fake()->randomNumber(3);

        return [
            'name' => $name,
            'domain' => $domain,
            'data' => [
                'settings' => [
                    'timezone' => fake()->timezone(),
                    'locale' => 'en',
                ],
                'billing' => [
                    'plan' => 'basic',
                    'status' => 'active',
                ],
            ],
        ];
    }

    /**
     * Indicate that the company is a test company.
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Test Company',
            'domain' => 'testcompany' . fake()->randomNumber(3),
        ]);
    }

    /**
     * Indicate that the company has a specific domain.
     */
    public function withDomain(string $domain): static
    {
        return $this->state(fn (array $attributes) => [
            'domain' => $domain,
        ]);
    }

    /**
     * Indicate that the company has specific settings.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'data' => array_merge($attributes['data'] ?? [], $settings),
        ]);
    }
}
