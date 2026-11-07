<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CachingAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_product_list_is_served_from_cache(): void
    {
        $category = Category::factory()->create();
        Product::factory(2)->for($category)->create();
        $this->getJson('/api/products')->assertJsonPath('meta.total', 2);

        // a write that bypasses Eloquent (no events) must NOT show up: proves the response is cached
        DB::table('products')->insert([
            'category_id' => $category->id,
            'name' => 'Sneaky', 'price' => 1, 'stock' => 1,
        ]);
        $this->getJson('/api/products')->assertJsonPath('meta.total', 2);

        // different filters/page are separate cache entries
        $this->getJson('/api/products?page=1&per_page=1')->assertJsonPath('meta.total', 3);
    }

    public function test_create_update_and_delete_through_api_invalidate_cache(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $this->getJson('/api/products')->assertJsonPath('data.0.stock', 50);

        $this->patchJson("/api/products/{$product->id}", ['stock' => 3])->assertOk();
        $this->getJson('/api/products')->assertJsonPath('data.0.stock', 3);

        // supplier-only change: no product column is dirty, still must invalidate
        $supplier = Supplier::factory()->create();
        $this->getJson('/api/products')->assertJsonCount(0, 'data.0.suppliers');
        $this->patchJson("/api/products/{$product->id}", ['supplier_ids' => [$supplier->id]])->assertOk();
        $this->getJson('/api/products')->assertJsonPath('data.0.suppliers.0.id', $supplier->id);

        $this->postJson('/api/products', [
            'category_id' => $product->category_id, 'name' => 'New', 'price' => 5, 'stock' => 1,
        ])->assertCreated();
        $this->getJson('/api/products')->assertJsonPath('meta.total', 2);

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();
        $this->getJson('/api/products')->assertJsonPath('meta.total', 1);
    }

    public function test_category_rename_invalidates_cache(): void
    {
        $product = Product::factory()->create();
        $this->getJson('/api/products');

        $product->category->update(['name' => 'Renamed']);
        $this->getJson('/api/products')->assertJsonPath('data.0.category.name', 'Renamed');
    }

    public function test_api_routes_are_rate_limited(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 60)
            ->assertHeader('X-RateLimit-Remaining', 59);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->create();
        $attempt = fn () => $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong']);

        foreach (range(1, 5) as $_) {
            $attempt()->assertUnprocessable();
        }
        $attempt()->assertTooManyRequests();
    }
}
