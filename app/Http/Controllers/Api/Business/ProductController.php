<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Business;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     *
     * @throws AuthorizationException
     */
    public function index(): JsonResource
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['business', 'category', 'brand', 'unit', 'variations'])
            ->allowedFilters(['name', 'sku', 'type', 'business_id', 'category_id', 'brand_id'])
            ->allowedSorts(['id', 'name', 'sku', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created product in storage.
     *
     * @throws AuthorizationException
     */
    public function store(ProductRequest $request, Business $business): ProductResource
    {
        $this->authorize('create', [Product::class, $business]);

        $data = $request->validated();
        $productData = $data['data']['attributes'];

        $product = DB::transaction(function () use ($productData) {
            // Manejar la imagen si se proporciona
            if (isset($productData['image'])) {
                $productData['image'] = $this->handleImageUpload($productData['image']);
            }

            $product = Product::create($productData);

            // Crear variación por defecto si el producto es variable
            if ($product->type === 'variable' && isset($productData['variations'])) {
                foreach ($productData['variations'] as $variationData) {
                    $product->variations()->create($variationData);
                }
            }

            return $product;
        });

        return ProductResource::make($product->load(['business', 'category', 'brand', 'unit', 'variations']));
    }

    /**
     * Display the specified product.
     *
     * @throws AuthorizationException
     */
    public function show(Business $business, Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $product->load([
            'business',
            'category',
            'brand',
            'unit',
            'variations.variation_location_details',
            'product_variations'
        ]);

        return ProductResource::make($product);
    }

    /**
     * Update the specified product in storage.
     *
     * @throws AuthorizationException
     */
    public function update(ProductRequest $request, Business $business, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $productData = $data['data']['attributes'];

        DB::transaction(function () use ($product, $productData) {
            // Manejar la imagen si se proporciona
            if (isset($productData['image'])) {
                // Eliminar imagen anterior si existe
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $productData['image'] = $this->handleImageUpload($productData['image']);
            }

            $product->update($productData);

            // Actualizar variaciones si se proporcionan
            if (isset($productData['variations'])) {
                $product->variations()->delete();
                foreach ($productData['variations'] as $variationData) {
                    $product->variations()->create($variationData);
                }
            }
        });

        return ProductResource::make($product->load(['business', 'category', 'brand', 'unit', 'variations']));
    }

    /**
     * Remove the specified product from storage.
     *
     * @throws AuthorizationException
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        DB::transaction(function () use ($product) {
            // Eliminar imagen si existe
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            // Eliminar variaciones relacionadas
            $product->variations()->delete();
            $product->product_variations()->delete();

            $product->delete();
        });

        return response()->json([], 204);
    }

    /**
     * Toggle product status (enable/disable stock).
     *
     * @throws AuthorizationException
     */
    public function toggleStock(Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $product->update(['enable_stock' => !$product->enable_stock]);

        return ProductResource::make($product);
    }

    /**
     * Get product stock information.
     *
     * @throws AuthorizationException
     */
    public function stock(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $stockInfo = [];

        foreach ($product->variations as $variation) {
            $locationDetails = $variation->variation_location_details;
            $totalStock = $locationDetails->sum('qty_available');

            $stockInfo[] = [
                'variation_id' => $variation->id,
                'variation_name' => $variation->name ?? $product->name,
                'total_stock' => $totalStock,
                'locations' => $locationDetails->map(function ($detail) {
                    return [
                        'location_id' => $detail->location_id,
                        'location_name' => $detail->businessLocation->name ?? 'Sin ubicación',
                        'quantity' => $detail->qty_available,
                        'unit_price' => $detail->unit_price,
                    ];
                }),
            ];
        }

        return response()->json([
            'data' => [
                'type' => 'product-stock',
                'id' => (string) $product->id,
                'attributes' => [
                    'product_name' => $product->name,
                    'enable_stock' => $product->enable_stock,
                    'alert_quantity' => $product->alert_quantity,
                    'stock_details' => $stockInfo,
                ]
            ]
        ]);
    }

    /**
     * Duplicate a product.
     *
     * @throws AuthorizationException
     */
    public function duplicate(Product $product): ProductResource
    {
        $this->authorize('create', Product::class);

        $newProduct = DB::transaction(function () use ($product) {
            $productData = $product->toArray();

            // Remover campos que no deben duplicarse
            unset($productData['id'], $productData['sku'], $productData['created_at'], $productData['updated_at']);

            // Generar nuevo SKU
            $productData['name'] = $productData['name'] . ' (Copia)';
            $productData['sku'] = $this->generateUniqueSku($product->business_id, $productData['name']);

            $newProduct = Product::create($productData);

            // Duplicar variaciones
            foreach ($product->variations as $variation) {
                $variationData = $variation->toArray();
                unset($variationData['id'], $variationData['product_id'], $variationData['created_at'], $variationData['updated_at']);

                $newProduct->variations()->create($variationData);
            }

            return $newProduct;
        });

        return ProductResource::make($newProduct->load(['business', 'category', 'brand', 'unit', 'variations']));
    }

    /**
     * Handle image upload.
     */
    private function handleImageUpload($imageData): string
    {
        if (is_string($imageData) && str_starts_with($imageData, 'data:image/')) {
            // Es una imagen en base64
            $image = str_replace('data:image/', '', $imageData);
            $image = explode(';base64,', $image);
            $imageType = $image[0];
            $imageData = base64_decode($image[1]);

            $fileName = 'products/' . uniqid() . '.' . $imageType;
            Storage::disk('public')->put($fileName, $imageData);

            return $fileName;
        }

        return $imageData; // Asumir que ya es una ruta válida
    }

    /**
     * Generate unique SKU for the product.
     */
    private function generateUniqueSku(int $businessId, string $productName): string
    {
        $business = Business::find($businessId);
        $prefix = $business->sku_prefix ?? 'PRD';

        // Generar SKU basado en el nombre del producto
        $baseSku = $prefix . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $productName), 0, 6));

        $counter = 1;
        $sku = $baseSku . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);

        while (Product::where('business_id', $businessId)->where('sku', $sku)->exists()) {
            $counter++;
            $sku = $baseSku . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        }

        return $sku;
    }
}
