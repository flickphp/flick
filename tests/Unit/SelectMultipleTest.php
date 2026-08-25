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

it('renders a multi-select name with [] appended (H1)', function () {
    $element = $this->form->selectMultiple('skills', 'Skills', '', [
        'php' => 'PHP',
        'js' => 'JavaScript',
        'go' => 'Go',
    ]);

    expect($element)
        ->toContain('name="skills[]"')
        ->toContain('multiple');
});

it('round-trips a multi-value POST to an array through a [] name (H1)', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myForm';
    $_POST['skills'] = ['php', 'js', 'go'];

    $colors = $this->form->request('skills');

    expect($colors)
        ->toBeArray()
        ->toContain('php')
        ->toContain('js')
        ->toContain('go');
});

it('does not fatal when create() builds a selectMultiple element (H2)', function () {
    $element = $this->form->create('Colors|selectMultiple([red:Red, blue:Blue])', ['action' => '/submit']);

    expect($element)
        ->toBeString()
        ->toContain('name="colors[]"')
        ->toContain('multiple');
});
