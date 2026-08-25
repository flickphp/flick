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

it('renders enctype with no custom attributes', function () {
    $element = $this->form->openMultipart('/upload', 'POST');

    expect($element)->toContain('enctype="multipart/form-data"');
});

it('keeps enctype when array attributes are passed', function () {
    $element = $this->form->openMultipart('/upload', 'POST', ['class' => 'wide']);

    expect($element)
        ->toContain('enctype="multipart/form-data"')
        ->toContain('class="wide"');
});

it('keeps enctype when string attributes are passed', function () {
    $element = $this->form->openMultipart('/upload', 'POST', 'class="wide"');

    expect($element)
        ->toContain('enctype="multipart/form-data"')
        ->toContain('class="wide"');
});

it('createMultipart keeps enctype with array attributes', function () {
    $element = $this->form->createMultipart('Name, Photo', ['class' => 'wide']);

    expect($element)
        ->toContain('enctype="multipart/form-data"')
        ->toContain('class="wide"');
});

it('createMultipart keeps enctype with string attributes', function () {
    $element = $this->form->createMultipart('Name, Photo', 'class="wide"');

    expect($element)
        ->toContain('enctype="multipart/form-data"')
        ->toContain('class="wide"');
});
