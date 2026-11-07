<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company(), 'email' => fake()->unique()->companyEmail()];
    }
}
