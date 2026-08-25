<?php

use Flick\Flick;

beforeEach(function () {
    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

it('displays the posted value in an input', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['name'] = 'Flick';
    $_POST['_id'] = 'myForm'; // Add form ID for submission validation
    $element = $this->form->text('name');
    expect($element)->toContain($_POST['name']);
});

it('displays the request value in an input', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['name'] = 'Flick';
    $element = $this->form->text('name');
    expect($element)->toContain($_GET['name']);
});

it('displays the posted value in an textarea', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['name'] = 'Flick';
    $_POST['_id'] = 'myForm'; // Add form ID for submission validation
    $element = $this->form->textarea('name');
    expect($element)->toContain($_POST['name']);
});

it('displays the requested value in an textarea', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['name'] = 'Flick';
    $element = $this->form->textarea('name');
    expect($element)->toContain($_GET['name']);
});

it('displays the posted value in a select menu', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['country'] = 'US';
    $element = $this->form->select('country', '', '', ['US' => 'United States']);
    expect($element)->toContain($_POST['country']);
});

it('displays the requested value in a select menu', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['country'] = 'US';
    $element = $this->form->select('country', '', '', ['US' => 'United States']);
    expect($element)->toContain($_GET['country']);
});

it('retrieves array values from POST for checkbox arrays', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['colors'] = ['red', 'green', 'blue'];
    $_POST['_id'] = 'myForm';

    $colors = $this->form->request('colors');

    expect($colors)->toBeArray();
    expect($colors)->toHaveCount(3);
    expect($colors)->toContain('red');
    expect($colors)->toContain('green');
    expect($colors)->toContain('blue');
});
