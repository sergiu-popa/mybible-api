<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'roles' => [],
            'is_super' => false,
            'language' => null,
            'languages' => [],
            'ui_locale' => null,
            'is_active' => true,
            'preferred_version' => null,
            'avatar' => null,
            'last_login' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Mark the user as an admin (membership in `users.roles`). Combine with
     * `super()` to produce a super-admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'roles' => ['admin'],
        ]);
    }

    /**
     * Mark the user as a super-admin. Implies the `admin` role since every
     * super-admin is also an admin.
     */
    public function super(): static
    {
        return $this->state(function (array $attributes): array {
            $roles = $attributes['roles'] ?? [];

            if (! in_array('admin', $roles, true)) {
                $roles[] = 'admin';
            }

            return [
                'roles' => $roles,
                'is_super' => true,
            ];
        });
    }

    /**
     * Scope the admin to a specific set of content languages (2-char codes).
     *
     * @param  list<string>  $codes
     */
    public function withLanguages(array $codes): static
    {
        return $this->state(fn (array $attributes) => [
            'languages' => $codes,
        ]);
    }

    /**
     * Mark the user as disabled (cannot log in / has tokens revoked
     * downstream). Separate from soft-delete: a disabled user is still
     * present and can be reactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
