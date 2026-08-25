<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

/*
|--------------------------------------------------------------------------
| Hardening sweep regression tests
|--------------------------------------------------------------------------
|
| Section 2 of the maintainers' 2026-08-11 pre-release review notes:
| inconsistencies that were not outright bugs but left the library behaving
| two different ways.
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

function hardeningForm(array $post = [], array $config = []): Flick
{
    return new Flick(array_merge([
        'request' => $post === [] ? ArrayRequest::createGet([]) : ArrayRequest::createPost($post),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
    ], $config));
}

/*
|--------------------------------------------------------------------------
| §2.1 — labels are trusted HTML in every builder
|--------------------------------------------------------------------------
|
| The review asked for one consistent policy instead of the split where only
| boolean() escaped. The policy is "labels are developer-authored markup":
| every label a developer writes renders as-is, and no request data can reach
| a label. Values still get escaped, which is where request data actually goes.
|
*/

$markup = '<i class="fa-search"></i> Search';

it('renders label markup as-is on a text input (§2.1)', function () use ($markup) {
    expect(hardeningForm()->text('name', $markup))->toContain($markup);
});

it('renders label markup as-is on a textarea (§2.1)', function () use ($markup) {
    expect(hardeningForm()->textarea('bio', $markup))->toContain($markup);
});

it('renders label markup as-is on a select menu (§2.1)', function () use ($markup) {
    expect(hardeningForm()->select('color', $markup, '', ['options' => ['r' => 'Red']]))
        ->toContain($markup);
});

it('renders markup as-is on a submit button (§2.1)', function () use ($markup) {
    expect(hardeningForm()->submit($markup))->toContain($markup);
});

it('renders label markup as-is on a checkbox (§2.1)', function () {
    $link = 'I agree to the <a href="/terms">Terms</a>';

    expect(hardeningForm()->checkbox('agree', $link, '1'))->toContain($link);
});

it('keeps the documented icon-in-button example working (§2.1)', function () use ($markup) {
    $html = hardeningForm()->create('Search|search', [
        'id' => 'form-search',
        'method' => 'GET',
        'button' => $markup,
    ]);

    expect($html)->toContain($markup);
});

it('still escapes a repopulated value, which is where request data lands (§2.1)', function () {
    $form = hardeningForm(['_id' => 'myForm', 'name' => '"><script>alert(1)</script>']);

    $html = $form->text('name', 'Name');

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('still escapes a checkbox value (§2.1)', function () {
    $html = hardeningForm()->checkbox('agree', 'Agree', '"><script>alert(1)</script>');

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('leaves an ordinary label untouched (§2.1)', function () {
    expect(hardeningForm()->text('name', 'First Name'))->toContain('First Name');
});

// §2.2 — the renderer and the validator read the same rule separator ---------

it('applies comma-separated rules on the render side (§2.2)', function () {
    $html = hardeningForm()->create('Email[required, email]');

    expect($html)->toContain('required');
});

it('does not silently accept a pipe rule separator the validator rejects (§2.2)', function () {
    $html = hardeningForm()->create('Email[required|email]');

    // the validator has no idea what 'required|email' is, so the rendered field
    // must not claim the rule was understood
    expect($html)->not->toContain('required');
});

// found while fixing §2.2: isRequired() compared loosely, so 'required' == true
// matched any rule given as a map and every ruled field rendered as required

it('does not mark a field required just because it carries another rule', function () {
    $html = hardeningForm()->create('Email[email]');

    expect($html)->not->toContain('required');
});

it('still marks a field required from a rules list', function () {
    $html = hardeningForm()->text('email', 'Email', '', ['rules' => ['required', 'email']]);

    expect($html)->toContain('required');
});

it('still marks a field required from a rules map', function () {
    $html = hardeningForm()->text('email', 'Email', '', ['rules' => ['required' => true]]);

    expect($html)->toContain('required');
});

it('keeps a regex rule argument containing a pipe intact (§2.2)', function () {
    $form = hardeningForm(['_id' => 'myForm', 'code' => 'cat']);

    $form->request('code', ['regex:/^(cat|dog)$/']);

    expect($form->getErrors())->toBe([]);
});

// §2.3 — a wrong CSRF token is reported, not swallowed -----------------------

it('reports a CSRF token mismatch instead of failing silently (§2.3)', function () {
    $session = new ArraySession;
    $session->setValue('_token', 'correcttoken');
    $session->setValue('_token_expires', time() + 3600);

    $form = new Flick([
        'request' => ArrayRequest::createPost(['_id' => 'myForm', '_token' => 'WRONG', 'name' => 'Tim']),
        'session' => $session,
        'echo' => false,
        'csrf' => true,
    ]);

    expect($form->submitted())->toBeFalse();
    expect($form->getErrors())->not->toBe([]);
});

it('reports an expired session token in echo=false mode too (§2.3)', function () {
    $form = new Flick([
        'request' => ArrayRequest::createPost(['_id' => 'myForm', '_token' => 'abc', 'name' => 'Tim']),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => true,
    ]);

    $form->createAndValidate('Name[required]');

    expect($form->getErrors())->not->toBe([]);
});

it('raises no CSRF error when the token is correct (§2.3)', function () {
    $session = new ArraySession;
    $session->setValue('_token', 'correcttoken');
    $session->setValue('_token_expires', time() + 3600);

    $form = new Flick([
        'request' => ArrayRequest::createPost(['_id' => 'myForm', '_token' => 'correcttoken', 'name' => 'Tim']),
        'session' => $session,
        'echo' => false,
        'csrf' => true,
    ]);

    expect($form->submitted())->toBeTrue();
    expect($form->createAndValidate('Name[required]'))->toBe('Tim');
    expect($form->getErrors())->toBe([]);
});

// §2.4 — notMatches honours the trim policy ---------------------------------

it('applies the trim policy to the notMatches counterpart field (§2.4)', function () {
    $form = hardeningForm(['_id' => 'myForm', 'a' => 'hello', 'b' => ' hello ']);

    $form->createAndValidate('A[notMatches:b], B');

    expect($form->getErrors())->toHaveKey('a');
});

it('still passes notMatches when the values genuinely differ (§2.4)', function () {
    $form = hardeningForm(['_id' => 'myForm', 'a' => 'hello', 'b' => 'goodbye']);

    $form->createAndValidate('A[notMatches:b], B');

    expect($form->getErrors())->toBe([]);
});

it('honours trim=false for the notMatches counterpart field (§2.4)', function () {
    $form = hardeningForm(['_id' => 'myForm', 'a' => 'hello', 'b' => ' hello '], ['trim' => false]);

    $form->createAndValidate('A[notMatches:b], B');

    // nothing is trimmed, so the two values really are different
    expect($form->getErrors())->toBe([]);
});

// §2.5 — the missingArgument message substitutes its placeholders ------------

it('substitutes every placeholder in the missingArgument message (§2.5)', function () {
    $form = hardeningForm(['_id' => 'myForm', 'one' => 'abc']);

    $form->request('one', ['between:5']);

    expect($form->getError('one'))->not->toContain(':argument');
    expect($form->getError('one'))->toContain('one');
});

it('still lets a custom missingArgument message win (§2.5)', function () {
    $form = hardeningForm(['_id' => 'myForm', 'one' => '5']);

    $form->request('one', ['between:5'], ['missingArgument' => 'ERROR']);

    expect($form->getError('one'))->toBe('ERROR');
});
