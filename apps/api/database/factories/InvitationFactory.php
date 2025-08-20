<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Invitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Company::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(['admin', 'manager', 'operator']),
            'token' => bin2hex(random_bytes(32)), // 64-character hex token
            'expires_at' => Carbon::now()->addDays(7),
            'accepted_at' => null,
            'invited_by' => null, // System-generated invitation by default
        ];
    }

    /**
     * Indicate that the invitation is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->subDays(fake()->numberBetween(1, 30)),
            'accepted_at' => null,
        ]);
    }

    /**
     * Indicate that the invitation is pending (valid and not accepted).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->addDays(fake()->numberBetween(1, 7)),
            'accepted_at' => null,
        ]);
    }

    /**
     * Indicate that the invitation has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => Carbon::now()->subDays(fake()->numberBetween(0, 30)),
        ]);
    }

    /**
     * Indicate that the invitation is for a specific role.
     */
    public function role(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    /**
     * Indicate that the invitation is for admin role.
     */
    public function admin(): static
    {
        return $this->role('admin');
    }

    /**
     * Indicate that the invitation is for manager role.
     */
    public function manager(): static
    {
        return $this->role('manager');
    }

    /**
     * Indicate that the invitation is for operator role.
     */
    public function operator(): static
    {
        return $this->role('operator');
    }

    /**
     * Indicate that the invitation is for user role.
     */
    public function user(): static
    {
        return $this->role('user');
    }

    /**
     * Indicate that the invitation was sent by a specific user.
     */
    public function sentBy(?User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'invited_by' => $user?->id,
        ]);
    }

    /**
     * Indicate that the invitation is for a specific tenant.
     */
    public function forTenant(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $company->id,
        ]);
    }

    /**
     * Indicate that the invitation has a specific email.
     */
    public function email(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }

    /**
     * Indicate that the invitation expires on a specific date.
     */
    public function expiresAt(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $date,
        ]);
    }

    /**
     * Indicate that the invitation is for the central domain (super admin).
     */
    public function central(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'role' => 'super-admin',
        ]);
    }
}
