<?php

namespace App\Modules\Products\Variants;

use App\Modules\Products\Models\ProductFamily;
use App\Modules\Products\Models\ProductFamilyChannelMapping;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ProductFamilyChannelService
{
    /** @param array<string,mixed> $metadata */
    public function mapParent(
        int $companyId,
        int $connectionId,
        int $familyId,
        string $provider,
        string $externalParentId,
        string $status = 'active',
        array $metadata = [],
    ): ProductFamilyChannelMapping {
        $provider = strtolower(trim($provider));
        $externalParentId = trim($externalParentId);
        $status = strtolower(trim($status));
        if ($provider === '' || $externalParentId === '' || ! in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Product family channel mapping identity is invalid.');
        }
        $provider = mb_substr($provider, 0, 64);
        $externalParentId = mb_substr($externalParentId, 0, 192);

        try {
            return DB::transaction(function () use ($companyId, $connectionId, $familyId, $provider, $externalParentId, $status, $metadata): ProductFamilyChannelMapping {
                $connection = DB::table('integration_connections')
                    ->where('company_id', $companyId)
                    ->where('id', $connectionId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first(['id', 'provider']);
                if ($connection === null) {
                    throw new DomainException('Active integration connection was not found for company.');
                }
                if (strtolower((string) $connection->provider) !== $provider) {
                    throw new DomainException('Product family channel provider does not match the integration connection.');
                }

                $family = ProductFamily::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->find($familyId);
                if (! $family instanceof ProductFamily) {
                    throw new DomainException('Product family was not found for company.');
                }

                $existing = ProductFamilyChannelMapping::query()
                    ->where('company_id', $companyId)
                    ->where('connection_id', $connectionId)
                    ->where('product_family_id', $familyId)
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof ProductFamilyChannelMapping) {
                    return $this->assertExact($existing, $provider, $externalParentId, $status, $metadata);
                }

                $owner = ProductFamilyChannelMapping::query()
                    ->where('company_id', $companyId)
                    ->where('connection_id', $connectionId)
                    ->where('provider', $provider)
                    ->where('external_parent_id', $externalParentId)
                    ->lockForUpdate()
                    ->first();
                if ($owner instanceof ProductFamilyChannelMapping) {
                    throw new DomainException('External marketplace parent identity is already mapped to another product family.');
                }

                return ProductFamilyChannelMapping::query()->create([
                    'company_id' => $companyId,
                    'connection_id' => $connectionId,
                    'product_family_id' => $familyId,
                    'provider' => $provider,
                    'external_parent_id' => $externalParentId,
                    'status' => $status,
                    'metadata' => $metadata === [] ? null : $metadata,
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }
            $existing = ProductFamilyChannelMapping::query()
                ->where('company_id', $companyId)
                ->where('connection_id', $connectionId)
                ->where('product_family_id', $familyId)
                ->first();
            if ($existing instanceof ProductFamilyChannelMapping) {
                return $this->assertExact($existing, $provider, $externalParentId, $status, $metadata);
            }

            throw new DomainException('Product family channel mapping collided with a concurrent write.', 0, $exception);
        }
    }

    /** @param array<string,mixed> $metadata */
    private function assertExact(ProductFamilyChannelMapping $mapping, string $provider, string $externalParentId, string $status, array $metadata): ProductFamilyChannelMapping
    {
        $persistedMetadata = $mapping->metadata ?? [];
        if (
            (string) $mapping->provider !== $provider
            || (string) $mapping->external_parent_id !== $externalParentId
            || (string) $mapping->status !== $status
            || $persistedMetadata !== $metadata
        ) {
            throw new DomainException('Product family channel mapping drift detected.');
        }

        return $mapping;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505' || (string) ($exception->errorInfo[0] ?? '') === '23505';
    }
}
