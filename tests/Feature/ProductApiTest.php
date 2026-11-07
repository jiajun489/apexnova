<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_login_issues_token_that_grants_access(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/products')->assertOk();
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // the guard caches the resolved user between requests in one test; reset it so the token is re-checked
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/products')->assertUnauthorized();
    }

    public function test_guests_cannot_access_products(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/products')->assertUnauthorized();
        $this->postJson('/api/products', [])->assertUnauthorized();
        $this->deleteJson("/api/products/{$product->id}")->assertUnauthorized();
    }

    public function test_index_paginates_and_formats_products(): void
    {
        $this->actingAsUser();
        Product::factory(20)->create();

        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'price', 'stock', 'stock_status', 'category' => ['id', 'name'], 'suppliers']],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_index_filters_by_category_price_and_stock(): void
    {
        $this->actingAsUser();
        $tools = Category::factory()->create();
        $match = Product::factory()->for($tools)->create(['price' => 50, 'stock' => 5]);
        Product::factory()->for($tools)->create(['price' => 500, 'stock' => 5]);  // price too high
        $plenty = Product::factory()->for($tools)->create(['price' => 50, 'stock' => 100]); // stock too high
        Product::factory()->create(['price' => 50, 'stock' => 5]);                // other category

        $query = "category_id={$tools->id}&min_price=10&max_price=100&max_stock=10";
        $this->getJson("/api/products?$query")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->getJson('/api/products?stock_status=low_stock&category_id='.$tools->id)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/products?stock_status=in_stock&category_id='.$tools->id)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plenty->id);

        // boundary: exactly LOW_STOCK_THRESHOLD (10) units is low_stock, not in_stock
        // boundary: 1 unit is low_stock; 0 units is out_of_stock, not low_stock
        $edge = Product::factory()->create(['stock' => Product::LOW_STOCK_THRESHOLD]);
        $one = Product::factory()->for($edge->category)->create(['stock' => 1]);
        $empty = Product::factory()->for($edge->category)->create(['stock' => 0]);
        $this->getJson("/api/products?stock_status=in_stock&category_id={$edge->category_id}")->assertJsonCount(0, 'data');
        $this->getJson("/api/products?stock_status=low_stock&category_id={$edge->category_id}")
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $one->id)
            ->assertJsonPath('data.1.id', $edge->id)
            ->assertJsonPath('data.1.stock_status', 'low_stock'); // accessor agrees with the filter
        $this->getJson("/api/products?stock_status=out_of_stock&category_id={$edge->category_id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $empty->id)
            ->assertJsonPath('data.0.stock_status', 'out_of_stock');

        $this->getJson('/api/products?max_price=100&max_stock=10')->assertOk(); // upper bound alone is valid

        $this->getJson('/api/products?min_price=100&max_price=10')
            ->assertUnprocessable()->assertJsonValidationErrors('max_price');
    }

    public function test_store_validates_and_creates_product_with_suppliers(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/products', ['name' => 'x', 'price' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'price', 'stock']);

        $category = Category::factory()->create();
        $suppliers = Supplier::factory(2)->create();

        $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => '  Claw   Hammer ',
            'price' => 19.99,
            'stock' => 0,
            'supplier_ids' => $suppliers->pluck('id')->all(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Claw Hammer') // mutator squished whitespace
            ->assertJsonPath('data.price', '19.99')
            ->assertJsonPath('data.stock_status', 'out_of_stock') // accessor
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonCount(2, 'data.suppliers');
    }

    public function test_show_and_update_product(): void
    {
        $this->actingAsUser();
        $product = Product::factory()->create(['stock' => 50]);
        $supplier = Supplier::factory()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()->assertJsonPath('data.id', $product->id);

        $this->patchJson("/api/products/{$product->id}", ['stock' => 3, 'supplier_ids' => [$supplier->id]])
            ->assertOk()
            ->assertJsonPath('data.stock', 3)
            ->assertJsonPath('data.stock_status', 'low_stock')
            ->assertJsonPath('data.name', $product->name) // untouched fields kept
            ->assertJsonPath('data.suppliers.0.id', $supplier->id);

        $this->getJson('/api/products/999999')->assertNotFound();
    }

    public function test_destroy_soft_deletes_product(): void
    {
        $this->actingAsUser();
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertSoftDeleted($product);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
        $this->getJson('/api/products')->assertJsonCount(0, 'data');
    }
}
