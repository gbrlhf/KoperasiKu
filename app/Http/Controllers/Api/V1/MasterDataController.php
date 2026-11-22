<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Member;
use App\Models\MemberCreditAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\ReceiptItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Services\Pricing\WeightedAverageCostService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function suppliers(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pemasok berhasil diambil.',
            'data' => $suppliers,
        ]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32', 'unique:suppliers,code'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable'],
            'status' => ['nullable', 'string'],
        ]);

        $code = ! empty($validated['code'])
            ? trim($validated['code'])
            : 'SUP-'.strtoupper(Str::random(5));

        $isActive = true;
        if ($request->filled('status')) {
            $isActive = strtoupper(trim((string) $request->input('status'))) === 'ACTIVE';
        } elseif ($request->has('is_active')) {
            $isActive = (bool) $request->boolean('is_active');
        }

        $supplier = Supplier::create([
            'code' => $code,
            'name' => trim($validated['name']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $isActive,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pemasok berhasil ditambahkan.',
            'data' => $supplier,
        ], 201);
    }

    public function updateSupplier(Request $request, string $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('suppliers', 'code')->ignore($supplier->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable'],
            'status' => ['nullable', 'string'],
        ]);

        $updates = [];
        if (! empty($validated['code'])) {
            $updates['code'] = trim($validated['code']);
        }
        if (isset($validated['name'])) {
            $updates['name'] = trim($validated['name']);
        }
        if ($request->exists('phone')) {
            $updates['phone'] = $validated['phone'];
        }
        if ($request->exists('address')) {
            $updates['address'] = $validated['address'];
        }
        if ($request->filled('status')) {
            $updates['is_active'] = strtoupper(trim((string) $request->input('status'))) === 'ACTIVE';
        } elseif ($request->has('is_active')) {
            $updates['is_active'] = (bool) $request->boolean('is_active');
        }

        $supplier->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Data pemasok berhasil diperbarui.',
            'data' => $supplier->fresh(),
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $members = Member::query()
            ->with(['creditAccount', 'user.roles'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $kw = $request->string('search');
                $q->where(function ($sub) use ($kw) {
                    $sub->where('full_name', 'ilike', "%{$kw}%")
                        ->orWhere('employee_number', 'ilike', "%{$kw}%")
                        ->orWhere('member_number', 'ilike', "%{$kw}%");
                });
            })
            ->orderBy('full_name')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => 'Daftar anggota berhasil diambil.',
            'data' => $members,
        ]);
    }

    public function storeMember(Request $request): JsonResponse
    {
        $empInput = trim((string) ($request->input('employee_number') ?: $request->input('member_number') ?: ''));
        $memInput = trim((string) ($request->input('member_number') ?: $request->input('employee_number') ?: ''));
        $request->merge([
            'employee_number' => $empInput,
            'member_number' => $memInput,
        ]);

        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:64', 'unique:members,employee_number'],
            'member_number' => ['required', 'string', 'max:64', 'unique:members,member_number'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:ACTIVE,INACTIVE'],
            'role' => ['nullable', 'string'],
            'roles' => ['nullable', 'array'],
        ]);

        $member = DB::transaction(function () use ($validated, $request): Member {
            $status = $validated['status'] ?? 'ACTIVE';
            $userId = null;

            if (! empty($validated['email'])) {
                $user = User::where('email', $validated['email'])->first();
                if (! $user) {
                    $user = User::create([
                        'email' => $validated['email'],
                        'password_hash' => Hash::make('password123'),
                        'status' => $status,
                    ]);
                } else {
                    $user->update([
                        'status' => $status,
                    ]);
                }
                $userId = $user->id;
                $this->syncUserRoles($user, $request);
            }

            $member = Member::create([
                'user_id' => $userId,
                'employee_number' => $validated['employee_number'],
                'member_number' => $validated['member_number'],
                'full_name' => $validated['full_name'],
                'department' => ! empty($validated['department']) ? $validated['department'] : 'Umum',
                'status' => $status,
            ]);

            MemberCreditAccount::create([
                'member_id' => $member->id,
                'status' => 'ACTIVE',
            ]);

            return $member->load(['creditAccount', 'user.roles']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Anggota berhasil ditambahkan.',
            'data' => $member,
        ], 201);
    }

    public function updateMember(Request $request, string $id): JsonResponse
    {
        $member = Member::with('user')->findOrFail($id);

        if ($request->filled('employee_number') && ! $request->filled('member_number')) {
            $request->merge(['member_number' => $request->input('employee_number')]);
        } elseif ($request->filled('member_number') && ! $request->filled('employee_number')) {
            $request->merge(['employee_number' => $request->input('member_number')]);
        }

        $validated = $request->validate([
            'employee_number' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('members', 'employee_number')->ignore($member->id)],
            'member_number' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('members', 'member_number')->ignore($member->id)],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'required', 'in:ACTIVE,INACTIVE'],
            'role' => ['nullable', 'string'],
            'roles' => ['nullable', 'array'],
        ]);

        $updated = DB::transaction(function () use ($member, $validated, $request): Member {
            $memberUpdates = [];
            foreach (['employee_number', 'member_number', 'full_name', 'status'] as $field) {
                if (isset($validated[$field])) {
                    $memberUpdates[$field] = $validated[$field];
                }
            }
            if (array_key_exists('department', $validated) && $validated['department'] !== null) {
                $memberUpdates['department'] = $validated['department'] ?: 'Umum';
            }

            if ($member->user) {
                $userUpdates = [];
                if (! empty($validated['email']) && $validated['email'] !== $member->user->email) {
                    $emailTaken = User::where('email', $validated['email'])->where('id', '!=', $member->user->id)->exists();
                    if (! $emailTaken) {
                        $userUpdates['email'] = $validated['email'];
                    }
                }
                if (isset($validated['status'])) {
                    $userUpdates['status'] = $validated['status'];
                }
                if (! empty($userUpdates)) {
                    $member->user->update($userUpdates);
                }
                if ($request->hasAny(['role', 'roles'])) {
                    $this->syncUserRoles($member->user, $request);
                }
            } elseif (! empty($validated['email'])) {
                $user = User::firstOrCreate(
                    ['email' => $validated['email']],
                    [
                        'password_hash' => Hash::make('password123'),
                        'status' => $validated['status'] ?? $member->status ?? 'ACTIVE',
                    ]
                );
                $memberUpdates['user_id'] = $user->id;
                $this->syncUserRoles($user, $request);
            }

            if (! empty($memberUpdates)) {
                $member->update($memberUpdates);
            }

            return $member->fresh(['creditAccount', 'user.roles']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Data anggota berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    public function stockMovements(Request $request): JsonResponse
    {
        $limit = min(100, max(10, (int) $request->integer('limit', 50)));
        $movements = StockMovement::query()
            ->with('product:id,sku,name,unit')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat mutasi stok berhasil diambil.',
            'data' => $movements,
        ]);
    }

    public function receipts(): JsonResponse
    {
        $receipts = PurchaseReceipt::with(['supplier', 'items.product'])
            ->latest('received_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Daftar penerimaan pembelian berhasil diambil.',
            'data' => $receipts,
        ]);
    }

    public function storeReceipt(Request $request, WeightedAverageCostService $wacService): JsonResponse
    {
        $validated = $request->validate([
            'receipt_number' => ['nullable', 'string', 'max:64', 'unique:purchase_receipts,receipt_number'],
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $receiptNumber = ! empty($validated['receipt_number'])
            ? trim($validated['receipt_number'])
            : 'RCV-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
        $receivedAt = ! empty($validated['received_at']) ? $validated['received_at'] : now();

        $receipt = DB::transaction(function () use ($validated, $receiptNumber, $receivedAt, $request, $wacService): PurchaseReceipt {
            $receipt = PurchaseReceipt::create([
                'receipt_number' => $receiptNumber,
                'supplier_id' => ! empty($validated['supplier_id']) ? $validated['supplier_id'] : null,
                'created_by' => $request->user()?->id,
                'received_at' => $receivedAt,
                'status' => 'COMPLETED',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = (int) $item['quantity'];
                $product = Product::findOrFail($item['product_id']);
                $unitCost = isset($item['unit_cost']) && is_numeric($item['unit_cost'])
                    ? round((float) $item['unit_cost'], 2)
                    : round((float) $product->current_cost, 2);

                $wacService->receiveWithCostUpdate(
                    productId: $item['product_id'],
                    receivedQty: $qty,
                    unitCost: $unitCost,
                    referenceType: 'purchase_receipt',
                    referenceId: $receipt->id,
                    actorUserId: $request->user()?->id,
                    reason: 'PURCHASE_RECEIPT'
                );

                ReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                ]);
            }

            return $receipt->load(['supplier', 'items.product']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Penerimaan barang berhasil dicatat dan stok serta HPP (WAC) telah diperbarui.',
            'data' => $receipt,
        ], 201);
    }

    public function storeAdjustment(Request $request, InventoryService $inventoryService): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'adjustment_type' => ['required', 'string', 'in:ADDITION,DEDUCTION,OPNAME'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $rawKey = trim((string) (
            $request->header('Idempotency-Key')
            ?: $request->header('X-Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? '')
        ));

        if ($rawKey !== '') {
            $sourceReference = Str::isUuid($rawKey)
                ? strtolower($rawKey)
                : $this->deterministicUuid('adj-key:' . $rawKey);
        } else {
            $minuteBucket = CarbonImmutable::now('Asia/Jakarta')->format('YmdHi');
            $seed = implode('|', [
                'adj',
                (string) $validated['product_id'],
                strtoupper((string) $validated['adjustment_type']),
                (string) $validated['quantity'],
                $minuteBucket,
            ]);
            $sourceReference = $this->deterministicUuid($seed);
        }

        try {
            $result = DB::transaction(function () use ($validated, $request, $inventoryService, $sourceReference): array {
                $product = Product::query()->whereKey($validated['product_id'])->lockForUpdate()->firstOrFail();
                $balance = InventoryBalance::query()->where('product_id', $product->id)->lockForUpdate()->first();

                if (! $balance) {
                    $balance = InventoryBalance::create([
                        'product_id' => $product->id,
                        'on_hand' => 0,
                        'reserved' => 0,
                    ]);
                }

                $oldOnHand = (int) $balance->on_hand;
                $reserved = (int) $balance->reserved;
                $qty = (int) $validated['quantity'];
                $type = $validated['adjustment_type'];
                $movementReason = "[{$type}] ".trim((string) $validated['reason']);

                $existingMovement = StockMovement::query()
                    ->where('product_id', $product->id)
                    ->where('type', 'ADJUSTMENT')
                    ->where('reference_id', $sourceReference)
                    ->first();

                if ($existingMovement) {
                    $conflict = match ($type) {
                        'ADDITION' => (int) $existingMovement->quantity_change !== $qty || $existingMovement->reason !== $movementReason,
                        'DEDUCTION' => (int) $existingMovement->quantity_change !== -$qty || $existingMovement->reason !== $movementReason,
                        default => $oldOnHand !== $qty || $existingMovement->reason !== $movementReason,
                    };

                    if ($conflict) {
                        throw new BusinessRuleException('Referensi penyesuaian stok sudah digunakan untuk nilai berbeda.', 409, 'IDEMPOTENCY_CONFLICT');
                    }

                    return [
                        'idempotent' => true,
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'old_on_hand' => $oldOnHand - (int) $existingMovement->quantity_change,
                        'new_on_hand' => $oldOnHand,
                        'reserved' => $reserved,
                        'available_stock' => $oldOnHand - $reserved,
                        'delta' => (int) $existingMovement->quantity_change,
                        'movement_id' => $existingMovement->id,
                        'reference_id' => $sourceReference,
                    ];
                }

                if ($type === 'ADDITION') {
                    if ($qty <= 0) {
                        throw new BusinessRuleException('Jumlah penambahan stok harus lebih dari 0.');
                    }
                    $delta = $qty;
                    $newOnHand = $oldOnHand + $qty;
                } elseif ($type === 'DEDUCTION') {
                    if ($qty <= 0) {
                        throw new BusinessRuleException('Jumlah pengurangan stok harus lebih dari 0.');
                    }
                    $delta = -$qty;
                    $newOnHand = $oldOnHand - $qty;
                } else {
                    $newOnHand = $qty;
                    $delta = $newOnHand - $oldOnHand;
                }

                if ($newOnHand < 0) {
                    throw new BusinessRuleException('Stok fisik hasil penyesuaian tidak boleh bernilai negatif.');
                }

                if ($newOnHand < $reserved) {
                    throw new BusinessRuleException("Stok fisik ({$newOnHand}) tidak boleh lebih kecil dari stok yang sedang direservasi pesanan aktif ({$reserved}).");
                }

                $movementId = null;
                if ($delta !== 0) {
                    $inventoryService->adjust(
                        productId: (string) $product->id,
                        delta: $delta,
                        reason: $movementReason,
                        actorUserId: (string) ($request->user()?->id ?? 'SYSTEM'),
                        sourceReference: $sourceReference
                    );

                    $movementId = StockMovement::query()
                        ->where('product_id', $product->id)
                        ->where('type', 'ADJUSTMENT')
                        ->where('reference_id', $sourceReference)
                        ->latest('created_at')
                        ->value('id');
                }

                return [
                    'idempotent' => false,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'old_on_hand' => $oldOnHand,
                    'new_on_hand' => $newOnHand,
                    'reserved' => $reserved,
                    'available_stock' => $newOnHand - $reserved,
                    'delta' => $delta,
                    'movement_id' => $movementId,
                    'reference_id' => $sourceReference,
                ];
            }, 3);
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        }

        $isReplay = (bool) ($result['idempotent'] ?? false);

        return response()->json([
            'success' => true,
            'message' => $isReplay
                ? 'Permintaan penyesuaian stok sebelumnya dikembalikan (Idempotent).'
                : 'Penyesuaian stok berhasil disimpan ke kartu stok.',
            'data' => $result,
        ], $isReplay ? 200 : 201);
    }

    private function deterministicUuid(string $seed): string
    {
        $hash = hash('sha256', $seed);

        return sprintf(
            '%s-%s-4%s-%x%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            (hexdec(substr($hash, 16, 1)) & 0x3) | 0x8,
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    public function expenses(): JsonResponse
    {
        $expenses = OperatingExpense::query()
            ->latest('expense_date')
            ->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'Daftar biaya operasional berhasil diambil.',
            'data' => $expenses,
        ]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'proof' => ['nullable'],
        ]);

        $proofPath = null;
        if ($request->hasFile('proof') && $request->file('proof')->isValid()) {
            $file = $request->file('proof');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $filename = 'exp-'.now()->format('YmdHis').'-'.Str::random(6).'.'.$ext;
            $destDir = public_path('uploads/expenses');
            if (! is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            $file->move($destDir, $filename);
            $proofPath = '/uploads/expenses/'.$filename;
        }

        $expense = OperatingExpense::create([
            'expense_date' => $validated['expense_date'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'amount' => round((float) $validated['amount'], 2),
            'proof_path' => $proofPath,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Biaya operasional berhasil dicatat.',
            'data' => $expense,
        ], 201);
    }

    private function syncUserRoles(User $user, Request $request): void
    {
        $roleNames = [];
        if ($request->filled('roles') && is_array($request->input('roles'))) {
            $roleNames = array_map(fn ($r) => strtoupper(trim((string) $r)), $request->input('roles'));
        } elseif ($request->filled('role')) {
            $roleNames = [strtoupper(trim((string) $request->input('role')))];
        } else {
            $roleNames = ['MEMBER'];
        }

        if (! in_array('MEMBER', $roleNames, true)) {
            $roleNames[] = 'MEMBER';
        }

        $roleCodes = [];
        foreach (array_unique($roleNames) as $roleCode) {
            if (in_array($roleCode, ['MEMBER', 'CASHIER', 'MANAGER'], true)) {
                Role::firstOrCreate(['code' => $roleCode], ['label' => $roleCode]);
                $roleCodes[] = $roleCode;
            }
        }

        if (! empty($roleCodes)) {
            $user->roles()->sync($roleCodes);
        }
    }
}
