<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
        'assets' => __DIR__.'/../test-files/assets',
        'action' => '/submit',
    ]);
});

it('creates a form from a string', function () {
    $element = $this->form->create('Name,Comments|textarea,Age|number', ['action' => '/submit']);

    $string = '<form action="/submit" method="POST" id="myForm">';
    $string .= '<input type="hidden" name="_id" value="myForm">';
    $string .= '<div class="my-4">';
    $string .= '    <label for="name" class="form-label">Name</label>';
    $string .= '        <input type="text" name="name" id="name" value="" class="form-control">';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <label for="comments" class="form-label">Comments</label>';
    $string .= '        <textarea name="comments" id="comments" class="form-control"></textarea>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <label for="age" class="form-label">Age</label>';
    $string .= '        <input type="number" name="age" id="age" value="" class="form-control">';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <button type="submit" id="submit" class="btn btn-primary">Submit</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form from a string and uses overrides', function () {
    $element = $this->form->create('Name, Comments|textarea, Age|number', [
        'id' => 'myFlickForm',
        'button' => 'Submit Me',
        'action' => '/submit',
    ]);

    $string = '<form action="/submit" method="POST" id="myFlickForm">';
    $string .= '<input type="hidden" name="_id" value="myFlickForm">';
    $string .= '<div class="my-4">';
    $string .= '    <label for="name" class="form-label">Name</label>';
    $string .= '        <input type="text" name="name" id="name" value="" class="form-control">';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <label for="comments" class="form-label">Comments</label>';
    $string .= '        <textarea name="comments" id="comments" class="form-control"></textarea>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <label for="age" class="form-label">Age</label>';
    $string .= '        <input type="number" name="age" id="age" value="" class="form-control">';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <button type="submit" id="submit" class="btn btn-primary">Submit Me</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form from a string and adds a dropdown menu from a file', function () {
    $element = $this->form->create('Month|select(months)', ['action' => '/submit']);

    $string = '<form action="/submit" method="POST" id="myForm">';
    $string .= '<input type="hidden" name="_id" value="myForm">';
    $string .= '<div class="my-4">';
    $string .= '    <label for="month" class="form-label">Month</label>';
    $string .= '    <select name="month" id="month" class="form-control">';
    $string .= '        <option value="1">January</option>';
    $string .= '        <option value="2">February</option>';
    $string .= '        <option value="3">March</option>';
    $string .= '        <option value="4">April</option>';
    $string .= '        <option value="5">May</option>';
    $string .= '        <option value="6">June</option>';
    $string .= '        <option value="7">July</option>';
    $string .= '        <option value="8">August</option>';
    $string .= '        <option value="9">September</option>';
    $string .= '        <option value="10">October</option>';
    $string .= '        <option value="11">November</option>';
    $string .= '        <option value="12">December</option>';
    $string .= '    </select>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <button type="submit" id="submit" class="btn btn-primary">Submit</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form from a string and adds a dropdown menu', function () {
    $element = $this->form->create('Foo|select([one:One, two:Two])', ['action' => '/submit']);

    $string = '<form action="/submit" method="POST" id="myForm">';
    $string .= '<input type="hidden" name="_id" value="myForm">';
    $string .= '<div class="my-4">';
    $string .= '    <label for="foo" class="form-label">Foo</label>';
    $string .= '    <select name="foo" id="foo" class="form-control">';
    $string .= '        <option value="one">One</option>';
    $string .= '        <option value="two">Two</option>';
    $string .= '    </select>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <button type="submit" id="submit" class="btn btn-primary">Submit</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form from a string and adds a dropdown menu with a default value', function () {
    $element = $this->form->create('Foo|select([one:One, two:Two]::Select Something)[r]', ['action' => '/submit']);

    $string = '<form action="/submit" method="POST" id="myForm">';
    $string .= '<input type="hidden" name="_id" value="myForm">';
    $string .= '<div class="my-4">';
    $string .= '    <label for="foo" class="form-label">Foo</label>';
    $string .= '    <select name="foo" id="foo" class="form-control" required>';
    $string .= '        <option value="0">Select Something</option>';
    $string .= '        <option value="one">One</option>';
    $string .= '        <option value="two">Two</option>';
    $string .= '    </select>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <button type="submit" id="submit" class="btn btn-primary">Submit</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form from a string and adds a validation rule', function () {
    $element = $this->form->create('Foo[r]', ['action' => '/submit']);

    $string = '<form action="/submit" method="POST" id="myForm">';
    $string .= '<input type="hidden" name="_id" value="myForm">';
    $string .= '<div class="my-4">';
    $string .= '    <label for="foo" class="form-label">Foo</label>';
    $string .= '    <input type="text" name="foo" id="foo" value="" class="form-control" required>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <button type="submit" id="submit" class="btn btn-primary">Submit</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form from an array', function () {
    $array = [
        'action' => '/submit',
        'fields' => [
            [
                'type' => 'text',
                'name' => 'name',
                'label' => 'Name',
            ],
            [
                'type' => 'textarea',
                'name' => 'comments',
                'label' => 'Comments',
            ],
        ],
    ];

    $element = $this->form->create($array);

    $string = '<form action="/submit" method="POST" id="myForm">';
    $string .= '<input type="hidden" name="_id" value="myForm">';
    $string .= '<div class="my-4">';
    $string .= '    <label for="name" class="form-label">Name</label>';
    $string .= '    <input type="text" name="name" id="name" value="" class="form-control">';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <label for="comments" class="form-label">Comments</label>';
    $string .= '    <textarea name="comments" id="comments" class="form-control"></textarea>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '    <button type="submit" id="submit" class="btn btn-primary">Submit</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('creates a form from a file', function () {
    $element = $this->form->create('/myform');

    $string = '<form action="myform.php" method="POST" id="myForm">';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('changes the form method to GET', function () {
    $element = $this->form->create('Name', 'GET');

    $string = 'method="GET"';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});
