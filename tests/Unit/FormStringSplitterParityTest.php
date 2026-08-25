<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Http\ArrayRequest;

/*
 * Audit 2026-08-19, S06 — Build split a form-definition string at field
 * boundaries with its own regex (/,\s*(?![^(]*\)|[^[]*])/) while Validate used
 * a depth-tracked splitter. A regex rule whose character class contains a
 * comma ('[regex:/^[A-Z],[0-9]$/]') broke the regex's lookahead: create()
 * rendered a phantom input named '$/' and dropped the rule, while request()
 * on the same string read two fields.
 *
 * Build now calls Validate::prepareAFormStringForValidation(), the same
 * splitter the validator uses, so the renderer and the reader can no longer
 * disagree about where one field ends and the next begins. Side effect worth
 * pinning: that splitter collapses whitespace outside brackets, so a label
 * typed with several spaces ('First   Name') now renders as first_name - the
 * name the validator was already reading - instead of first___name.
 */

function splitterForm(array $post = []): Flick
{
    return new Flick([
        'request' => new ArrayRequest([
            'post' => $post,
            'server' => $post === []
                ? ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']
                : ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
        ]),
        'echo' => false,
        'csrf' => false,
    ]);
}

it('does not split a field at a comma inside a regex character class', function () {
    $form = splitterForm();
    $html = $form->create('Code[regex:/^[A-Z],[0-9]$/], Name[required]');

    $fields = $form->getFields();

    expect(array_keys($fields))->toBe(['code', 'name'])
        ->and($fields['code']['rules'])->toBe(['regex:/^[A-Z],[0-9]$/'])
        ->and($html)->not->toContain('name="$/"')
        ->and($html)->toContain('name="code"')
        ->and($html)->toContain('name="name"');
});

it('renders the same field set request() reads from the same string', function () {
    $definition = 'Code[regex:/^[A-Z],[0-9]$/], Name[required]';

    $rendered = splitterForm();
    $rendered->create($definition);

    $read = splitterForm(['_id' => 'myForm', 'code' => 'A,1', 'name' => 'Tim']);
    $values = $read->request($definition);

    expect(array_keys($values))->toBe(array_keys($rendered->getFields()))
        ->and($read->ok())->toBeTrue();
});

it('renders a multi-space label under the name the validator reads', function () {
    $rendered = splitterForm();
    $html = $rendered->create('First   Name[required]');

    // request() returns the bare value for a single-field string, so the
    // field having been found under first_name shows as 'Tim' rather than ''.
    $read = splitterForm(['_id' => 'myForm', 'first_name' => 'Tim']);
    $value = $read->request('First   Name[required]');

    expect($html)->toContain('name="first_name"')
        ->and($html)->not->toContain('first___name')
        ->and(array_keys($rendered->getFields()))->toBe(['first_name'])
        ->and($value)->toBe('Tim')
        ->and($read->ok())->toBeTrue();
});

it('still tolerates an empty segment between commas', function () {
    $form = splitterForm();
    $form->create('Name[required], , Email|email[email],');

    expect(array_keys($form->getFields()))->toBe(['name', 'email']);
});
