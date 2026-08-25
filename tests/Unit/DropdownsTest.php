<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
        'assets' => __DIR__.'/../test-files/assets',
    ]);
});

it('creates a dropdown with a string', function () {
    $element = $this->form->create('Month|select(months)');

    $string = '<option value="1">January</option>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with a string prepended with a forward slash', function () {
    $element = $this->form->create('Month|select(/months)');

    $string = '<option value="1">January</option>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with an options array', function () {
    $element = $this->form->select('one', 'One', 'one', ['one' => 'One', 'two' => 'Two']);

    $string = '<option value="one" selected>One</option>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with an options array string', function () {
    $element = $this->form->select('one', 'One', 'one', '[one:One, two:Two]');

    $string = '<option value="one" selected>One</option>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown and loads user-supplied options', function () {
    $element = $this->form->select('one', 'One', 'one', 'foo');

    $string = '<select name="one" id="one" class="form-control">';
    $string .= '<optgroup label="foobar"><option value="foo">Foo</option>';
    $string .= '<option value="bar">Bar</option>';
    $string .= '</optgroup><optgroup label="foobaz"><option value="baz">Baz</option>';
    $string .= '<option value="barbaz">Barbaz</option>';
    $string .= '</optgroup>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown and loads flick-supplied options', function () {
    $element = $this->form->select('month', 'Month', '', 'months');

    $string = '<select name="month" id="month" class="form-control " >';
    $string .= '<option value="1">January</option>';
    $string .= '<option value="2">February</option>';
    $string .= '<option value="3">March</option>';
    $string .= '<option value="4">April</option>';
    $string .= '<option value="5">May</option>';
    $string .= '<option value="6">June</option>';
    $string .= '<option value="7">July</option>';
    $string .= '<option value="8">August</option>';
    $string .= '<option value="9">September</option>';
    $string .= '<option value="10">October</option>';
    $string .= '<option value="11">November</option>';
    $string .= '<option value="12">December</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form with a string and adds a dropdown with a built-in menu', function () {
    $element = $this->form->create('Month|select(months)');

    $string = '<select name="month" id="month" class="form-control">';
    $string .= '<option value="1">January</option>';
    $string .= '<option value="2">February</option>';
    $string .= '<option value="3">March</option>';
    $string .= '<option value="4">April</option>';
    $string .= '<option value="5">May</option>';
    $string .= '<option value="6">June</option>';
    $string .= '<option value="7">July</option>';
    $string .= '<option value="8">August</option>';
    $string .= '<option value="9">September</option>';
    $string .= '<option value="10">October</option>';
    $string .= '<option value="11">November</option>';
    $string .= '<option value="12">December</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form with a string and adds a dropdown with an array', function () {
    $element = $this->form->create('Month|select([foo:Foo, bar:Bar])');

    $string = '<select name="month" id="month" class="form-control">';
    $string .= '<option value="foo">Foo</option>';
    $string .= '<option value="bar">Bar</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form with a string and adds a dropdown with an array and has a default value', function () {
    $element = $this->form->create('Month{bar}|select([foo:Foo, bar:Bar])');

    $string = '<select name="month" id="month" class="form-control">';
    $string .= '<option value="foo">Foo</option>';
    $string .= '<option value="bar" selected>Bar</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form with a string and adds a dropdown with an array and has a default option', function () {
    $element = $this->form->create('Month|select([foo:Foo, bar:Bar]::Select Something...)');

    $string = '<select name="month" id="month" class="form-control">';
    $string .= '<option value="0">Select Something...</option>';
    $string .= '<option value="foo">Foo</option>';
    $string .= '<option value="bar">Bar</option>';
    $string .= '</select>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with an options array and selects the posted value', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['one'] = 'two';

    $element = $this->form->select('one', 'One', 'one', ['one' => 'One', 'two' => 'Two']);

    $string = '<option value="two" selected>Two</option>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a dropdown with an options array string and shows the posted value', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['one'] = 'two';

    $element = $this->form->select('one', 'One', 'one', '[one:One, two:Two]');

    $string = '<option value="two" selected>Two</option>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});
