<?php

namespace App\Actions\Companies;

use App\DataTransfer\CopyConflict;
use App\DataTransfer\CopyConflictChoice;
use App\DataTransfer\CopyResult;
use App\Enums\CompanyCopyPermissionType;
use App\Models\CompanyCopyPermission;
use App\Models\Period\Brand;
use App\Models\Period\Contact;
use App\Models\Period\Product;
use App\Models\Period\ProductCategory;
use App\Models\Period\Unit;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CopyRecordsBetweenCompanies
{
    public function handle(
        int $sourceCompanyId,
        CompanyCopyPermissionType $type,
        array $ids,
        array $conflictChoices = [],
    ): CopyResult {
        abort_unless(CompanyCopyPermission::allows($sourceCompanyId, (int) PeriodContext::companyId(), $type), 403, 'Bu şirketten veri aktarma izniniz yok.');

        SourcePeriodContext::use($sourceCompanyId);

        try {
            $sourceRows = $type === CompanyCopyPermissionType::Contact
                ? Contact::on('period_source')->whereIn('id', $ids)->get()
                : Product::on('period_source')->whereIn('id', $ids)->get();

            $copied = [];
            $existing = [];
            $conflicts = [];
            $warnings = [];
            $cancelled = [];

            DB::connection('period')->transaction(function () use (
                $sourceRows,
                $sourceCompanyId,
                $type,
                $conflictChoices,
                &$copied,
                &$existing,
                &$conflicts,
                &$warnings,
                &$cancelled,
            ): void {
                foreach ($sourceRows as $source) {
                    $targetClass = $type === CompanyCopyPermissionType::Contact ? Contact::class : Product::class;
                    $target = $targetClass::query()->where('code', $source->code)->first();
                    $choice = $conflictChoices[$source->id] ?? null;

                    if ($target && $choice === null) {
                        $conflicts[] = new CopyConflict(
                            sourceId: (int) $source->id,
                            sourceCode: (string) $source->code,
                            sourceLabel: (string) ($source->title ?? $source->name),
                            existingTargetId: (int) $target->id,
                            existingTargetLabel: (string) ($target->title ?? $target->name),
                        );

                        continue;
                    }

                    $newCode = (string) $source->code;

                    if ($target) {
                        $action = CopyConflictChoice::from((string) ($choice['action'] ?? $choice));

                        if ($action === CopyConflictChoice::UseExisting) {
                            $existing[] = [
                                'source_id' => (int) $source->id,
                                'target_id' => (int) $target->id,
                            ];

                            AuditContext::period(
                                'Şirketler arası kopyalamada mevcut kart kullanıldı.',
                                [
                                    'source_company_id' => $sourceCompanyId,
                                    'source_record_id' => $source->id,
                                    'target_record_id' => $target->id,
                                    'type' => $type->value,
                                ],
                                $target,
                                'cross_company_copy_existing',
                            );

                            continue;
                        }

                        if ($action === CopyConflictChoice::Cancel) {
                            $cancelled[] = (int) $source->id;
                            continue;
                        }

                        $newCode = strtoupper(trim((string) ($choice['new_code'] ?? '')));

                        if ($newCode === '' || $targetClass::query()->where('code', $newCode)->exists()) {
                            throw ValidationException::withMessages([
                                "conflicts.{$source->id}.new_code" => 'Yeni kod boş olamaz ve hedefte benzersiz olmalıdır.',
                            ]);
                        }
                    }

                    $created = $type === CompanyCopyPermissionType::Contact
                        ? $this->copyContact($source, $sourceCompanyId, $newCode)
                        : $this->copyProduct($source, $sourceCompanyId, $newCode, $warnings);

                    $copied[] = [
                        'source_id' => (int) $source->id,
                        'target_id' => (int) $created->id,
                        'code' => $created->code,
                    ];

                    AuditContext::period(
                        'Şirketler arası kart kopyalandı.',
                        [
                            'source_company_id' => $sourceCompanyId,
                            'source_record_id' => $source->id,
                            'target_record_id' => $created->id,
                            'type' => $type->value,
                        ],
                        $created,
                        'cross_company_copy',
                    );
                }
            });

            return new CopyResult($copied, $existing, $conflicts, $warnings, $cancelled);
        } finally {
            SourcePeriodContext::clear();
        }
    }

    private function copyContact(Contact $source, int $sourceCompanyId, string $code): Contact
    {
        $target = new Contact();

        $target->forceFill([
            'code' => $code,
            'title' => $source->title,
            'type' => $source->type->value,
            'tax_office' => $source->tax_office,
            'tax_number' => $source->tax_number,
            'national_id' => $source->national_id,
            'address' => $source->address,
            'city' => $source->city,
            'district' => $source->district,
            'phone' => $source->phone,
            'email' => $source->email,
            'term_days' => $source->term_days,
            'risk_limit' => $source->risk_limit,
            'discount_rate' => $source->discount_rate,
            'price_list_id' => null,
            'source_company_id' => $sourceCompanyId,
            'source_record_id' => $source->id,
            'is_active' => $source->is_active,
        ]);

        $target->save();

        return $target;
    }

    private function copyProduct(
        Product $source,
        int $sourceCompanyId,
        string $code,
        array &$warnings,
    ): Product {
        $sourceUnitCode = DB::connection('period_source')
            ->table('units')
            ->where('id', $source->unit_id)
            ->value('code');

        $targetUnitId = Unit::query()->where('code', $sourceUnitCode)->value('id');

        if (! $targetUnitId) {
            throw ValidationException::withMessages([
                'unit' => "Kaynak ürünün {$sourceUnitCode} birimi hedef period'da yok.",
            ]);
        }

        $brandId = null;

        if ($source->brand_id) {
            $brandName = DB::connection('period_source')->table('brands')->where('id', $source->brand_id)->value('name');
            $brandId = $brandName ? Brand::query()->where('name', $brandName)->value('id') : null;

            if ($brandName && ! $brandId) {
                $warnings[] = "{$source->code}: '{$brandName}' markası hedefte bulunamadı; marka bağı boş bırakıldı.";
            }
        }

        $categoryId = $this->mapCategory($source->category_id, $warnings, (string) $source->code);

        if (in_array($source->kind->value, ['set', 'configurable'], true)) {
            $warnings[] = "{$source->code}: yalnız ürün kartı kopyalandı; set/konfigürasyon alt tanımları G-111 kapsamı gereği taşınmadı.";
        }

        return Product::query()->create([
            'code' => $code,
            'name' => $source->name,
            'description' => $source->description,
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'unit_id' => $targetUnitId,
            'barcode' => $source->barcode,
            'vat_rate' => $source->vat_rate,
            'list_price' => $source->list_price,
            'currency' => $source->currency,
            'kind' => $source->kind->value,
            'variant_group_id' => null,
            'allow_negative_stock' => $source->allow_negative_stock,
            'min_stock' => $source->min_stock,
            'channel_stock_mode' => $source->channel_stock_mode->value,
            'source_company_id' => $sourceCompanyId,
            'source_record_id' => $source->id,
            'is_active' => $source->is_active,
        ]);
    }

    private function mapCategory(?int $sourceCategoryId, array &$warnings, string $productCode): ?int
    {
        if (! $sourceCategoryId) {
            return null;
        }

        $path = [];
        $cursor = $sourceCategoryId;

        while ($cursor) {
            $row = DB::connection('period_source')
                ->table('product_categories')
                ->where('id', $cursor)
                ->first();

            if (! $row) {
                break;
            }

            array_unshift($path, (string) $row->name);
            $cursor = $row->parent_id;
        }

        $targetParent = null;

        foreach ($path as $name) {
            $target = ProductCategory::query()
                ->where('parent_id', $targetParent)
                ->where('name', $name)
                ->first();

            if (! $target) {
                $warnings[] = "{$productCode}: kategori yolu '".implode(' / ', $path)."' hedefte bulunamadı; kategori bağı boş bırakıldı.";

                return null;
            }

            $targetParent = $target->id;
        }

        return $targetParent;
    }
}
