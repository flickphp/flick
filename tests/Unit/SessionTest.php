<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $this->form = new Flick([
        'csrf' => 1,
    ]);
});

it('adds a csrf token to a form', function () {
    ob_start();
    $this->form->open();
    $element = ob_get_contents();
    ob_end_clean();

    expect($element)
        ->toBeString()
        ->toContain('_token');
});

it('generates a unique csrf token for each form', function () {
    $token1 = $this->form->build->generateCsrfToken();
    $token2 = $this->form->build->generateCsrfToken();

    expect($token1)
        ->not()
        ->toEqual($token2);
});

it('validates a csrf token', function () {
    ob_start();
    $this->form->open();
    ob_get_contents();
    ob_end_clean();

    sleep(1);

    // use reflection to access our protected method
    $method = new ReflectionMethod(get_class($this->form), 'checkForAndValidateCsrfToken');
    $result = $method->invoke($this->form);

    expect($result)->toBeFalse();
});
