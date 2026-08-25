<?php

use Flick\Flick;

beforeEach(function () {
    $_POST = [];
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

afterEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_GET = [];
});

it('renders a form with a numeric id without fataling (M8)', function () {
    $element = $this->form->open('/submit', 'POST', ['id' => 123]);

    expect($element)
        ->toBeString()
        ->toContain('id="123"')
        ->toContain('name="_id" value="123"');
});

it('clears a default-checked checkbox after submission when it was unchecked (M9)', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    // the checkbox was unchecked, so it is absent from POST; another field is present
    $_POST['email'] = 'user@example.com';

    $element = $this->form->checkbox('agree', 'I agree', '1', ['checked' => true]);

    expect($element)->not->toContain('checked');
});

it('keeps a default-checked checkbox checked before submission (M9)', function () {
    $element = $this->form->checkbox('agree', 'I agree', '1', ['checked' => true]);

    expect($element)->toContain('checked');
});

it('keeps a checkbox checked after submission when it was checked (M9)', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['agree'] = '1';

    $element = $this->form->checkbox('agree', 'I agree', '1', ['checked' => true]);

    expect($element)->toContain('checked');
});

it('repopulates a fastForm text field from an integer posted value', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm', 'age' => 30];

    $html = $this->form->create(['fields' => [
        'age' => ['type' => 'text', 'label' => 'Age', 'name' => 'age'],
    ]]);

    expect($html)->toContain('value="30"');
});

it('does not leak a bare inline attribute into an inline checkbox, but still uses the inline view (L1)', function () {
    $element = $this->form->checkboxInline('agree', 'I agree', '1');

    expect($element)
        ->not->toContain(' inline>')
        ->not->toContain(' inline ')
        ->toContain('flick-checkbox-inline');
});

it('does not leak a bare inline attribute into an inline radio, but still uses the inline view (L1)', function () {
    $element = $this->form->radioInline('color', 'Red', 'red');

    // the boolean-inline view is shared across checkbox/radio, so it emits the
    // flick-checkbox-inline wrapper class for both
    expect($element)
        ->not->toContain(' inline>')
        ->not->toContain(' inline ')
        ->toContain('type="radio"')
        ->toContain('flick-checkbox-inline');
});
