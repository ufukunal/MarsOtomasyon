<?php

arch('uses the PHP production-safety preset')
    ->preset()
    ->php();

arch('uses the security preset')
    ->preset()
    ->security();

arch('follows Laravel architecture conventions')
    ->preset()
    ->laravel()
    ->ignoring([
        'App\Foundation\Health\ReadinessController',
        'App\Modules\Core\CoreServiceProvider',
    ]);

arch('keeps Foundation independent from application modules')
    ->expect('App\Foundation')
    ->not->toUse('App\Modules');

arch('forbids debugging and dynamic-evaluation helpers in application code')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'eval']);
