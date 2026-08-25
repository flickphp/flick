<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Validation\ValidationDelegateInterface;

beforeEach(function () {
    Flick::resetDefaultValidationDelegate();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    $_GET = [];
    $_POST['_id'] = 'myForm';
});

afterEach(function () {
    Flick::resetDefaultValidationDelegate();
    $_POST = [];
    $_GET = [];
});

// Bug #4 — a file input must not reflect a raw request value (bootstrap theme).
it('does not reflect a raw script payload into a bootstrap file input (#4)', function () {
    $_POST['avatar'] = '"><script>alert(1)</script>';

    $form = new Flick(['views' => 'bootstrap', 'csrf' => false, 'echo' => false]);
    $html = $form->file('avatar');

    expect($html)->toContain('type="file"');
    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('does not emit an unescaped value attribute on a file input', function () {
    $_POST['avatar'] = '"><script>alert(1)</script>';

    $form = new Flick(['views' => 'bootstrap', 'csrf' => false, 'echo' => false]);
    $html = $form->file('avatar');

    // The payload's closing quote must not break out of any attribute.
    expect($html)->toContain('type="file"');
    expect($html)->not->toContain('value=""><script');
});

// Bug #20 — a crafted attribute KEY must not inject additional attributes.
it('strips characters from an injecting attribute key (#20)', function () {
    $form = new Flick(['csrf' => false, 'echo' => false]);

    $html = $form->text('name', 'Name', '', ['onmouseover=alert(1) x' => 'y']);

    expect($html)->not->toContain('onmouseover=alert(1)');
    // The key survives only as an inert, sanitized attribute name.
    expect($html)->toContain('onmouseoveralert1x="y"');
});

// Bug #14 — the validation delegate must receive POST values with precedence
// over GET on a key collision (matching RequestInterface::all()).
it('passes POST over GET to the validation delegate (#14)', function () {
    $captured = null;

    $delegate = new class($captured) implements ValidationDelegateInterface
    {
        public function __construct(public mixed &$captured) {}

        public function canHandle(string $rule): bool
        {
            return $rule === 'delegated';
        }

        public function validate(string $field, mixed $value, string $rule, array $allData = []): array
        {
            $this->captured = $allData;

            return [];
        }
    };

    Flick::setDefaultValidationDelegate($delegate);

    $_POST['token'] = 'from-post';
    $_POST['other'] = 'post-value';
    $_GET['other'] = 'get-value';

    $form = new Flick(['csrf' => false, 'echo' => false]);
    $form->request('token', ['delegated']);

    expect($captured['other'])->toBe('post-value');
});
