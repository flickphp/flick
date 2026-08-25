<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Session\ArraySession;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
    unset($_SERVER['REQUEST_METHOD']);
});

function renderForm(array $config = []): Flick
{
    return new Flick($config + ['csrf' => false, 'echo' => false]);
}

// Bug #31 — files() must append [] to the field name; without it PHP keeps only
// the last file of a multi-file selection.
it('appends [] to the field name of a files() input (#31)', function () {
    $html = renderForm()->files('documents', 'Documents');

    expect($html)
        ->toContain('name="documents[]"')
        ->toContain('multiple');
});

it('does not double-append [] when files() is given a [] name (#31)', function () {
    $html = renderForm()->files('documents[]', 'Documents');

    expect($html)
        ->toContain('name="documents[]"')
        ->not->toContain('name="documents[][]"');
});

it('leaves the single-file file() name untouched (#31)', function () {
    $html = renderForm()->file('resume', 'Resume');

    expect($html)
        ->toContain('name="resume"')
        ->not->toContain('name="resume[]"')
        ->not->toContain('multiple');
});

// Bug #18 — checkbox/radio option value and label must be escaped like
// buildSelectMenuOptions escapes select options.
// #18 offered two fixes: escape checkbox/radio option value+label, or enforce the
// trust boundary consistently. The first was taken in 2026-08-09; the second was
// taken in the 2026-08-11 sweep, because no request data can reach a label and
// escaping them broke documented markup (an icon in a button, a Terms link in a
// checkbox label). The value half of #18 stands — a value CAN carry request data.

it('escapes a script payload in a checkbox value (#18)', function () {
    $html = renderForm()->checkbox('color', 'Colour', '"><script>alert(1)</script>');

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

it('escapes a script payload in a radio value (#18)', function () {
    $html = renderForm()->radio('gender', 'Gender', '"><script>alert(1)</script>');

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

it('renders a checkbox label as developer-authored markup (#18)', function () {
    $html = renderForm()->checkbox('agree', 'I agree to the <a href="/terms">Terms</a>', 'yes');

    expect($html)->toContain('<a href="/terms">Terms</a>');
});

it('still escapes select option text, where markup is inert (#18)', function () {
    $html = renderForm()->select('color', 'Colour', '', [
        'options' => ['r' => '<script>alert(1)</script>'],
    ]);

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('still renders a plain checkbox exactly as before (#18)', function () {
    $html = renderForm()->checkbox('agree', 'I agree', 'yes');

    expect($html)
        ->toContain('name="agree"')
        ->toContain('value="yes"')
        ->toContain('I agree');
});

it('still marks a checkbox checked from the submitted value after escaping (#18)', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'agree' => 'yes'];

    $html = renderForm()->checkbox('agree', 'I agree', 'yes');

    expect($html)->toContain('checked');
});

// Bug #30 — submitMultistep() must render its $attributes argument onto the button.
it('renders string attributes onto the multistep submit button (#30)', function () {
    $html = renderForm()->submitMultistep('Go', 'class="my-custom-class" data-x="1"');

    expect($html)
        ->toContain('my-custom-class')
        ->toContain('data-x="1"')
        ->toContain('Go');
});

it('renders array attributes onto the multistep submit button (#30)', function () {
    $html = renderForm()->submitMultistep('Go', ['class' => 'my-custom-class', 'data-x' => '1']);

    expect($html)
        ->toContain('my-custom-class')
        ->toContain('data-x="1"');
});

it('keeps the theme default button class when submitMultistep gets no attributes (#30)', function () {
    $html = renderForm()->submitMultistep('Submit Form');

    // the default form renders the flick theme, whose button class this is
    expect($html)->toContain('class="flick-button"');
});

// Bug #32 — a render-time id change (attributes.id) must reach the shared
// Support instance, where services resolve the form id lazily.
it('syncs an attributes id into the shared Support instance (#32)', function () {
    $form = renderForm();
    $form->open('/', 'POST', ['id' => 'form-subscribe']);

    expect($form->support->config('id'))->toBe('form-subscribe');
});

it('leaves the configured id in Support when open() gets no id (#32)', function () {
    $form = renderForm(['id' => 'wizard']);
    $form->open('/', 'POST');

    expect($form->support->config('id'))->toBe('wizard');
});

// Bug #33 — createMultistep() must not render its $options as form-tag attributes.
it('does not leak multistep options onto the form tag (#33)', function () {
    $form = renderForm(['session' => new ArraySession]);
    $form->session->start();

    $steps = ['Step One' => ['fields' => ['name' => ['type' => 'text', 'label' => 'Name', 'name' => 'name']]]];
    $html = (string) $form->createMultistep($steps, [
        'auto' => true,
        'nextText' => 'Continue',
        'reviewTitle' => 'Check Your Answers',
        'reviewText' => 'Look it over',
        'submitText' => 'Send It',
    ]);

    preg_match('/<form[^>]*>/', $html, $m);
    $formTag = $m[0] ?? '';

    expect($formTag)->not->toBe('');
    expect($formTag)
        ->not->toContain('auto')
        ->not->toContain('nextText')
        ->not->toContain('reviewTitle')
        ->not->toContain('reviewText')
        ->not->toContain('submitText');
});

it('still forwards real form attributes from createMultistep options (#33)', function () {
    $form = renderForm(['session' => new ArraySession]);
    $form->session->start();

    $steps = ['Step One' => ['fields' => ['name' => ['type' => 'text', 'label' => 'Name', 'name' => 'name']]]];
    $html = (string) $form->createMultistep($steps, ['auto' => true, 'id' => 'wizard']);

    preg_match('/<form[^>]*>/', $html, $m);

    expect($m[0] ?? '')->toContain('id="wizard"');
});
