<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /** List products (filtered, paginated) */
    public function index(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $params = [...$request->validated(), 'page' => $request->integer('page', 1)];
        ksort($params);
        $key = 'products:'.Product::cacheVersion().':index:'.md5(json_encode($params));

        $products = Cache::remember($key, now()->addMinutes(10), fn () => Product::with(['category', 'suppliers'])
            ->filter($request->validated())
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString());

        // ponytail: only the list is cached; show() is a primary-key lookup, cache it too if profiling says so
        return ProductResource::collection($products);
    }

    /** Create a product */
    public function store(ProductRequest $request): ProductResource
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('supplier_ids'));
            $product->suppliers()->sync($request->input('supplier_ids', []));

            return $product;
        });

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    /** Show a product */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'suppliers']));
    }

    /** Update a product */
    public function update(ProductRequest $request, Product $product): ProductResource
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->except('supplier_ids'));

            if ($request->has('supplier_ids')) {
                $product->suppliers()->sync($request->input('supplier_ids'));
            }
        });

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    /** Delete a product (soft delete) */
    public function destroy(Product $product): Response
    {
        $product->delete(); // soft delete

        return response()->noContent();
    }
}
