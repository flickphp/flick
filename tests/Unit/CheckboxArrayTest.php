<?php

use Flick\Flick;

beforeEach(function () {
    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);

    // Reset superglobals
    $_POST = [];
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {

    // Clean up superglobals
    $_POST = [];
    $_GET = [];
    unset($_SERVER['REQUEST_METHOD']);
});

// Checkbox array checked state tests

it('shows checkbox as checked when value is in POST array', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['colors'] = ['red', 'green'];
    $_POST['_id'] = 'myForm';

    $element = $this->form->checkboxInline('colors[]', 'Red', 'red');
    expect($element)->toContain('checked');
});

it('shows multiple checkboxes as checked when values are in POST array', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['colors'] = ['red', 'blue'];
    $_POST['_id'] = 'myForm';

    $redCheckbox = $this->form->checkboxInline('colors[]', 'Red', 'red');
    $greenCheckbox = $this->form->checkboxInline('colors[]', 'Green', 'green');
    $blueCheckbox = $this->form->checkboxInline('colors[]', 'Blue', 'blue');

    expect($redCheckbox)->toContain('checked');
    expect($greenCheckbox)->not->toContain('checked');
    expect($blueCheckbox)->toContain('checked');
});

it('does not show checkbox as checked when value is not in POST array', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['colors'] = ['red', 'green'];
    $_POST['_id'] = 'myForm';

    $element = $this->form->checkboxInline('colors[]', 'Blue', 'blue');
    expect($element)->not->toContain('checked');
});

it('shows checkbox as checked when value is in GET array', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['colors'] = ['red', 'green'];

    $element = $this->form->checkboxInline('colors[]', 'Red', 'red');
    expect($element)->toContain('checked');
});

it('does not show checkbox as checked when value is not in GET array', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['colors'] = ['red', 'green'];

    $element = $this->form->checkboxInline('colors[]', 'Blue', 'blue');
    expect($element)->not->toContain('checked');
});

// Request retrieval tests

it('retrieves array values from POST via request method', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['colors'] = ['red', 'green', 'blue'];
    $_POST['_id'] = 'myForm';

    $colors = $this->form->request('colors');

    expect($colors)->toBeArray();
    expect($colors)->toContain('red');
    expect($colors)->toContain('green');
    expect($colors)->toContain('blue');
});

it('retrieves single checkbox value from POST', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['agree'] = 'yes';
    $_POST['_id'] = 'myForm';

    $agree = $this->form->request('agree');
    expect($agree)->toBe('yes');
});

// Single checkbox regression tests

it('shows single checkbox as checked when POST value matches', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['agree'] = 'yes';
    $_POST['_id'] = 'myForm';

    $element = $this->form->checkbox('agree', 'I agree', 'yes');
    expect($element)->toContain('checked');
});

it('does not show single checkbox as checked when POST value does not match', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['agree'] = 'no';
    $_POST['_id'] = 'myForm';

    $element = $this->form->checkbox('agree', 'I agree', 'yes');
    expect($element)->not->toContain('checked');
});

// Radio button regression tests

it('shows radio as checked when POST value matches', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['gender'] = 'male';
    $_POST['_id'] = 'myForm';

    $maleRadio = $this->form->radio('gender', 'Male', 'male');
    $femaleRadio = $this->form->radio('gender', 'Female', 'female');

    expect($maleRadio)->toContain('checked');
    expect($femaleRadio)->not->toContain('checked');
});

it('generates correct name attribute for checkbox arrays', function () {
    $element = $this->form->checkboxInline('colors[]', 'Red', 'red');
    expect($element)->toContain('name="colors[]"');
});

it('generates unique id for each checkbox in an array', function () {
    $red = $this->form->checkboxInline('colors[]', 'Red', 'red');
    $green = $this->form->checkboxInline('colors[]', 'Green', 'green');
    $blue = $this->form->checkboxInline('colors[]', 'Blue', 'blue');

    expect($red)->toContain('id="colorsRed"');
    expect($green)->toContain('id="colorsGreen"');
    expect($blue)->toContain('id="colorsBlue"');
});

it('generates unique id for checkbox method with array name', function () {
    $monday = $this->form->checkbox('days[]', 'Monday', 'monday');
    $tuesday = $this->form->checkbox('days[]', 'Tuesday', 'tuesday');

    expect($monday)->toContain('id="daysMonday"');
    expect($tuesday)->toContain('id="daysTuesday"');
});

it('does not modify id for single checkboxes without array notation', function () {
    $element = $this->form->checkbox('agree', 'I agree', 'yes');
    expect($element)->toContain('id="agree"');
    expect($element)->not->toContain('id="agreeYes"');
});
