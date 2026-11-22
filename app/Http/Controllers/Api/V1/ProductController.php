<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Services\Pricing\PriceChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with(['categoryRelation', 'supplier', 'inventory']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->string('category_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->string('supplier_id'));
        }

        if ($request->filled('search')) {
            $kw = $request->string('search');
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'ilike', "%{$kw}%")
                    ->orWhere('sku', 'ilike', "%{$kw}%")
                    ->orWhere('barcode', 'ilike', "%{$kw}%");
            });
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($request->boolean('low_stock_only', false)) {
            $query->whereHas('inventory', function ($q) {
                $q->whereRaw('(on_hand - reserved) <= products.reorder_point');
            });
        }

        $products = $query->orderBy('name')->paginate((int) $request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => 'Daftar produk berhasil diambil.',
            'data' => $products,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with(['categoryRelation', 'supplier', 'inventory', 'priceHistories' => function ($q) {
            $q->latest('changed_at')->limit(20);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail produk berhasil diambil.',
            'data' => $product,
        ]);
    }

    public function store(Request $request, InventoryService $inventoryService, AuditService $auditService): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:products,barcode'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'current_cost' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'allow_below_cost' => ['nullable', 'boolean'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
            'status' => ['nullable', 'string'],
            'image' => ['nullable'],
        ]);

        [$categoryId, $categoryName] = $this->resolveCategory(
            $validated['category_id'] ?? null,
            $validated['category'] ?? null
        );

        $sellingPrice = (float) ($validated['selling_price'] ?? $validated['price'] ?? 0);
        $currentCost = (float) ($validated['current_cost'] ?? $validated['cost_price'] ?? 0);
        $allowBelowCost = $request->boolean('allow_below_cost');

        try {
            $this->assertValidSellingMargin($sellingPrice, $currentCost, $allowBelowCost);
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        }
        $reorderPoint = (int) ($validated['reorder_point'] ?? $validated['min_stock'] ?? 5);
        $initialStock = (int) ($validated['initial_stock'] ?? 0);
        $isActive = $this->resolveIsActive($request, true);
        $imageUrl = $this->handleImageUpload($request);

        $product = DB::transaction(function () use (
            $validated,
            $categoryId,
            $categoryName,
            $sellingPrice,
            $currentCost,
            $reorderPoint,
            $initialStock,
            $isActive,
            $imageUrl,
            $request,
            $inventoryService,
            $auditService
        ): Product {
            $product = Product::create([
                'category_id' => $categoryId,
                'category' => $categoryName,
                'supplier_id' => ! empty($validated['supplier_id']) ? $validated['supplier_id'] : null,
                'sku' => trim($validated['sku']),
                'barcode' => ! empty($validated['barcode']) ? trim($validated['barcode']) : null,
                'name' => trim($validated['name']),
                'unit' => ! empty($validated['unit']) ? trim($validated['unit']) : 'Pcs',
                'selling_price' => $sellingPrice,
                'current_cost' => $currentCost,
                'reorder_point' => $reorderPoint,
                'image' => $imageUrl,
                'image_path' => $imageUrl,
                'is_active' => $isActive,
            ]);

            InventoryBalance::create([
                'product_id' => $product->id,
                'on_hand' => 0,
                'reserved' => 0,
            ]);

            if ($initialStock > 0) {
                $inventoryService->receive(
                    productId: $product->id,
                    quantity: $initialStock,
                    reason: 'PURCHASE_RECEIPT',
                    actorUserId: (string) ($request->user()?->id ?? 'SYSTEM'),
                    sourceReference: $product->id
                );
            }

            $auditService->record(
                action: 'PRODUCT_CREATED',
                entityType: 'product',
                entityId: (string) $product->id,
                oldValues: null,
                newValues: [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'selling_price' => $product->selling_price,
                    'initial_stock' => $initialStock,
                ],
                actorId: $request->user()?->id
            );

            return $product->load(['categoryRelation', 'supplier', 'inventory']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan.',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, string $id, PriceChangeService $priceChangeService, AuditService $auditService): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'sku' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product->id)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($product->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:32'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'current_cost' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'allow_below_cost' => ['nullable', 'boolean'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
            'status' => ['nullable', 'string'],
            'image' => ['nullable'],
        ]);

        try {
            $updatedProduct = DB::transaction(function () use ($product, $validated, $request, $priceChangeService, $auditService): Product {
                $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

                $updates = [];

                if ($request->hasAny(['category_id', 'category'])) {
                    [$categoryId, $categoryName] = $this->resolveCategory(
                        $validated['category_id'] ?? null,
                        $validated['category'] ?? null
                    );
                    $updates['category_id'] = $categoryId;
                    $updates['category'] = $categoryName;
                }

                if ($request->exists('supplier_id')) {
                    $updates['supplier_id'] = ! empty($validated['supplier_id']) ? $validated['supplier_id'] : null;
                }

                if (isset($validated['sku'])) {
                    $updates['sku'] = trim($validated['sku']);
                }

                if ($request->exists('barcode')) {
                    $updates['barcode'] = ! empty($validated['barcode']) ? trim($validated['barcode']) : null;
                }

                if (isset($validated['name'])) {
                    $updates['name'] = trim($validated['name']);
                }

                if (! empty($validated['unit'])) {
                    $updates['unit'] = trim($validated['unit']);
                }

                if ($request->hasAny(['reorder_point', 'min_stock'])) {
                    $updates['reorder_point'] = (int) ($validated['reorder_point'] ?? $validated['min_stock'] ?? $locked->reorder_point);
                }

                if ($request->hasAny(['is_active', 'status'])) {
                    $updates['is_active'] = $this->resolveIsActive($request, (bool) $locked->is_active);
                }

                $imageUrl = $this->handleImageUpload($request);
                if ($imageUrl !== null) {
                    $updates['image'] = $imageUrl;
                    $updates['image_path'] = $imageUrl;
                }

                $oldCost = (float) $locked->current_cost;
                $newCost = $request->hasAny(['current_cost', 'cost_price'])
                    ? (float) ($validated['current_cost'] ?? $validated['cost_price'] ?? $oldCost)
                    : $oldCost;

                $effectiveSellingPrice = $request->hasAny(['selling_price', 'price'])
                    ? (float) ($validated['selling_price'] ?? $validated['price'] ?? 0)
                    : (float) $locked->selling_price;

                if ($request->hasAny(['selling_price', 'price', 'current_cost', 'cost_price'])) {
                    $this->assertValidSellingMargin($effectiveSellingPrice, $newCost, $request->boolean('allow_below_cost'));
                }

                if (round($newCost, 2) !== round($oldCost, 2)) {
                    $updates['current_cost'] = round($newCost, 2);
                }

                if (! empty($updates)) {
                    $locked->update($updates);
                }

                if ($request->hasAny(['selling_price', 'price'])) {
                    $locked = $priceChangeService->updateSellingPrice(
                        product: $locked,
                        newSellingPrice: $effectiveSellingPrice,
                        actorUserId: $request->user()?->id,
                        reason: 'Perubahan harga jual melalui form Edit Produk'
                    );
                } elseif (round($newCost, 2) !== round($oldCost, 2)) {
                    ProductPriceHistory::create([
                        'product_id' => $locked->id,
                        'old_sell_price' => $locked->selling_price,
                        'new_sell_price' => $locked->selling_price,
                        'old_cost' => $oldCost,
                        'new_cost' => round($newCost, 2),
                        'changed_by' => $request->user()?->id,
                        'reason' => 'Perubahan HPP melalui form Edit Produk',
                        'changed_at' => now(),
                    ]);
                }

                $auditService->record(
                    action: 'PRODUCT_UPDATED',
                    entityType: 'product',
                    entityId: (string) $locked->id,
                    oldValues: null,
                    newValues: $updates,
                    actorId: $request->user()?->id
                );

                return $locked->fresh(['categoryRelation', 'supplier', 'inventory']);
            });
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        }

        return response()->json([
            'success' => true,
            'message' => 'Data produk berhasil diperbarui.',
            'data' => $updatedProduct,
        ]);
    }

    public function updatePrice(Request $request, string $id, PriceChangeService $priceChangeService): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'selling_price' => ['required', 'numeric', 'min:0'],
            'allow_below_cost' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $newPrice = (float) $validated['selling_price'];
            $this->assertValidSellingMargin($newPrice, (float) $product->current_cost, $request->boolean('allow_below_cost'));

            $updatedProduct = $priceChangeService->updateSellingPrice(
                product: $product,
                newSellingPrice: $newPrice,
                actorUserId: $request->user()?->id,
                reason: $validated['reason'] ?? null
            );
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        }

        return response()->json([
            'success' => true,
            'message' => 'Harga jual produk berhasil diperbarui dan dicatat di histori harga.',
            'data' => $updatedProduct,
        ]);
    }

    private function assertValidSellingMargin(float $sellingPrice, float $currentCost, bool $allowBelowCost): void
    {
        if ($sellingPrice <= 0) {
            throw new BusinessRuleException('Harga jual harus lebih besar dari 0.', 422, 'INVALID_SELLING_PRICE');
        }

        if (! $allowBelowCost && round($sellingPrice, 2) < round($currentCost, 2)) {
            throw new BusinessRuleException(
                'Harga jual tidak boleh lebih rendah dari HPP saat ini, kecuali flag allow_below_cost=true disertakan.',
                422,
                'SELLING_PRICE_BELOW_COST'
            );
        }
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function resolveCategory(?string $categoryId, ?string $categoryName): array
    {
        if (! empty($categoryId)) {
            $cat = Category::find($categoryId);
            if ($cat) {
                return [$cat->id, $cat->name];
            }
        }

        $trimmedName = trim((string) $categoryName);
        if ($trimmedName !== '') {
            $cat = Category::whereRaw('LOWER(name) = ?', [strtolower($trimmedName)])->first()
                ?? Category::create(['name' => $trimmedName]);

            return [$cat->id, $cat->name];
        }

        $defaultCat = Category::firstOrCreate(['name' => 'Umum']);

        return [$defaultCat->id, $defaultCat->name];
    }

    private function resolveIsActive(Request $request, bool $default): bool
    {
        if ($request->filled('status')) {
            return strtoupper(trim((string) $request->input('status'))) === 'ACTIVE';
        }

        if ($request->has('is_active')) {
            return filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        return $default;
    }

    private function handleImageUpload(Request $request): ?string
    {
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $filename = 'prod-'.now()->format('YmdHis').'-'.Str::random(6).'.'.$ext;
            $destDir = public_path('uploads/products');
            if (! is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            $file->move($destDir, $filename);

            return '/uploads/products/'.$filename;
        }

        if ($request->filled('image') && is_string($request->input('image'))) {
            return trim((string) $request->input('image'));
        }

        return null;
    }
}
