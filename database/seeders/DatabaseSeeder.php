<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate: re-running db:seed must not crash on the unique email
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password'], // hashed by the User model's cast
        );

        $categories = Category::factory(5)->create();
        $suppliers = Supplier::factory(8)->create();

        Product::factory(50)
            ->recycle($categories)
            ->create()
            ->each(fn (Product $p) => $p->suppliers()->attach($suppliers->random(rand(1, 3))));
    }
}
