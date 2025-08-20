<?php

namespace Database\Factories;

use App\Models\DeviceRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceRegistration>
 */
class DeviceRegistrationFactory extends Factory
{
    protected $model = DeviceRegistration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['ios', 'android'];
        $platform = $this->faker->randomElement($platforms);

        $models = [
            'ios' => ['iPhone 13', 'iPhone 14', 'iPhone 15', 'iPad Pro', 'iPad Air'],
            'android' => ['Samsung Galaxy S23', 'Google Pixel 7', 'OnePlus 11', 'Xiaomi 13', 'Samsung Note 20'],
        ];

        return [
            'user_id' => User::factory(),
            'device_id' => $this->faker->unique()->uuid() . '-' . $this->faker->randomNumber(4),
            'device_info' => [
                'platform' => $platform,
                'model' => $this->faker->randomElement($models[$platform]),
                'version' => $platform === 'ios' ? $this->faker->randomElement(['15.0', '16.0', '17.0']) : $this->faker->randomElement(['11', '12', '13', '14']),
                'app_version' => $this->faker->randomElement(['1.0.0', '1.1.0', '1.2.0']),
                'screen_resolution' => $this->faker->randomElement(['1080x1920', '1440x3200', '828x1792']),
                'timezone' => $this->faker->timezone,
            ],
            'device_secret' => bin2hex(random_bytes(32)),
            'is_trusted' => $this->faker->boolean(30), // 30% chance of being trusted
            'registered_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'trusted_at' => function (array $attributes) {
                return $attributes['is_trusted'] ? $this->faker->dateTimeBetween($attributes['registered_at'], 'now') : null;
            },
            'trusted_until' => function (array $attributes) {
                return $attributes['is_trusted'] && $attributes['trusted_at']
                    ? $this->faker->dateTimeBetween($attributes['trusted_at'], '+90 days')
                    : null;
            },
            'last_used_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Indicate that the device is trusted.
     */
    public function trusted(): static
    {
        return $this->state(function (array $attributes) {
            $trustedAt = $this->faker->dateTimeBetween($attributes['registered_at'] ?? '-30 days', 'now');

            return [
                'is_trusted' => true,
                'trusted_at' => $trustedAt,
                'trusted_until' => $this->faker->dateTimeBetween($trustedAt, '+90 days'),
            ];
        });
    }

    /**
     * Indicate that the device trust has expired.
     */
    public function expiredTrust(): static
    {
        return $this->state(function (array $attributes) {
            $trustedAt = $this->faker->dateTimeBetween('-60 days', '-10 days');

            return [
                'is_trusted' => true,
                'trusted_at' => $trustedAt,
                'trusted_until' => $this->faker->dateTimeBetween($trustedAt, '-1 days'),
            ];
        });
    }

    /**
     * Indicate that the device is untrusted.
     */
    public function untrusted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_trusted' => false,
                'trusted_at' => null,
                'trusted_until' => null,
            ];
        });
    }

    /**
     * Indicate that the device is for iOS platform.
     */
    public function ios(): static
    {
        return $this->state(function (array $attributes) {
            $models = ['iPhone 13', 'iPhone 14', 'iPhone 15', 'iPad Pro', 'iPad Air'];

            return [
                'device_info' => array_merge($attributes['device_info'] ?? [], [
                    'platform' => 'ios',
                    'model' => $this->faker->randomElement($models),
                    'version' => $this->faker->randomElement(['15.0', '16.0', '17.0']),
                ]),
            ];
        });
    }

    /**
     * Indicate that the device is for Android platform.
     */
    public function android(): static
    {
        return $this->state(function (array $attributes) {
            $models = ['Samsung Galaxy S23', 'Google Pixel 7', 'OnePlus 11', 'Xiaomi 13', 'Samsung Note 20'];

            return [
                'device_info' => array_merge($attributes['device_info'] ?? [], [
                    'platform' => 'android',
                    'model' => $this->faker->randomElement($models),
                    'version' => $this->faker->randomElement(['11', '12', '13', '14']),
                ]),
            ];
        });
    }
}
