<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

/*
|--------------------------------------------------------------------------
| Core sweep regression tests
|--------------------------------------------------------------------------
|
| Each test here pins one bug found while hardening the core package for
| release (2026-08-11). Section numbers key the tests to the maintainers'
| review notes.
|
*/

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    $_GET = [];
    $_SESSION = [];
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
    $_SESSION = [];
});

/**
 * Build a Flick instance around an in-memory request/session pair.
 */
function sweepForm(ArrayRequest $request, array $config = []): Flick
{
    return new Flick(array_merge([
        'request' => $request,
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
    ], $config));
}

// §1.1 — a tracking parameter is not a form submission ------------------------

it('does not treat a bare tracking parameter as a submission (§1.1)', function () {
    $form = sweepForm(ArrayRequest::createGet(['utm_source' => 'newsletter']));

    expect($form->submitted())->toBeFalse();
});

it('raises no errors on a GET request carrying only tracking parameters (§1.1)', function () {
    $form = sweepForm(ArrayRequest::createGet(['fbclid' => 'abc123', 'ref' => 'twitter']));

    $form->createAndValidate('Name[required], Email[required, email]');

    expect($form->getErrors())->toBe([]);
});

it('still treats a GET form carrying its own _id as submitted (§1.1)', function () {
    $form = sweepForm(ArrayRequest::createGet(['_id' => 'myForm', 'search' => 'shoes']));

    expect($form->submitted())->toBeTrue();
});

it('validates a submitted GET form (§1.1)', function () {
    $form = sweepForm(ArrayRequest::createGet(['_id' => 'myForm', 'search' => '']));

    $form->createAndValidate('Search[required]');

    expect($form->getErrors())->toHaveKey('search');
});

it('ignores a GET submission belonging to another form (§1.1)', function () {
    $form = sweepForm(
        ArrayRequest::createGet(['_id' => 'otherForm', 'search' => 'shoes']),
        ['id' => 'myForm'],
    );

    expect($form->submitted())->toBeFalse();
});

// §1.2 — a hyphenated label must render the name the validator reads ----------

it('renders a hyphenated label under the name the validator reads (§1.2)', function () {
    $form = sweepForm(ArrayRequest::createGet([]));

    $html = $form->create('E-mail[required]');

    expect($html)->toContain('name="e_mail"');
    expect($html)->toContain('id="e_mail"');
    expect($html)->not->toContain('name="e-mail"');
});

it('reads back a value posted under a hyphenated label (§1.2)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'e_mail' => 'a@b.com']));

    expect($form->request('E-mail[required]'))->toBe('a@b.com');
    expect($form->getErrors())->toBe([]);
});

// §1.3 — a scalar posted to a multi-value field must not fatal ----------------

it('does not fatal when a scalar is posted to a multi-value select (§1.3)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'colors' => 'red']));

    $html = $form->selectMultiple('colors', 'Colors', '', [
        'options' => ['red' => 'Red', 'blue' => 'Blue'],
    ]);

    expect($html)->toContain('<option value="red"');
    expect($html)->toContain('<option value="blue"');
});

it('still marks a scalar-posted option as selected (§1.3)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'colors' => 'red']));

    $html = $form->selectMultiple('colors', 'Colors', '', [
        'options' => ['red' => 'Red', 'blue' => 'Blue'],
    ]);

    expect($html)->toContain('<option value="red" selected');
    expect($html)->not->toContain('<option value="blue" selected');
});

it('still marks array-posted options as selected (§1.3)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'colors' => ['red', 'blue']]));

    $html = $form->selectMultiple('colors', 'Colors', '', [
        'options' => ['red' => 'Red', 'blue' => 'Blue', 'green' => 'Green'],
    ]);

    expect($html)->toContain('<option value="red" selected');
    expect($html)->toContain('<option value="blue" selected');
    expect($html)->not->toContain('<option value="green" selected');
});

// §1.4 — errors() must produce output in echo=false mode ----------------------

it('returns the error alert markup when echo is off (§1.4)', function () {
    $form = sweepForm(
        ArrayRequest::createPost(['_id' => 'myForm']),
        ['showErrorsAlert' => true],
    );

    $form->request('Name[required]');

    expect($form->errors())->toContain('The name field is required');
});

it('returns an empty string when there is nothing to report (§1.4)', function () {
    $form = sweepForm(
        ArrayRequest::createPost(['_id' => 'myForm', 'name' => 'Tim']),
        ['showErrorsAlert' => true],
    );

    $form->request('Name[required]');

    expect($form->errors())->toBe('');
});

it('still returns an empty string when showErrorsAlert is off (§1.4)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm']));

    $form->request('Name[required]');

    expect($form->errors())->toBe('');
});

// §1.5 — a submitted value must not expand template placeholders --------------

it('does not expand a submitted {{datalist}} placeholder (§1.5)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'city' => '{{datalist}}']));

    $html = $form->datalist('city', 'City', '', ['nyc' => 'New York', 'la' => 'Los Angeles']);

    expect($html)->toContain('value="{{datalist}}"');
    expect($html)->not->toContain('value="<datalist');
});

it('does not expand a submitted {{attributes}} placeholder (§1.5)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'name' => '{{attributes}}']));

    $html = $form->text('name', 'Name', '', ['maxlength' => '5']);

    expect($html)->toContain('value="{{attributes}}"');
    expect($html)->not->toContain('value=" maxlength');
});

it('does not expand a submitted {{classes}} placeholder (§1.5)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'name' => '{{classes}}']));

    $html = $form->text('name', 'Name');

    expect($html)->toContain('value="{{classes}}"');
});

it('does not expand a submitted {{options}} placeholder (§1.5)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'name' => '{{options}}']));

    $html = $form->text('name', 'Name');

    expect($html)->toContain('value="{{options}}"');
});

it('still escapes error text rendered into a field (§1.5)', function () {
    $form = sweepForm(ArrayRequest::createPost(['_id' => 'myForm', 'name' => '']));

    $form->request('name', ['required'], ['required' => '<script>alert(1)</script>']);
    $html = $form->text('name', 'Name');

    expect($html)->not->toContain('<script>alert(1)</script>');
    expect($html)->toContain('&lt;script&gt;');
});

// §1.6 — an empty string config must not fatal --------------------------------

it('accepts an empty string config (§1.6)', function () {
    $form = new Flick('');

    expect($form->config('views'))->toBe('flick');
});

it('still accepts a views shorthand string config (§1.6)', function () {
    $form = new Flick('bootstrap');

    expect($form->config('views'))->toBe('bootstrap');
});
