<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Division>
 */
class DivisionFactory extends Factory
{
/**
 * Define the model's default state.
 *
 * @return array<string, mixed> The default state of the model.
 */
public function definition(): array
{
    // Define the possible division names
    $divisionName = ['Payment Division', 'IT Division', 'Procurement Division', 'Finance Division'];

    // Return the default state of the model
    return [
        'name' => fake()->unique()->randomElement($divisionName)
    ];
}
}
