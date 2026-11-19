<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Layanan idempotency atomik untuk mutasi transaksi finansial & inventori.
 * Mengikat kunci ke (user_id, operation, target_resource_id, request_hash)
 * serta memulihkan tabrakan konkurensi PostgreSQL (SQLSTATE 23505) secara aman.
 */
class IdempotencyService
{
    public function normalizeKey(mixed $rawKey): ?string
    {
        if ($rawKey === null) {
            return null;
        }

        if (!is_string($rawKey) && !is_numeric($rawKey)) {
            throw new BusinessRuleException('Idempotency key harus berupa teks yang valid.', 422, 'INVALID_IDEMPOTENCY_KEY');
        }

        $key = trim((string) $rawKey);
        if ($key === '') {
            throw new BusinessRuleException('Idempotency key tidak boleh kosong bila disertakan.', 422, 'INVALID_IDEMPOTENCY_KEY');
        }

        if (strlen($key) > 100 || !preg_match('/^[\x21-\x7E]+$/', $key)) {
            throw new BusinessRuleException(
                'Format idempotency key tidak valid (maksimal 100 karakter ASCII tanpa spasi).',
                422,
                'INVALID_IDEMPOTENCY_KEY'
            );
        }

        return $key;
    }

    public function fingerprint(string $operation, ?string $targetResourceId, array $canonicalPayload): string
    {
        $normalized = [
            'operation' => strtoupper($operation),
            'target_resource_id' => $targetResourceId ? strtolower(trim($targetResourceId)) : null,
            'payload' => $this->recursiveSort($canonicalPayload),
        ];

        return hash('sha256', (string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Jalankan callback dalam satu DB transaction yang terikat idempotency record.
     *
     * @param  Closure(): array{status: int, body: array<string, mixed>, target_resource_id?: ?string}  $work
     * @return array{status: int, body: array<string, mixed>, replayed: bool}
     */
    public function run(
        ?string $key,
        string $operation,
        ?string $userId,
        ?string $targetResourceId,
        array $canonicalPayload,
        Closure $work
    ): array {
        $normalizedOperation = strtoupper($operation);

        if ($key === null) {
            try {
                $result = DB::transaction(fn () => $work(), 3);
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e) && str_contains($this->extractConstraintName($e) ?? '', 'external_reference')) {
                    throw new BusinessRuleException('Referensi transfer sudah pernah tercatat (Duplikasi).', 422, 'DUPLICATE_PAYMENT');
                }
                throw $e;
            }

            return [
                'status' => (int) $result['status'],
                'body' => $result['body'],
                'replayed' => false,
            ];
        }

        $requestHash = $this->fingerprint($normalizedOperation, $targetResourceId, $canonicalPayload);

        // Pre-check cepat sebelum membuka transaksi baru
        $existing = IdempotencyKey::where('idempotency_key', $key)->first();
        if ($existing) {
            return $this->validateAndReplay($existing, $normalizedOperation, $userId, $targetResourceId, $requestHash);
        }

        try {
            $result = DB::transaction(function () use ($key, $normalizedOperation, $userId, $targetResourceId, $requestHash, $work) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['koperadata.idempotency:' . $key]);
                }
                // Re-check dengan lock jika baris sudah ter-commit tepat sebelum transaksi ini
                $existingInTx = IdempotencyKey::where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existingInTx) {
                    return [
                        'replayed_record' => $existingInTx,
                    ];
                }

                $computed = $work();
                $resolvedTarget = $targetResourceId ?? ($computed['target_resource_id'] ?? null);

                IdempotencyKey::create([
                    'idempotency_key' => $key,
                    'operation' => $normalizedOperation,
                    'user_id' => $userId,
                    'target_resource_id' => $resolvedTarget,
                    'request_hash' => $requestHash,
                    'response_status' => (int) $computed['status'],
                    'response_body' => $computed['body'],
                ]);

                return [
                    'status' => (int) $computed['status'],
                    'body' => $computed['body'],
                    'replayed' => false,
                ];
            }, 3);

            if (isset($result['replayed_record'])) {
                return $this->validateAndReplay(
                    $result['replayed_record'],
                    $normalizedOperation,
                    $userId,
                    $targetResourceId,
                    $requestHash
                );
            }

            return $result;
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if (!$this->isUniqueViolation($e)) {
                throw $e;
            }

            // Setelah DB::transaction() rollback karena SQLSTATE 23505, cek apakah penyebabnya idempotency_key atau external_reference
            $committed = IdempotencyKey::where('idempotency_key', $key)->first();
            if ($committed) {
                return $this->validateAndReplay($committed, $normalizedOperation, $userId, $targetResourceId, $requestHash);
            }

            $constraintName = $this->extractConstraintName($e);
            if ($constraintName !== null && str_contains($constraintName, 'external_reference')) {
                throw new BusinessRuleException('Referensi transfer sudah pernah tercatat (Duplikasi).', 422, 'DUPLICATE_PAYMENT');
            }

            if ($constraintName !== null && (str_contains($constraintName, 'idempotency_key') || str_contains($constraintName, 'pickup_idempotency_key'))) {
                throw new BusinessRuleException('Idempotency key sedang digunakan oleh transaksi lain.', 409, 'IDEMPOTENCY_CONFLICT');
            }

            throw $e;
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>, replayed: bool}
     */
    private function validateAndReplay(
        IdempotencyKey $record,
        string $operation,
        ?string $userId,
        ?string $targetResourceId,
        string $requestHash
    ): array {
        if ($record->user_id !== null && $userId !== null && $record->user_id !== $userId) {
            throw new BusinessRuleException('Idempotency key sudah digunakan oleh akun lain.', 403, 'FORBIDDEN');
        }

        if ($record->user_id !== null && $userId === null) {
            throw new BusinessRuleException('Idempotency key terikat pada akun pengguna lain.', 403, 'FORBIDDEN');
        }

        if (strtoupper((string) $record->operation) !== strtoupper($operation)) {
            throw new BusinessRuleException('Idempotency key sudah digunakan untuk operasi lain.', 409, 'IDEMPOTENCY_CONFLICT');
        }

        if ($targetResourceId !== null && $record->target_resource_id !== null && strtolower($record->target_resource_id) !== strtolower($targetResourceId)) {
            throw new BusinessRuleException('Idempotency key sudah digunakan untuk target resource lain.', 409, 'IDEMPOTENCY_CONFLICT');
        }

        if (!hash_equals((string) $record->request_hash, $requestHash)) {
            throw new BusinessRuleException('Idempotency key tidak boleh dipakai ulang dengan payload berbeda.', 409, 'IDEMPOTENCY_PAYLOAD_MISMATCH');
        }

        $body = is_array($record->response_body) ? $record->response_body : [];
        $body['idempotent'] = true;

        return [
            'status' => (int) $record->response_status,
            'body' => $body,
            'replayed' => true,
        ];
    }

    private function recursiveSort(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->recursiveSort($item), $value);
        }

        ksort($value);
        foreach ($value as $k => $v) {
            $value[$k] = $this->recursiveSort($v);
        }

        return $value;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23505'
            || (isset($e->errorInfo[0]) && (string) $e->errorInfo[0] === '23505');
    }

    private function extractConstraintName(QueryException $e): ?string
    {
        if (preg_match('/constraint "([^"]+)"/i', $e->getMessage(), $m)) {
            return strtolower($m[1]);
        }

        return null;
    }
}
