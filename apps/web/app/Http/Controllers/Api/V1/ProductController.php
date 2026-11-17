<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Pricing\PriceChangeService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private function isManager(Request $request): bool
    {
        $role = $request->user()?->role ?? (app()->environment('local') ? strtoupper((string) $request->header('X-Mock-Role')) : '');
        return $role === 'MANAGER';
    }

    private function actorId(Request $request): string
    {
        return (string) ($request->user()?->id ?? $request->header('X-Mock-User-Id', '00000000-0000-0000-0000-000000000001'));
    }

    public function index(Request $request)
    {
        $query = Product::with(['category:id,name', 'supplier:id,code,name'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%")
                        ->orWhere('barcode', $search);
                });
            })
            ->when($request->category_id, fn ($q, $catId) => $q->where('category_id', $catId))
            ->orderBy('name');

        $products = $query->paginate(20);

        if ($this->isManager($request)) {
            $products->getCollection()->each->makeVisible('current_cost');
        }

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|uuid|exists:categories,id',
            'supplier_id' => 'nullable|uuid|exists:suppliers,id',
            'sku' => 'required|string|max:64|unique:products,sku',
            'barcode' => 'nullable|string|max:64|unique:products,barcode',
            'name' => 'required|string|max:200',
            'unit' => 'nullable|string|max:30',
            'selling_price' => 'required|numeric|min:0',
            'current_cost' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $product = Product::create([
            ...$validated,
            'unit' => $validated['unit'] ?? 'pcs',
            'current_cost' => $validated['current_cost'] ?? 0,
            'reorder_point' => $validated['reorder_point'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($this->isManager($request)) {
            $product->makeVisible('current_cost');
        }

        return response()->json(['data' => $product], 201);
    }

    public function show(Request $request, string $id)
    {
        $product = Product::with(['category', 'supplier', 'priceHistories'])->findOrFail($id);

        if ($this->isManager($request)) {
            $product->makeVisible('current_cost');
        }

        return response()->json(['data' => $product]);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|uuid|exists:categories,id',
            'supplier_id' => 'nullable|uuid|exists:suppliers,id',
            'barcode' => "nullable|string|max:64|unique:products,barcode,{$product->id}",
            'name' => 'sometimes|string|max:200',
            'unit' => 'sometimes|string|max:30',
            'reorder_point' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $product->update($validated);

        if ($this->isManager($request)) {
            $product->makeVisible('current_cost');
        }

        return response()->json(['data' => $product]);
    }

    public function updatePrice(Request $request, string $id, PriceChangeService $priceService)
    {
        $validated = $request->validate([
            'selling_price' => 'required|numeric|min:0',
            'current_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        $result = $priceService->updatePricing(
            productId: $id,
            newSellPrice: (float) $validated['selling_price'],
            newCost: isset($validated['current_cost']) ? (float) $validated['current_cost'] : null,
            reason: $validated['reason'],
            actorUserId: $this->actorId($request)
        );

        return response()->json($result);
    }
}