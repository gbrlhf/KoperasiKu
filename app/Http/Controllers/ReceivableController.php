<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\CreditPolicy;
use App\Models\CreditPolicyLog;
use App\Models\Member;
use App\Models\MemberCreditAccount;
use App\Models\Receivable;
use App\Services\AuditService;
use App\Services\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceivableController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected AuditService $audit
    ) {}

    /**
     * Mencatat pembayaran cicilan / pelunasan piutang (POST /api/v1/receivables/payments)
     */
    public function pay(Request $request)
    {
        try {
            $validated = $request->validate([
                'member_id' => 'required|uuid',
                'method' => 'required|string|in:CASH,BANK_TRANSFER',
                'amount' => 'required|numeric|min:0.01',
                'external_reference' => 'nullable|string|max:100',
                'idempotency_key' => 'nullable|string|max:100',
                'allocations' => 'required|array|min:1',
                'allocations.*.receivable_id' => 'required|uuid',
                'allocations.*.amount' => 'required|numeric|min:0.01',
            ]);

            $user = $request->user();
            $result = $this->transactionService->payReceivable(
                $validated,
                $user?->id ? (string) $user->id : null,
                $user?->member?->id ? (string) $user->member->id : null
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage()
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Pay receivable error', ['error' => $e->getMessage(), 'user_id' => $request->user()?->id]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Terjadi kesalahan saat mencatat pembayaran piutang.'
            ], 500);
        }
    }

    /**
     * Ringkasan limit kredit global & saldo milik anggota sendiri (GET /api/v1/members/me/credit)
     */
    public function myCredit(Request $request)
    {
        $member = $request->user()?->member;
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Profil anggota tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transactionService->getMemberCreditSummary((string) $member->id),
        ]);
    }

    /**
     * Daftar tagihan piutang milik anggota sendiri (GET /api/v1/members/me/receivables)
     */
    public function myReceivables(Request $request)
    {
        $member = $request->user()?->member;
        if (!$member) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = Receivable::with(['order:id,order_no,pickup_code,completed_at', 'allocations.payment'])
            ->where('member_id', $member->id)
            ->orderBy('due_date')
            ->get()
            ->map(function (Receivable $r) {
                return array_merge($r->toArray(), ['effective_status' => $r->effectiveStatus()]);
            });

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    /**
     * Daftar tagihan piutang anggota saat dilayani di kasir (GET /api/v1/cashier/receivables?member_id=...)
     */
    public function cashierReceivables(Request $request)
    {
        $rawMember = trim((string) $request->query('member_id', ''));
        if ($rawMember === '') {
            return response()->json(['success' => false, 'message' => 'Parameter member_id wajib diisi.'], 422);
        }

        $member = Str::isUuid($rawMember)
            ? Member::where('id', $rawMember)->first()
            : Member::whereRaw('UPPER(employee_number) = ?', [strtoupper($rawMember)])
                ->orWhereRaw('UPPER(member_number) = ?', [strtoupper($rawMember)])
                ->first();

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Anggota tidak ditemukan.'], 404);
        }

        $receivables = Receivable::with(['order:id,order_no,completed_at'])
            ->where('member_id', $member->id)
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date')
            ->get()
            ->map(fn (Receivable $r) => array_merge($r->toArray(), ['effective_status' => $r->effectiveStatus()]));

        return response()->json([
            'success' => true,
            'data' => [
                'member' => $member,
                'credit_summary' => $this->transactionService->getMemberCreditSummary((string) $member->id),
                'receivables' => $receivables,
            ],
        ]);
    }

    /**
     * Daftar seluruh piutang aktif & overdue untuk Manajer (GET /api/v1/manager/receivables)
     */
    public function managerReceivables(Request $request)
    {
        $rows = Receivable::with(['member:id,employee_number,member_number,full_name,department', 'order:id,order_no,completed_at'])
            ->orderBy('due_date')
            ->get()
            ->map(fn (Receivable $r) => array_merge($r->toArray(), ['effective_status' => $r->effectiveStatus()]));

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    /**
     * Kebijakan limit piutang global (GET /api/v1/credit-policy)
     */
    public function getCreditPolicy()
    {
        $policy = CreditPolicy::where('active', true)->latest('effective_at')->first();
        return response()->json(['success' => true, 'data' => $policy]);
    }

    /**
     * Ubah kebijakan limit global oleh Manajer — due_days tetap terkunci 7 hari (PATCH /api/v1/credit-policy)
     */
    public function updateCreditPolicy(Request $request)
    {
        $validated = $request->validate([
            'limit_amount' => 'required|numeric|min:0',
            'active' => 'nullable|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $actor = $request->user();
        $result = DB::transaction(function () use ($validated, $actor) {
            $oldPolicy = CreditPolicy::where('active', true)->latest('effective_at')->lockForUpdate()->first();
            $oldLimit = $oldPolicy ? (float) $oldPolicy->limit_amount : null;
            $oldActive = $oldPolicy ? (bool) $oldPolicy->active : null;

            $newActive = $validated['active'] ?? true;
            $newLimit = (float) $validated['limit_amount'];

            if ($oldPolicy) {
                $oldPolicy->update(['active' => false]);
            }

            $newPolicy = CreditPolicy::create([
                'limit_amount' => $newLimit,
                'due_days' => 7, // Terkunci sesuai PRD FR-08A
                'active' => $newActive,
                'effective_at' => CarbonImmutable::now('UTC'),
            ]);

            CreditPolicyLog::create([
                'credit_policy_id' => $newPolicy->id,
                'old_limit_amount' => $oldLimit,
                'new_limit_amount' => $newLimit,
                'old_active' => $oldActive,
                'new_active' => $newActive,
                'changed_by' => $actor?->id,
                'reason' => $validated['reason'] ?? 'Pembaruan kebijakan limit kredit global oleh Manajer',
                'changed_at' => CarbonImmutable::now('UTC'),
            ]);

            $this->audit->record(
                'CREDIT_POLICY_UPDATED',
                'CreditPolicy',
                (string) $newPolicy->id,
                ['limit_amount' => $oldLimit, 'active' => $oldActive],
                ['limit_amount' => $newLimit, 'active' => $newActive, 'due_days' => 7],
                $actor?->id ? (string) $actor->id : null
            );

            return $newPolicy;
        });

        return response()->json([
            'success' => true,
            'message' => 'Kebijakan limit kredit global berhasil diperbarui.',
            'data' => $result,
        ]);
    }

    /**
     * Aktifkan / blokir fasilitas kredit anggota oleh Manajer (PATCH /api/v1/members/{id}/credit-status)
     */
    public function updateMemberCreditStatus(Request $request, string $id)
    {
        if (!Str::isUuid($id)) {
            return response()->json(['success' => false, 'message' => 'ID anggota tidak valid.'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:ACTIVE,BLOCKED',
        ]);

        $member = Member::find($id);
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Anggota tidak ditemukan.'], 404);
        }

        $account = MemberCreditAccount::where('member_id', $member->id)->first();
        $oldStatus = $account?->status;
        $newStatus = strtoupper((string) $validated['status']);

        $account = MemberCreditAccount::updateOrCreate(
            ['member_id' => $member->id],
            [
                'status' => $newStatus,
                'activated_at' => $newStatus === 'ACTIVE'
                    ? ($account?->activated_at ?? CarbonImmutable::now('UTC'))
                    : $account?->activated_at,
            ]
        );

        $this->audit->record(
            'MEMBER_CREDIT_STATUS_UPDATED',
            'MemberCreditAccount',
            (string) $member->id,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            $request->user()?->id ? (string) $request->user()->id : null
        );

        return response()->json([
            'success' => true,
            'message' => "Status fasilitas kredit anggota diubah menjadi {$newStatus}.",
            'data' => $account,
        ]);
    }
}
