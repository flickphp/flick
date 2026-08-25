<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * A checkbox/radio element in a fastForm array definition that carries an
 * options list must render as a GROUP — one input per option — the way the
 * same element renders in the string syntax. The single-element dispatch
 * silently dropped the options and rendered one lone box, and a submitted
 * multi-value POST (an array) crashed the re-render with a TypeError.
 */

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];

    $this->form = new Flick(['csrf' => false, 'echo' => false]);

    $this->definition = [
        'fields' => [
            'colors' => [
                'type' => 'checkbox',
                'label' => 'Colors',
                'name' => 'colors',
                'options' => ['red' => 'Red', 'green' => 'Green', 'blue' => 'Blue'],
            ],
            'plan' => [
                'type' => 'radio',
                'label' => 'Plan',
                'name' => 'plan',
                'options' => ['free' => 'Free', 'pro' => 'Pro'],
            ],
        ],
    ];
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
});

it('renders a fastForm checkbox element with options as a full group', function () {
    $html = $this->form->create($this->definition);

    expect(substr_count($html, 'type="checkbox"'))->toBe(3)
        ->and($html)->toContain('name="colors[]"')
        ->and($html)->toContain('value="red"')
        ->and($html)->toContain('value="green"')
        ->and($html)->toContain('value="blue"')
        ->and($html)->toContain('id="colorsRed"')
        ->and($html)->toContain('id="colorsGreen"');
});

it('renders a fastForm radio element with options as a full group sharing one name', function () {
    $html = $this->form->create($this->definition);

    expect(substr_count($html, 'type="radio"'))->toBe(2)
        ->and($html)->toContain('name="plan"')
        ->and($html)->not->toContain('name="plan[]"')
        ->and($html)->toContain('value="free"')
        ->and($html)->toContain('value="pro"');
});

it('re-renders a submitted fastForm group with the checked state, without crashing', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'colors' => ['red', 'blue'], 'plan' => 'pro'];

    $html = $this->form->create($this->definition);

    preg_match_all('/value="red"[^>]*/', $html, $red);
    preg_match_all('/value="green"[^>]*/', $html, $green);
    preg_match_all('/value="blue"[^>]*/', $html, $blue);
    preg_match_all('/value="pro"[^>]*/', $html, $pro);

    expect($red[0][0])->toContain('checked')
        ->and($green[0][0])->not->toContain('checked')
        ->and($blue[0][0])->toContain('checked')
        ->and($pro[0][0])->toContain('checked');
});

it('re-renders a submitted fastForm selectMultiple without crashing and reselects', function () {
    $definition = [
        'fields' => [
            'skills' => [
                'type' => 'selectMultiple',
                'label' => 'Skills',
                'name' => 'skills',
                'options' => ['php' => 'PHP', 'js' => 'JS', 'go' => 'Go'],
            ],
        ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'skills' => ['php', 'go']];

    $html = $this->form->create($definition);

    preg_match_all('/<option value="php"[^>]*/', $html, $php);
    preg_match_all('/<option value="js"[^>]*/', $html, $js);

    expect($html)->toContain('name="skills[]"')
        ->and($php[0][0])->toContain('selected')
        ->and($js[0][0])->not->toContain('selected');
});

it('marks every member of a required fastForm group as required', function () {
    $definition = [
        'fields' => [
            'colors' => [
                'type' => 'checkbox',
                'label' => 'Colors',
                'name' => 'colors',
                'options' => ['red' => 'Red', 'green' => 'Green'],
                'rules' => ['required'],
            ],
        ],
    ];

    $html = $this->form->create($definition);

    expect(substr_count($html, 'type="checkbox"'))->toBe(2)
        ->and(substr_count($html, 'required'))->toBeGreaterThanOrEqual(2);
});

it('renders an inline fastForm checkbox group one member per option', function () {
    $definition = [
        'fields' => [
            'colors' => [
                'type' => 'checkboxInline',
                'label' => 'Colors',
                'name' => 'colors',
                'options' => ['red' => 'Red', 'green' => 'Green'],
            ],
        ],
    ];

    $html = $this->form->create($definition);

    expect(substr_count($html, 'type="checkbox"'))->toBe(2)
        ->and($html)->toContain('name="colors[]"');
});

it('still renders a single fastForm checkbox without options as one input', function () {
    $definition = [
        'fields' => [
            'agree' => [
                'type' => 'checkbox',
                'label' => 'I agree',
                'name' => 'agree',
                'value' => 'yes',
            ],
        ],
    ];

    $html = $this->form->create($definition);

    expect(substr_count($html, 'type="checkbox"'))->toBe(1)
        ->and($html)->toContain('name="agree"')
        ->and($html)->not->toContain('name="agree[]"');
});
