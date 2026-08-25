<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];

    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
});

// ---------------------------------------------------------------------------
// the days dropdown must submit the day it displays
// ---------------------------------------------------------------------------

it('keys the days dropdown by the day number', function () {
    $days = require __DIR__.'/../../lang/en/dropdowns/days.php';

    expect(array_key_first($days))->toBe(1);
    expect($days[1])->toBe(1);
    expect(array_key_last($days))->toBe((int) date('t'));
    expect($days[(int) date('t')])->toBe((int) date('t'));
});

it('renders day options whose value matches their label', function () {
    $html = $this->form->create('Day|select(days)');

    expect($html)->toContain('<option value="1">1</option>');
    expect($html)->toContain('<option value="2">2</option>');
    expect($html)->not->toContain('<option value="0">1</option>');
});

it('gives the days dropdown one option per day of the current month', function () {
    $html = $this->form->create('Day|select(days)');

    preg_match_all('/<option value="\d+">\d+<\/option>/', $html, $matches);

    expect($matches[0])->toHaveCount((int) date('t'));
});

// ---------------------------------------------------------------------------
// the type attribute must be written once
// ---------------------------------------------------------------------------

it('writes the type attribute once when set through the attributes array', function () {
    $html = $this->form->input('custom', 'Custom Field', '', ['type' => 'email']);

    expect(substr_count($html, 'type='))->toBe(1);
    expect($html)->toContain('type="email"');
});

it('still honours a type set through the attributes array', function () {
    $html = $this->form->input('custom', 'Custom Field', '', ['type' => 'tel']);

    expect($html)->toContain('type="tel"');
    expect($html)->not->toContain('type="text"');
});

it('writes the type once for the array configuration form', function () {
    $html = $this->form->input(['name' => 'custom', 'type' => 'email', 'label' => 'Custom Field'], '');

    expect(substr_count($html, 'type='))->toBe(1);
    expect($html)->toContain('type="email"');
});

it('keeps other attributes alongside a type override', function () {
    $html = $this->form->input('custom', 'Custom Field', '', ['type' => 'email', 'placeholder' => 'you@example.com']);

    expect($html)->toContain('placeholder="you@example.com"');
    expect(substr_count($html, 'type='))->toBe(1);
});

// ---------------------------------------------------------------------------
// an empty element type must not blow up on a missing view file
// ---------------------------------------------------------------------------

it('falls back to text when the element type is empty', function () {
    $html = $this->form->create('State|(states)');

    expect($html)->toContain('name="state"');
});

it('renders a bare pipe with no type as a text input', function () {
    $html = $this->form->create('Name|');

    expect($html)->toContain('type="text"');
    expect($html)->toContain('name="name"');
});
