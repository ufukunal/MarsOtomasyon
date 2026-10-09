<?php

use App\Actions\Contacts\SaveContact;
use App\Exceptions\PeriodReadOnlyException;
use App\Models\Period\Contact;
use App\Models\Period\Product;
use App\Support\Cache\CacheKey;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;

it('v2 isolated company periods never leak catalog records or scoped cache keys', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('V2ISOA');
    $product = $this->createTestProduct(['code' => 'V2-ISOLATED-A']);

    $keyA = CacheKey::period('products');
    expect(Product::query()->where('code', 'V2-ISOLATED-A')->exists())->toBeTrue();

    [$companyB, $periodB] = $this->createCompanyWithPeriod('V2ISOB');
    $keyB = CacheKey::period('products');

    expect($keyA)->not->toBe($keyB)
        ->and(Product::query()->where('code', 'V2-ISOLATED-A')->exists())->toBeFalse();

    PeriodContext::useSystem($companyA->id, $periodA->id);
    expect(Product::query()->where('code', 'V2-ISOLATED-A')->exists())->toBeTrue();

    PeriodContext::useSystem($companyB->id, $periodB->id);
    expect(Product::query()->where('code', 'V2-ISOLATED-A')->exists())->toBeFalse();

    // Source object creation must never materialize into the other physical DB.
    expect($product->code)->toBe('V2-ISOLATED-A');
});

it('v2 periods deny human context switches without an authorized session', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2ISOGUEST');
    PeriodContext::clear();

    expect(fn () => PeriodContext::use($company->id, $period->id))
        ->toThrow(AuthorizationException::class);
});

it('v2 closed periods reject contact mutations before writing any rows', function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2ISOCLOSED');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
    $period->update(['status' => 'closed']);

    expect(fn () => app(SaveContact::class)->handle([
        'title' => 'Rejected in Closed Period',
        'type' => 'legal',
        'risk_limit' => '0',
        'discount_rate' => '0',
        'is_active' => true,
        'category_ids' => [],
    ]))->toThrow(PeriodReadOnlyException::class);

    expect(Contact::query()->where('title', 'Rejected in Closed Period')->count())->toBe(0);
});
