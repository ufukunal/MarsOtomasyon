<?php

use Illuminate\Support\Facades\URL;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2SIGNED');
    $actor = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($actor, $company, $period);
});

it('V2-235 denies an unsigned report download URL before looking up an export', function () {
    $url = route('reports.exports.download', ['export' => 987654]);

    $this->get($url)->assertForbidden();
});

it('V2-235 rejects an expired signed export download URL', function () {
    $url = URL::temporarySignedRoute(
        'reports.exports.download',
        now()->subMinute(),
        ['export' => 987654],
    );

    $this->get($url)->assertForbidden();
});

it('V2-235 accepts a valid signature and then validates that the export exists', function () {
    $url = URL::temporarySignedRoute(
        'reports.exports.download',
        now()->addMinutes(15),
        ['export' => 987654],
    );

    $this->get($url)->assertNotFound();
});