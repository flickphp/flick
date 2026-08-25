<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $this->form = new Flick([
        'echo' => false,
        'csrf' => false,
    ]);
});

it('opens a form', function () {
    $element = $this->form->open('/submit');

    $string = '<form action="/submit" method="POST" id="myForm">';
    $string .= '<input type="hidden" name="_id" value="myForm">';
    $string = preg_replace('/\s+/', '', $string);

    $element = preg_replace('/\s+/', '', $element);
    expect($element)->toBeString()->toContain($string);
});

it('opens a form with options', function () {
    $element = $this->form->open('/search', 'GET', [
        'id' => 'mySearchForm',
        'class' => 'bg-gray',
    ]);

    $string = '<form action="/search" method="GET" id="mySearchForm" class="bg-gray">';
    $string .= '<input type="hidden" name="_id" value="mySearchForm">';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('closes a form', function () {
    $element = $this->form->close();

    expect($element)->toBeString()->toContain('</form>');
});

it('creates a hidden input', function () {
    $element = $this->form->hidden('name', 'value');

    $string = '<input type="hidden" name="name" id="name" value="value">';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a datetime-local input', function () {
    $element = $this->form->datetime('name', 'label', 'value', ['required' => true]);

    $string = '<label for="name" class="flick-label">label</label>';
    $string .= '<input type="datetime-local" name="name" id="name" value="value" class="flick-input" required>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates the correct element for each type', function () {
    $inputTypes = [
        'color',
        'date',
        'email',
        'file',
        'month',
        'number',
        'password',
        'range',
        'search',
        'tel',
        'text',
        'time',
        'url',
        'week',
    ];

    foreach ($inputTypes as $type) {
        $element = $this->form->$type('name', 'label');

        $string = '<label for="name" class="flick-label">label</label>';
        // file inputs have additional flick-file class
        $inputClass = $type === 'file' ? 'flick-input flick-file' : 'flick-input';
        $string .= '<input type="'.$type.'" name="name" id="name"';
        // file inputs don't have value attribute
        if ($type !== 'file') {
            $string .= ' value=""';
        }
        $string .= ' class="'.$inputClass.'">';

        $element = preg_replace('/\s+/', '', $element);
        $string = preg_replace('/\s+/', '', $string);
        expect($element)->toBeString()->toContain($string);
    }
});

it('creates an input with an array', function () {
    $element = $this->form->text([
        'name' => 'fullName',
        'label' => 'Your Name',
        'value' => 'Gern Blanston',
        'attributes' => [
            'required' => true,
            'maxlength' => 100,
        ],
    ]);

    $string = '<label for="fullName" class="flick-label">Your Name</label>';
    $string .= '<input type="text" name="fullName" id="fullName" value="Gern Blanston" class="flick-input" maxlength="100" required>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates an input with a required attribute', function () {
    $element = $this->form->text('name', 'label', '', 'required');

    $string = '<label for="name" class="flick-label">label</label>';
    $string .= '<input type="text" name="name" id="name" value="" class="flick-input" required>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a textarea', function () {
    $element = $this->form->textarea('name', 'label', 'foobar', ['required' => true]);

    $string = '<label for="name" class="flick-label">label</label>';
    $string .= '<textarea name="name" id="name" class="flick-input flick-textarea" required>foobar</textarea>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a checkbox', function () {
    $element = $this->form->checkbox('name', 'label', 'value');

    $string = '<input type="checkbox" name="name" id="name" value="value" class="flick-checkbox-input">';
    $string .= '<label for="name" class="flick-checkbox-label">label</label>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a radio', function () {
    $element = $this->form->radio('bar', 'Foo', 'foo');

    // Radio uses same classes as checkbox
    $string = '<input type="radio" name="bar" id="barFoo" value="foo" class="flick-checkbox-input">';
    $string .= '<label for="barFoo" class="flick-checkbox-label">Foo</label>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with an array', function () {
    $element = $this->form->select('name', 'label', 'two', [
        'one' => 'one',
        'two' => 'two',
    ]);

    $string = '<label for="name" class="flick-label">label</label>';
    $string .= '<select name="name" id="name" class="flick-input flick-select">';
    $string .= '<option value="one">one</option>';
    $string .= '<option value="two" selected>two</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with an options array', function () {
    $element = $this->form->select('name', 'label', 'two', [
        'options' => [
            'one' => 'one',
            'two' => 'two',
        ],
    ]);

    $string = '<label for="name" class="flick-label">label</label>';
    $string .= '<select name="name" id="name" class="flick-input flick-select">';
    $string .= '<option value="one">one</option>';
    $string .= '<option value="two" selected>two</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with a string', function () {
    $element = $this->form->select('name', 'label', '8', 'months');

    $string = '<label for="name" class="flick-label">label</label>';
    $string .= '<select name="name" id="name" class="flick-input flick-select">';
    $string .= '<option value="1">January</option>';
    $string .= '<option value="2">February</option>';
    $string .= '<option value="3">March</option>';
    $string .= '<option value="4">April</option>';
    $string .= '<option value="5">May</option>';
    $string .= '<option value="6">June</option>';
    $string .= '<option value="7">July</option>';
    $string .= '<option value="8" selected>August</option>';
    $string .= '<option value="9">September</option>';
    $string .= '<option value="10">October</option>';
    $string .= '<option value="11">November</option>';
    $string .= '<option value="12">December</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with a string and adds a default option', function () {
    $element = $this->form->select('name', 'label', '8', 'months::Select a Month...');

    $string = '<label for="name" class="flick-label">label</label>';
    $string .= '<select name="name" id="name" class="flick-input flick-select">';
    $string .= '<option value="0">Select a Month...</option>';
    $string .= '<option value="1">January</option>';
    $string .= '<option value="2">February</option>';
    $string .= '<option value="3">March</option>';
    $string .= '<option value="4">April</option>';
    $string .= '<option value="5">May</option>';
    $string .= '<option value="6">June</option>';
    $string .= '<option value="7">July</option>';
    $string .= '<option value="8" selected>August</option>';
    $string .= '<option value="9">September</option>';
    $string .= '<option value="10">October</option>';
    $string .= '<option value="11">November</option>';
    $string .= '<option value="12">December</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a submit button', function () {
    $element = $this->form->submit();
    $element = preg_replace('/\s+/', '', $element);

    $string = '<button type="submit" id="submit" class="flick-button">Submit</button>';
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a submit button with options', function () {
    $element = $this->form->submit('Login', [
        'class' => 'my-class',
        'onmouseover' => 'doSomething()',
    ]);

    $string = '<button type="submit" id="submit" class="flick-button my-class" onmouseover="doSomething()">Login</button>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('displays an element value after $_POST', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['one'] = 'two';
    $_POST['_id'] = 'myForm'; // Add form ID for submission validation

    $element = $this->form->value('one');

    expect($element)->toBeString()->toBe('two');
});
