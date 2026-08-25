<?php

use Flick\Flick;
use Flick\Http\ArrayRequest;

it('does not enter the XHR branch when ajax is explicitly false (M11)', function () {
    $jsonCalled = false;

    new Flick([
        'csrf' => false,
        'ajax' => false,
        'request' => ArrayRequest::createAjax(['_id' => 'myForm', 'email' => 'x@example.com']),
        'onJson' => function () use (&$jsonCalled) {
            $jsonCalled = true;

            return null;
        },
    ]);

    expect($jsonCalled)->toBeFalse();
});

it('enters the XHR branch when ajax is true (M11 regression)', function () {
    $jsonCalled = false;

    new Flick([
        'csrf' => false,
        'ajax' => true,
        'request' => ArrayRequest::createAjax(['_id' => 'myForm', 'email' => 'x@example.com']),
        'onJson' => function () use (&$jsonCalled) {
            $jsonCalled = true;

            return null;
        },
    ]);

    expect($jsonCalled)->toBeTrue();
});
