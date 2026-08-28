<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'hospital_id' => null,
            'role_id' => null,
        ];
    }

    public function forHospital(Hospital $hospital): static
    {
        return $this->state(fn () => ['hospital_id' => $hospital->id]);
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn () => ['role_id' => $role->id]);
    }
}
