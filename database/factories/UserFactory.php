<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
/**
 * Define the model's default state.
 *
 * @return array<string, mixed> The default state of the model.
 */
public function definition(): array
{
    // Return an array with the default state of the model
    return [
        'username' => 'lks', // Set the username to 'lks'
        'password' => Hash::make('123'), // Hash the password '123' and set it
        'role' => 'admin' // Set the role to 'admin'
    ];
}
}
