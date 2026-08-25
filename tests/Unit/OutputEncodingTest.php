<?php

use Flick\Flick;
use Flick\Session\ArraySession;

beforeEach(function () {
    Flick::resetDefaultValidationDelegate();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    $_GET = [];
    $_POST['_id'] = 'myForm';

    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
});

it('single-encodes a repopulated value containing special characters (session off)', function () {
    $_POST['name'] = 'O\'Brien & <Sons>';

    $element = $this->form->text('name');

    // rendered exactly once — not raw, not double-encoded
    expect($element)->toContain('value="'.htmlspecialchars('O\'Brien & <Sons>', ENT_QUOTES, 'UTF-8').'"')
        ->and($element)->not->toContain('&amp;amp;')
        ->and($element)->not->toContain('&amp;#039;');
});

it('does not render an unescaped angle bracket from a repopulated value', function () {
    $_POST['name'] = '<script>alert(1)</script>';

    $element = $this->form->text('name');

    expect($element)->not->toContain('<script>alert(1)</script>')
        ->and($element)->toContain('&lt;script&gt;');
});

it('single-encodes a repopulated value when session repopulation is on', function () {
    $form = new Flick([
        'csrf' => false,
        'echo' => false,
        'session' => new ArraySession,
    ]);

    $_POST['name'] = 'O\'Brien & <Sons>';

    $element = $form->text('name');

    expect($element)->toContain('value="'.htmlspecialchars('O\'Brien & <Sons>', ENT_QUOTES, 'UTF-8').'"')
        ->and($element)->not->toContain('&amp;amp;')
        ->and($element)->not->toContain('<script');
});

it('confirmed passes when both fields contain special characters', function () {
    $_POST['password'] = 'Secret1&<>"\'';
    $_POST['password_confirmation'] = 'Secret1&<>"\'';

    $this->form->request('password', ['confirmed'], ['confirmed' => 'ERROR']);

    expect($this->form->hasError('password'))->toBeFalse();
});

it('matches passes when both fields contain special characters', function () {
    $_POST['a'] = 'x&y<z>"\'';
    $_POST['b'] = 'x&y<z>"\'';

    $this->form->request('a', ['matches:b'], ['matches' => 'ERROR']);

    expect($this->form->hasError('a'))->toBeFalse();
});

it('validates length rules against raw input, not html-encoded input', function () {
    // '<script>' is 8 raw characters; html-encoded it is 22 chars
    $_POST['bio'] = '<script>';

    $this->form->request('bio', ['max:10'], ['max' => 'ERROR']);

    expect($this->form->hasError('bio'))->toBeFalse();
});

it('repopulates from the raw submitted value, not a value transformed by a string modifier', function () {
    // a hashing modifier attached via global rules must not run at render time and
    // echo the hash back into the field
    $form = new Flick([
        'csrf' => false,
        'echo' => false,
        'rules' => ['secret' => ['bcrypt']],
    ]);

    $_POST['secret'] = 'PlainText123';

    $html = $form->text('secret');

    expect($html)->toContain('value="PlainText123"')
        ->and($html)->not->toContain('$2y$');
});

it('returns raw submitted data from request() for storage', function () {
    $_POST['name'] = 'O\'Brien & Sons';

    $value = $this->form->request('name');

    expect($value)->toBe('O\'Brien & Sons');
});

it('escapes reflected user input in a field-level validation error (no XSS)', function () {
    // The date rules echo the raw submitted value into the error message, which is
    // then rendered inside the field's @error block. It must be HTML-escaped.
    $_POST['birthday'] = '<script>alert(document.cookie)</script>';

    $this->form->request('birthday', ['after:2000-01-01'], []);

    expect($this->form->hasError('birthday'))->toBeTrue();

    $element = $this->form->text('birthday');

    expect($element)->not->toContain('<script>alert(document.cookie)</script>')
        ->and($element)->toContain('&lt;script&gt;');
});

it('renders the errors() summary as a real list with escaped content (no literal tags, no XSS)', function () {
    // errors() echoes in the default echo mode, so capture the output.
    $form = new Flick([
        'csrf' => false,
        'showErrorsAlert' => true,
    ]);

    $_POST['birthday'] = '<script>alert(1)</script>';
    $form->request('birthday', ['after:2000-01-01'], []);

    ob_start();
    $form->errors();
    $alert = ob_get_clean();

    // the list markup renders (not shown as literal &lt;ul&gt;), but the value is escaped
    expect($alert)->toContain('<ul><li>')
        ->and($alert)->not->toContain('&lt;ul&gt;')
        ->and($alert)->not->toContain('<script>alert(1)</script>')
        ->and($alert)->toContain('&lt;script&gt;');
});

it('validates json containing quotes without a decode workaround', function () {
    $_POST['payload'] = '{"name":"O\'Brien","q":"a & b"}';

    $this->form->request('payload', ['json'], ['json' => 'ERROR']);

    expect($this->form->hasError('payload'))->toBeFalse();
});
