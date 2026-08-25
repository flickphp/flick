<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * 'rules', 'options' and 'messages' belong at the top level of a fastForm
 * element — that is the documented array syntax. Nested inside the element's
 * 'attributes' bag they used to reach the RENDERER and not the submit parser,
 * because buildFastFormElement() copies the top-level keys DOWN into
 * attributes while parseSubmittedFastFormElement() only ever read the top
 * level.
 *
 * The rules case fails OPEN: the field registry drives the client-side
 * validator, so nested rules ran in the browser and never on the server.
 * The options case renders a checkbox group named colors[] and then validates
 * the scalar key 'colors', so per-element rules never apply.
 *
 * Both placements must now behave identically. Top level still wins when both
 * carry the same key.
 */

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];

    $this->form = new Flick(['csrf' => false, 'echo' => false]);
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
});

// --- rules nested in attributes ---------------------------------------------

it('applies rules nested in attributes on the server, not just in the browser', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'email' => 'nope'];

    $this->form->request([
        'fields' => [
            'email' => [
                'type' => 'email',
                'label' => 'Email',
                'attributes' => ['rules' => ['required', 'email']],
            ],
        ],
    ]);

    expect($this->form->hasError('email'))->toBeTrue();
});

it('uses a message nested in attributes for a rule nested beside it', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'email' => ''];

    $this->form->request([
        'fields' => [
            'email' => [
                'type' => 'email',
                'label' => 'Email',
                'attributes' => [
                    'rules' => ['required'],
                    'messages' => ['required' => 'We need your email address'],
                ],
            ],
        ],
    ]);

    expect($this->form->getError('email'))->toContain('We need your email address');
});

it('lets top-level rules win over rules nested in attributes', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'email' => 'nope'];

    $this->form->request([
        'fields' => [
            'email' => [
                'type' => 'email',
                'label' => 'Email',
                'rules' => [],
                'attributes' => ['rules' => ['required', 'email']],
            ],
        ],
    ]);

    expect($this->form->hasError('email'))->toBeFalse();
});

// --- options nested in attributes -------------------------------------------

it('applies a rule per element to a group whose options are nested in attributes', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // every element is a valid choice, so a per-element 'in' must pass. Looked
    // up under the scalar key 'colors' instead, the array trips the
    // array-on-a-scalar-field guard and fails for an unrelated reason.
    $_POST = ['_id' => 'myForm', 'colors' => ['red', 'green']];

    $value = $this->form->request([
        'fields' => [
            'colors' => [
                'type' => 'checkbox',
                'label' => 'Colors',
                'rules' => ['in:red,green'],
                'attributes' => [
                    'options' => ['red' => 'Red', 'green' => 'Green'],
                ],
            ],
        ],
    ]);

    expect($this->form->hasError('colors'))->toBeFalse()
        ->and($value['colors'])->toBe(['red', 'green']);
});

it('rejects an invalid element of a group whose options are nested in attributes', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'colors' => ['red', 'purple']];

    $this->form->request([
        'fields' => [
            'colors' => [
                'type' => 'checkbox',
                'label' => 'Colors',
                'rules' => ['in:red,green'],
                'attributes' => [
                    'options' => ['red' => 'Red', 'green' => 'Green'],
                ],
            ],
        ],
    ]);

    expect($this->form->hasError('colors'))->toBeTrue();
});

it('renders a nested-options group as colors[] and validates the same name', function () {
    $definition = [
        'fields' => [
            'colors' => [
                'type' => 'checkbox',
                'label' => 'Colors',
                'attributes' => [
                    'options' => ['red' => 'Red', 'green' => 'Green'],
                ],
            ],
        ],
    ];

    expect($this->form->create($definition))->toContain('name="colors[]"');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'colors' => ['red']];

    $submitted = new Flick(['csrf' => false, 'echo' => false]);
    $value = $submitted->request($definition);

    expect($value['colors'])->toBe(['red']);
});

// --- the documented top-level placement is unaffected ------------------------

it('still applies top-level rules when attributes carries none', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'email' => 'nope'];

    $this->form->request([
        'fields' => [
            'email' => [
                'type' => 'email',
                'label' => 'Email',
                'rules' => ['required', 'email'],
            ],
        ],
    ]);

    expect($this->form->hasError('email'))->toBeTrue();
});
