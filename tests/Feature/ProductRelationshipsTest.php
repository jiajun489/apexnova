<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_and_delete_rules(): void
    {
        $category = Category::create(['name' => 'Tools']);
        $product = $category->products()->create(['name' => 'Hammer', 'price' => 9.5]);
        $suppliers = [Supplier::create(['name' => 'Acme'])->id, Supplier::create(['name' => 'Bolt'])->id];
        $product->suppliers()->attach($suppliers);

        $this->assertTrue($product->category->is($category));
        $this->assertCount(2, $product->suppliers);
        $this->assertTrue(Supplier::first()->products->first()->is($product));
        $this->assertSame('9.50', $product->price);

        // soft delete keeps pivot rows (product is restorable); a hard delete cascades them
        $product->delete();
        $this->assertDatabaseCount('product_supplier', 2);
        $product->forceDelete();
        $this->assertDatabaseCount('product_supplier', 0);

        // a category with products can't be deleted
        $category->products()->create(['name' => 'Saw', 'price' => 20, 'stock' => 1]);
        $this->expectException(QueryException::class);
        $category->delete();
    }
}
