<?php

namespace App\Modules\Inventory\Mobile;

use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class MobileWarehouseService
{
    /** @return array{product_id:int,code:string,name:string,barcode:?string,matched_by:string} */
    public function lookupProduct(int $companyId, string $scan): array
    {
        $scan = trim($scan);
        if ($companyId < 1 || $scan === '') {
            throw new DomainException('Mobile scanner product lookup requires company and scan identity.');
        }

        $barcode = Barcode::query()
            ->where('company_id', $companyId)
            ->where('barcode', $scan)
            ->first();
        if ($barcode instanceof Barcode) {
            $product = Product::query()
                ->where('company_id', $companyId)
                ->find($barcode->product_id);
            if (! $product instanceof Product) {
                throw new RuntimeException('Persisted barcode does not resolve to a product in the same company.');
            }

            return [
                'product_id' => (int) $product->getKey(),
                'code' => (string) $product->code,
                'name' => (string) $product->name,
                'barcode' => (string) $barcode->barcode,
                'matched_by' => 'barcode',
            ];
        }

        $product = Product::query()
            ->where('company_id', $companyId)
            ->where('code', $scan)
            ->first();
        if (! $product instanceof Product) {
            throw new DomainException('Scanned product was not found for company.');
        }
        $primaryBarcode = Barcode::query()
            ->where('company_id', $companyId)
            ->where('product_id', $product->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        return [
            'product_id' => (int) $product->getKey(),
            'code' => (string) $product->code,
            'name' => (string) $product->name,
            'barcode' => $primaryBarcode instanceof Barcode ? (string) $primaryBarcode->barcode : null,
            'matched_by' => 'code',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{id:int,replay:bool,status:string,result:?array<string,mixed>}
     */
    public function claimOperation(
        int $companyId,
        string $clientId,
        string $operationId,
        string $operationType,
        array $payload,
    ): array {
        $clientId = trim($clientId);
        $operationType = strtolower(trim($operationType));
        if ($companyId < 1 || $clientId === '' || ! Str::isUuid($operationId) || $operationType === '') {
            throw new DomainException('Mobile client operation identity is invalid.');
        }
        $requestHash = $this->payloadHash($payload);

        return DB::transaction(function () use ($companyId, $clientId, $operationId, $operationType, $requestHash): array {
            $inserted = DB::table('mobile_client_operations')->insertOrIgnore([
                'company_id' => $companyId,
                'client_id' => mb_substr($clientId, 0, 80),
                'operation_id' => $operationId,
                'operation_type' => mb_substr($operationType, 0, 80),
                'request_sha256' => $requestHash,
                'status' => 'claimed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $operation = DB::table('mobile_client_operations')
                ->where('company_id', $companyId)
                ->where('client_id', mb_substr($clientId, 0, 80))
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();
            if ($operation === null) {
                throw new RuntimeException('Mobile client operation could not be persisted.');
            }
            if ((string) $operation->operation_type !== mb_substr($operationType, 0, 80)
                || ! hash_equals((string) $operation->request_sha256, $requestHash)) {
                throw new DomainException('Mobile client operation replay payload drift detected.');
            }

            $result = $operation->result === null
                ? null
                : json_decode((string) $operation->result, true, flags: JSON_THROW_ON_ERROR);

            return [
                'id' => (int) $operation->id,
                'replay' => $inserted === 0,
                'status' => (string) $operation->status,
                'result' => is_array($result) ? $result : null,
            ];
        });
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    public function completeOperation(int $companyId, int $operationRecordId, array $result): array
    {
        $canonicalResult = $this->canonicalize($result);
        if (! is_array($canonicalResult)) {
            throw new RuntimeException('Canonical mobile operation result must be an array.');
        }
        $resultJson = json_encode($canonicalResult, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resultHash = hash('sha256', $resultJson);

        return DB::transaction(function () use ($companyId, $operationRecordId, $canonicalResult, $resultJson, $resultHash): array {
            $operation = DB::table('mobile_client_operations')
                ->where('company_id', $companyId)
                ->where('id', $operationRecordId)
                ->lockForUpdate()
                ->first();
            if ($operation === null) {
                throw new DomainException('Mobile client operation was not found for company.');
            }
            if ((string) $operation->status === 'completed') {
                if (! is_string($operation->result_sha256) || ! hash_equals($operation->result_sha256, $resultHash)) {
                    throw new DomainException('Mobile client operation completion result drift detected.');
                }
                $stored = json_decode((string) $operation->result, true, flags: JSON_THROW_ON_ERROR);

                return is_array($stored) ? $stored : [];
            }
            if ((string) $operation->status !== 'claimed') {
                throw new DomainException('Mobile client operation cannot complete from current status.');
            }

            DB::table('mobile_client_operations')->where('id', $operationRecordId)->update([
                'status' => 'completed',
                'result' => $resultJson,
                'result_sha256' => $resultHash,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return $canonicalResult;
        });
    }

    /** @param array<string,mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
