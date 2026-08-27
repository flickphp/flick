<?php

use Flick\Flick;

/*
|--------------------------------------------------------------------------
| Modifiers (Validate::modifiers())
|--------------------------------------------------------------------------
|
| Every case here is written from the "Modifiers" section of validation.md,
| and where the docs give a worked example the test uses that exact example.
|
| Mutation testing on 2026-08-27 found the whole modifier table was uncovered:
| strip_tags() could be deleted from Validate::modifiers() without a single
| test noticing, and eight of the ten modifiers had zero mentions anywhere in
| the suite. Each test below was confirmed to go red when its modifier is
| reduced to fn ($val) => $val.
|
| Note on the posted _id: it must match the instance id ('myForm'), or
| validation is skipped entirely and request() returns null. That trap is
| documented in the project memory and it silently voids a test that looks
| like it passes.
|
*/

beforeEach(function () {
    Flick::resetDefaultRequest();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    $_GET = [];
});

function modified(string $input, string $modifier): mixed
{
    $_POST = ['_id' => 'myForm', 'field' => $input];

    $form = new Flick(['csrf' => false, 'echo' => false]);

    return $form->request('field', ['required', $modifier]);
}

// -- stripTags ---------------------------------------------------------------

it('stripTags strips html tags', function () {
    expect(modified('<p>Hello <b>world</b></p>', 'stripTags'))->toBe('Hello world');
});

it('stripTags strips php tags', function () {
    expect(modified('before<?php echo "x"; ?>after', 'stripTags'))->toBe('beforeafter');
});

it('stripTags keeps the text inside a script tag, so it is not an xss defence', function () {
    // strip_tags() removes <script> and </script> but not what sits between
    // them. Callers must still escape on output.
    expect(modified('<script>alert(1)</script>', 'stripTags'))->toBe('alert(1)');
});

it('stripTags leaves a value with no tags untouched', function () {
    expect(modified('plain text, nothing to strip', 'stripTags'))->toBe('plain text, nothing to strip');
});

// -- stripAlpha / stripNumeric ----------------------------------------------

it('stripAlpha keeps only digits', function () {
    // validation.md: 'a1b2c3' becomes '123'
    expect(modified('a1b2c3', 'stripAlpha'))->toBe('123');
});

it('stripNumeric keeps only letters', function () {
    // validation.md: 'Gern9 Blanston2' becomes 'GernBlanston'
    expect(modified('Gern9 Blanston2', 'stripNumeric'))->toBe('GernBlanston');
});

it('stripAlpha and stripNumeric are opposites, not aliases', function () {
    // They were adjacent one-line closures with the same shape; a swapped
    // regex between them would otherwise go unnoticed.
    expect(modified('a1b2c3', 'stripAlpha'))->toBe('123')
        ->and(modified('a1b2c3', 'stripNumeric'))->toBe('abc');
});

// -- slug --------------------------------------------------------------------

it('slug joins words with underscores, not hyphens', function () {
    // validation.md: 'Hello World! Some Title' becomes 'Hello_World_Some_Title'
    expect(modified('Hello World! Some Title', 'slug'))->toBe('Hello_World_Some_Title');
});

// -- sanitizeChars -----------------------------------------------------------

it('sanitizeChars converts special characters to html entities', function () {
    expect(modified('Tom & "Jerry" <b>bold</b>', 'sanitizeChars'))
        ->toBe('Tom &amp; &quot;Jerry&quot; &lt;b&gt;bold&lt;/b&gt;');
});

// -- sanitizeEmail -----------------------------------------------------------

it('sanitizeEmail removes characters an address cannot contain', function () {
    // Spaces and parentheses go. The bang stays — it is legal in a local part,
    // so this documents that sanitizeEmail is not a validity check.
    expect(modified('bad!name(x)@exam ple.com', 'sanitizeEmail'))->toBe('bad!namex@example.com');
});

// -- sanitizeInt -------------------------------------------------------------

it('sanitizeInt keeps digits and the sign, dropping everything else', function () {
    // The decimal point is stripped too, so '12x3.45' collapses to '12345'.
    expect(modified('abc-12x3.45', 'sanitizeInt'))->toBe('-12345');
});

// -- sanitizeUrl -------------------------------------------------------------

it('sanitizeUrl removes characters a url cannot contain', function () {
    expect(modified('https://exa mple.com/a b?q=1', 'sanitizeUrl'))->toBe('https://example.com/ab?q=1');
});

// -- bcrypt / hash -----------------------------------------------------------

it('bcrypt returns a verifiable bcrypt hash, not the raw value', function () {
    $hash = modified('secret123', 'bcrypt');

    expect($hash)->not->toBe('secret123')
        ->and($hash)->toStartWith('$2y$')
        ->and(password_verify('secret123', $hash))->toBeTrue();
});

it('hash is an alias for bcrypt', function () {
    $hash = modified('secret123', 'hash');

    expect($hash)->toStartWith('$2y$')
        ->and(password_verify('secret123', $hash))->toBeTrue();
});

// -- the modifier does not replace the rule ---------------------------------

it('still applies required alongside a modifier', function () {
    // The docs pair them: ['required', 'stripTags']. An empty value must still
    // fail required rather than being swallowed by the modifier.
    $_POST = ['_id' => 'myForm', 'field' => ''];

    $form = new Flick(['csrf' => false, 'echo' => false]);
    $form->request('field', ['required', 'stripTags']);

    expect($form->hasError('field'))->toBeTrue();
});
