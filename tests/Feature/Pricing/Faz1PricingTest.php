<?php

use App\Actions\Pricing\BulkAdjustPriceList;
use App\Actions\Pricing\SavePriceListItem;
use Illuminate\Validation\ValidationException;

it('aynı ürünün çakışan fiyat tarih aralığını reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('PRICE');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct();
    $list = $this->createTestPriceList();
    $action = app(SavePriceListItem::class);

    $action->handle($list, $product, '100', '2026-01-01', '2026-01-31');

    expect(fn () => $action->handle($list, $product, '110', '2026-01-15', '2026-02-15'))
        ->toThrow(ValidationException::class);
});

it('toplu yüzde fiyat değişimini BCMath ile uygular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('BULKPRICE');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $list = $this->createTestPriceList();
    $a = $this->createTestProduct(['code' => 'PA']);
    $b = $this->createTestProduct(['code' => 'PB']);
    $save = app(SavePriceListItem::class);

    $itemA = $save->handle($list, $a, '100');
    $itemB = $save->handle($list, $b, '250');

    expect(app(BulkAdjustPriceList::class)->handle($list, '10'))->toBe(2)
        ->and($itemA->refresh()->price)->toBe('110.0000')
        ->and($itemB->refresh()->price)->toBe('275.0000');
});
