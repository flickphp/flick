<?php

declare(strict_types=1);

use Flick\Flick;

beforeEach(function () {
    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);

    $_POST = [];
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
    unset($_SERVER['REQUEST_METHOD']);
});

it('creates checkbox group from string syntax', function () {
    $element = $this->form->create('Colors|checkbox([red:Red, green:Green, blue:Blue])', ['action' => '/submit']);

    expect($element)->toContain('name="colors[]"');
    expect($element)->toContain('value="red"');
    expect($element)->toContain('value="green"');
    expect($element)->toContain('value="blue"');
    // Labels may have whitespace around text
    expect($element)->toMatch('/>\s*Red\s*<\/label>/');
    expect($element)->toMatch('/>\s*Green\s*<\/label>/');
    expect($element)->toMatch('/>\s*Blue\s*<\/label>/');
});

it('generates unique IDs for checkbox group items', function () {
    $element = $this->form->create('Colors|checkbox([red:Red, green:Green])', ['action' => '/submit']);

    expect($element)->toContain('id="colorsRed"');
    expect($element)->toContain('id="colorsGreen"');
});

it('creates checkbox group with required validation', function () {
    $element = $this->form->create('Colors|checkbox([red:Red, green:Green])[r]', ['action' => '/submit']);

    expect($element)->toContain('required');
});

it('preserves checked state from POST for checkbox groups', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['colors'] = ['red', 'blue'];
    $_POST['_id'] = 'myForm';

    $element = $this->form->create('Colors|checkbox([red:Red, green:Green, blue:Blue])', ['action' => '/submit']);

    // Red and blue should be checked, green should not
    preg_match_all('/value="red"[^>]*/', $element, $redMatches);
    preg_match_all('/value="green"[^>]*/', $element, $greenMatches);
    preg_match_all('/value="blue"[^>]*/', $element, $blueMatches);

    expect($redMatches[0][0])->toContain('checked');
    expect($greenMatches[0][0])->not->toContain('checked');
    expect($blueMatches[0][0])->toContain('checked');
});

it('still creates single checkbox without options', function () {
    $element = $this->form->create('Agree|checkbox', ['action' => '/submit']);

    expect($element)->toContain('name="agree"');
    expect($element)->not->toContain('name="agree[]"');
    expect($element)->toContain('type="checkbox"');
});

it('creates single checkbox with default value', function () {
    $element = $this->form->create('Agree|checkbox{yes}', ['action' => '/submit']);

    expect($element)->toContain('value="yes"');
});

it('creates mixed form with checkbox groups and other elements', function () {
    $element = $this->form->create('Name, Colors|checkbox([red:Red, green:Green]), Email|email', ['action' => '/submit']);

    expect($element)->toContain('name="name"');
    expect($element)->toContain('name="colors[]"');
    expect($element)->toContain('name="email"');
    expect($element)->toContain('type="email"');
});

it('renders checkbox group stacked by default', function () {
    $element = $this->form->create('Colors|checkbox([red:Red, green:Green])', ['action' => '/submit']);

    // Should NOT have inline class - stacked is the default
    expect($element)->toContain('type="checkbox"');
    expect($element)->not->toContain('flick-checkbox-inline');
});

it('renders checkbox group inline when using checkboxInline type', function () {
    $element = $this->form->create('Colors|checkboxInline([red:Red, green:Green])', ['action' => '/submit']);

    // Should have inline class
    expect($element)->toContain('type="checkbox"');
    expect($element)->toContain('flick-checkbox-inline');
});

it('renders group label for checkbox group', function () {
    $element = $this->form->create('Favorite Colors|checkbox([red:Red, green:Green])', ['action' => '/submit']);

    // Group label should appear in a wrapper div
    expect($element)->toContain('class="flick-checkbox-group"');
    expect($element)->toContain('class="flick-label"');
    expect($element)->toContain('Favorite Colors');
});

// RADIO GROUP TESTS ----------------------------------------------------------

it('creates radio group from string syntax', function () {
    $element = $this->form->create('Gender|radio([m:Male, f:Female])', ['action' => '/submit']);

    expect($element)->toContain('name="gender"');
    expect($element)->toContain('type="radio"');
    expect($element)->toContain('value="m"');
    expect($element)->toContain('value="f"');
    expect($element)->toMatch('/>\s*Male\s*<\/label>/');
    expect($element)->toMatch('/>\s*Female\s*<\/label>/');
});

it('generates unique IDs for radio group items', function () {
    $element = $this->form->create('Gender|radio([m:Male, f:Female])', ['action' => '/submit']);

    expect($element)->toContain('id="genderM"');
    expect($element)->toContain('id="genderF"');
});

it('renders group label for radio group', function () {
    $element = $this->form->create('Gender|radio([m:Male, f:Female])', ['action' => '/submit']);

    // Group label should appear in a wrapper div
    expect($element)->toContain('class="flick-radio-group"');
    expect($element)->toContain('class="flick-label"');
    expect($element)->toContain('Gender');
});

it('renders radio group inline when using radioInline type', function () {
    $element = $this->form->create('Gender|radioInline([m:Male, f:Female])', ['action' => '/submit']);

    expect($element)->toContain('type="radio"');
    expect($element)->toContain('flick-checkbox-inline');
});

it('preserves checked state from POST for radio groups', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['gender'] = 'f';
    $_POST['_id'] = 'myForm';

    $element = $this->form->create('Gender|radio([m:Male, f:Female])', ['action' => '/submit']);

    // Female should be checked, Male should not
    preg_match_all('/value="m"[^>]*/', $element, $maleMatches);
    preg_match_all('/value="f"[^>]*/', $element, $femaleMatches);

    expect($maleMatches[0][0])->not->toContain('checked');
    expect($femaleMatches[0][0])->toContain('checked');
});

it('still creates single radio without options', function () {
    $element = $this->form->create('Option|radio', ['action' => '/submit']);

    expect($element)->toContain('name="option"');
    expect($element)->toContain('type="radio"');
});
