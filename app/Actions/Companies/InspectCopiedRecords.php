<?php

namespace App\Actions\Companies;

use App\Actions\Contacts\SaveContact;
use App\Actions\Products\SaveProduct;
use App\Enums\CompanyCopyPermissionType;
use App\Enums\ProductKind;
use App\Models\CompanyCopyPermission;
use App\Models\Period\Brand;
use App\Models\Period\Contact;
use App\Models\Period\Product;
use App\Models\Period\ProductCategory;
use App\Models\Period\Unit;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InspectCopiedRecords
{
    /** @return list<array<string, mixed>> */
    public function inspect(int $sourceCompanyId, CompanyCopyPermissionType $type): array
    {
        MutationAuthorizer::authorize('company_copy_permissions.view');
        $this->authorizeSource($sourceCompanyId, $type);

        $targetClass = $type === CompanyCopyPermissionType::Contact ? Contact::class : Product::class;

        $targets = $targetClass::query()
            ->where('source_company_id', $sourceCompanyId)
            ->whereNotNull('source_record_id')
            ->orderBy('code')
            ->get();

        SourcePeriodContext::use($sourceCompanyId);

        try {
            return $targets->map(function (Model $target) use ($type): array {
                $source = $type === CompanyCopyPermissionType::Contact
                    ? Contact::on('period_source')->find($target->source_record_id)
                    : Product::on('period_source')->find($target->source_record_id);

                if (! $source) {
                    return [
                        'target_id' => (int) $target->getKey(),
                        'code' => (string) $target->getAttribute('code'),
                        'label' => (string) ($target->getAttribute('title') ?? $target->getAttribute('name')),
                        'source_missing' => true,
                        'changes' => [],
                    ];
                }

                $sourceSnapshot = $this->snapshot($source, $type, true);
                $targetSnapshot = $this->snapshot($target, $type, false);
                $changes = [];

                foreach ($sourceSnapshot as $field => $sourceValue) {
                    $targetValue = $targetSnapshot[$field] ?? null;

                    if ($this->scalar($sourceValue) !== $this->scalar($targetValue)) {
                        $changes[] = [
                            'field' => $field,
                            'source' => $this->scalar($sourceValue),
                            'target' => $this->scalar($targetValue),
                        ];
                    }
                }

                return [
                    'target_id' => (int) $target->getKey(),
                    'source_id' => (int) $source->getKey(),
                    'code' => (string) $target->getAttribute('code'),
                    'label' => (string) ($target->getAttribute('title') ?? $target->getAttribute('name')),
                    'source_missing' => false,
                    'changes' => $changes,
                ];
            })->filter(
                fn (array $row): bool => $row['source_missing'] || $row['changes'] !== []
            )->values()->all();
        } finally {
            SourcePeriodContext::clear();
        }
    }

    public function refresh(
        int $sourceCompanyId,
        CompanyCopyPermissionType $type,
        int $targetId,
    ): Model {
        MutationAuthorizer::authorize('company_copy_permissions.view');
        PeriodContext::ensureWritable();
        $this->authorizeSource($sourceCompanyId, $type);

        $targetClass = $type === CompanyCopyPermissionType::Contact ? Contact::class : Product::class;
        $target = $targetClass::query()
            ->where('source_company_id', $sourceCompanyId)
            ->whereNotNull('source_record_id')
            ->findOrFail($targetId);

        SourcePeriodContext::use($sourceCompanyId);

        try {
            $source = $type === CompanyCopyPermissionType::Contact
                ? Contact::on('period_source')->findOrFail($target->source_record_id)
                : Product::on('period_source')->findOrFail($target->source_record_id);

            if ($type === CompanyCopyPermissionType::Product
                && $source->kind !== ProductKind::Normal) {
                throw ValidationException::withMessages([
                    'product' => 'Kaynak ürün set/konfigüre hale gelmiş. Alt tanımlar taşınmadan hedef kart yenilenemez.',
                ]);
            }

            $updated = $type === CompanyCopyPermissionType::Contact
                ? $this->refreshContact($source, $target)
                : $this->refreshProduct($source, $target);

            AuditContext::period(
                'Şirketler arası kopyalanmış kart kaynak verisiyle kullanıcı onayıyla güncellendi.',
                [
                    'source_company_id' => $sourceCompanyId,
                    'source_record_id' => $source->getKey(),
                    'target_record_id' => $updated->getKey(),
                    'type' => $type->value,
                ],
                $updated,
                'cross_company_copy_refreshed',
            );

            return $updated;
        } finally {
            SourcePeriodContext::clear();
        }
    }

    private function refreshContact(Contact $source, Contact $target): Contact
    {
        return app(SaveContact::class)->handle([
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
            'risk_limit' => (string) $source->risk_limit,
            'discount_rate' => (string) $source->discount_rate,
            'price_list_id' => $target->price_list_id,
            'category_ids' => $target->categories()->pluck('contact_categories.id')->all(),
            'is_active' => (bool) $source->is_active,
        ], $target, (int) $target->version);
    }

    private function refreshProduct(Product $source, Product $target): Product
    {
        $sourceUnitCode = DB::connection('period_source')
            ->table('units')
            ->where('id', $source->unit_id)
            ->value('code');

        $unitId = Unit::query()->where('code', $sourceUnitCode)->value('id');

        if (! $unitId) {
            throw ValidationException::withMessages([
                'unit' => "Kaynak ürünün {$sourceUnitCode} birimi hedef dönemde bulunamadı.",
            ]);
        }

        $brandId = null;

        if ($source->brand_id) {
            $brandName = DB::connection('period_source')
                ->table('brands')
                ->where('id', $source->brand_id)
                ->value('name');

            $brandId = $brandName ? Brand::query()->where('name', $brandName)->value('id') : null;
        }

        $categoryId = $this->mapSourceCategory($source->category_id);

        return app(SaveProduct::class)->handle([
            'code' => $target->code,
            'name' => $source->name,
            'description' => $source->description,
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'unit_id' => $unitId,
            'barcode' => $source->barcode,
            'vat_rate' => (string) $source->vat_rate,
            'list_price' => (string) $source->list_price,
            'price_vat_included' => false,
            'currency' => $source->currency,
            'kind' => ProductKind::Normal->value,
            'variant_group_id' => null,
            'allow_negative_stock' => (bool) $source->allow_negative_stock,
            'min_stock' => (string) $source->min_stock,
            'channel_stock_mode' => $source->channel_stock_mode->value,
            'is_active' => (bool) $source->is_active,
        ], $target, (int) $target->version);
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $model, CompanyCopyPermissionType $type, bool $source): array
    {
        if ($type === CompanyCopyPermissionType::Contact) {
            return [
                'unvan' => $model->getAttribute('title'),
                'tip' => $this->scalar($model->getAttribute('type')),
                'vergi_dairesi' => $model->getAttribute('tax_office'),
                'vergi_no' => $model->getAttribute('tax_number'),
                'tc_kimlik' => $model->getAttribute('national_id'),
                'adres' => $model->getAttribute('address'),
                'il' => $model->getAttribute('city'),
                'ilce' => $model->getAttribute('district'),
                'telefon' => $model->getAttribute('phone'),
                'eposta' => $model->getAttribute('email'),
                'vade_gunu' => $model->getAttribute('term_days'),
                'risk_limiti' => $model->getAttribute('risk_limit'),
                'iskonto_orani' => $model->getAttribute('discount_rate'),
                'durum' => (bool) $model->getAttribute('is_active'),
            ];
        }

        $connection = $source ? 'period_source' : 'period';
        $unitCode = DB::connection($connection)
            ->table('units')
            ->where('id', $model->getAttribute('unit_id'))
            ->value('code');

        $brandName = $model->getAttribute('brand_id')
            ? DB::connection($connection)->table('brands')->where('id', $model->getAttribute('brand_id'))->value('name')
            : null;

        return [
            'ad' => $model->getAttribute('name'),
            'aciklama' => $model->getAttribute('description'),
            'birim' => $unitCode,
            'marka' => $brandName,
            'kategori' => $this->categoryPath($connection, $model->getAttribute('category_id')),
            'barkod' => $model->getAttribute('barcode'),
            'kdv' => $model->getAttribute('vat_rate'),
            'liste_fiyati' => $model->getAttribute('list_price'),
            'para_birimi' => $model->getAttribute('currency'),
            'tip' => $this->scalar($model->getAttribute('kind')),
            'negatif_stok' => (bool) $model->getAttribute('allow_negative_stock'),
            'minimum_stok' => $model->getAttribute('min_stock'),
            'kanal_stok_modu' => $this->scalar($model->getAttribute('channel_stock_mode')),
            'durum' => (bool) $model->getAttribute('is_active'),
        ];
    }

    private function mapSourceCategory(?int $sourceCategoryId): ?int
    {
        $path = $this->categoryPath('period_source', $sourceCategoryId);

        if ($path === '') {
            return null;
        }

        $parentId = null;

        foreach (explode(' / ', $path) as $name) {
            $category = ProductCategory::query()
                ->where('parent_id', $parentId)
                ->where('name', $name)
                ->first();

            if (! $category) {
                throw ValidationException::withMessages([
                    'category' => "Kaynak kategori yolu hedef dönemde bulunamadı: {$path}",
                ]);
            }

            $parentId = $category->id;
        }

        return $parentId;
    }

    private function categoryPath(string $connection, ?int $categoryId): string
    {
        if (! $categoryId) {
            return '';
        }

        $names = [];
        $cursor = $categoryId;
        $visited = [];

        while ($cursor) {
            if (isset($visited[$cursor])) {
                break;
            }

            $visited[$cursor] = true;
            $row = DB::connection($connection)
                ->table('product_categories')
                ->where('id', $cursor)
                ->first();

            if (! $row) {
                break;
            }

            array_unshift($names, (string) $row->name);
            $cursor = $row->parent_id;
        }

        return implode(' / ', $names);
    }

    private function authorizeSource(int $sourceCompanyId, CompanyCopyPermissionType $type): void
    {
        abort_unless(
            CompanyCopyPermission::allows(
                $sourceCompanyId,
                (int) PeriodContext::companyId(),
                $type,
            ),
            403,
            'Bu şirketten veri aktarma izniniz yok.',
        );
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
