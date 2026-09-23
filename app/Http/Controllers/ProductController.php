<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * No tenant filtering here on purpose: TenantScope applies it to every query,
 * including route model binding, which turns another tenant's product into a 404.
 */
class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(Product::query()->orderBy('id')->paginate());
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }
}
