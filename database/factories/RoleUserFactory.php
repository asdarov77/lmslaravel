<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoleUserFactory extends Factory
{
    public function definition()
    {
        return [
            'role_id' => \App\Models\Role::factory(),
            'user_id' => \App\Models\User::factory(),
        ];
    }
}
