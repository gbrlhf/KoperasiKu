<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperatingExpense;
use App\Models\PurchaseReceipt;
use App\Models\ReceiptItem;
use App\Models\Supplier;
use App\Services\Pricing\WeightedAverageCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    private function actorId(Request $request): string
    {
        return (string) ($request->user()?->id ?? $request->header('X-Mock-User-Id', '00000000-0000-0000-0000-000000000001'));
    }

    public function members(Request $request)
    {
        $members = Member::when($request->search, function ($q, $s) {
            $q->where('full_name', 'ilike', "%{$s}%")
                ->orWhere('employee_number', 'ilike', "%{$s}%")
                ->orWhere('member_number', 'ilike', "%{$s}%");
        })->orderBy('full_name')->paginate(20);

        return response()->json($members);
    }

    public function storeMember(Request $request)
    {
        $validated = $request->validate([
            'employee_number' => 'required|string|max:50|unique:members,employee_number',
            'member_number' => 'required|string|max:50|unique:members,member_number',
            'full_name' => 'required|string|max:150',
            'department' => 'nullable|string|max:100',
            'status' => 'nullable|in:ACTIVE,INACTIVE',
        ]);

        $member = Member::create([
            ...$validated,
            'status' => $validated['status'] ?? 'ACTIVE',
        ]);

        return response()->json(['data' => $member], 201);
    }

    public function categories()
    {
        return response()->json(['data' => Category::orderBy('name')->get()]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        return response()->json(['data' => Category::create($validated)], 201);
    }

    public function suppliers()
    {
        return response()->json(['data' => Supplier::orderBy('name')->get()]);
    }

    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:suppliers,code',
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'lead_time_days' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier = Supplier::create([
            ...$validated,
            'lead_time_days' => $validated['lead_time_days'] ?? 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $supplier], 201);
    }

    public function receipts()
    {
        return response()->json(
            PurchaseReceipt::with(['supplier:id,code,name', 'items.product:id,sku,name'])->latest('received_at')->paginate(20)
        );
    }

    public function storeReceipt(Request $request, WeightedAverageCostService $wacService)
    {
        $validated = $request->validate([
            'receipt_number' => 'required|string|max:64|unique:purchase_receipts,receipt_number',
            'supplier_id' => 'nullable|uuid|exists:suppliers,id',
            'received_at' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $actorId = $this->actorId($request);

        $receipt = DB::transaction(function () use ($validated, $actorId, $wacService) {
            $receipt = PurchaseReceipt::create([
                'receipt_number' => $validated['receipt_number'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'received_at' => $validated['received_at'],
                'status' => 'COMPLETED',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            foreach ($validated['items'] as $item) {
                ReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                ]);

                $wacService->receiveWithCostUpdate(
                    productId: $item['product_id'],
                    incomingQty: (int) $item['quantity'],
                    incomingUnitCost: (float) $item['unit_cost'],
                    receiptReference: $receipt->receipt_number,
                    actorUserId: $actorId
                );
            }

            return $receipt->load(['supplier', 'items.product']);
        });

        return response()->json(['data' => $receipt], 201);
    }

    public function expenses()
    {
        return response()->json(OperatingExpense::latest('expense_date')->paginate(20));
    }

    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'category' => 'required|string|max:100',
            'description' => 'required|string',
        ]);

        $expense = OperatingExpense::create([
            ...$validated,
            'created_by' => $this->actorId($request),
        ]);

        return response()->json(['data' => $expense], 201);
    }
}