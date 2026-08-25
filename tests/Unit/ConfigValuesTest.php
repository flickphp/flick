<?php

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_id'] = 'myFlickForm'; // Add form ID for submission validation

    $config = [
        'csrf' => false,
        'echo' => false,
        'id' => 'myFlickForm',
        'assets' => __DIR__.'/../test-files/assets',
        'rules' => [
            'name' => ['min:5', 'max:10'],
            'comments' => ['min:10'],
        ],
        'messages' => [
            'name' => [
                'min' => 'must be at least 5',
                'max' => 'cannot be more than 10',
            ],
            'comments' => [
                'min' => 'must be at least 10',
            ],
        ],
    ];

    $this->form = new Flick($config);
});

it('creates a form from a string with available config values', function () {
    $element = $this->form->create('Name{gern}, Select|select(foo)', ['action' => '/submit']);

    $string = '<form action="/submit" method="POST" id="myFlickForm">';
    $string .= '<input type="hidden" name="_id" value="myFlickForm">';
    $string .= '<div class="my-4">';
    $string .= '<label for="name" class="form-label">Name</label>';
    $string .= '<input type="text" name="name" id="name" value="gern" class="form-control">';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '<label for="select" class="form-label">Select</label>';
    $string .= '<select name="select" id="select" class="form-control">';
    $string .= '<optgroup label="foobar"><option value="foo">Foo</option>';
    $string .= '<option value="bar">Bar</option>';
    $string .= '</optgroup><optgroup label="foobaz"><option value="baz">Baz</option>';
    $string .= '<option value="barbaz">Barbaz</option>';
    $string .= '</optgroup>';
    $string .= '</select>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '<button type="submit" id="submit" class="btn btn-primary">Submit</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('loads a form from a file with available config values', function () {
    $element = $this->form->create('/myform');

    $string = '<form action="myform.php" method="POST" id="myForm">';
    $string .= '<input type="hidden" name="_id" value="myForm">';
    $string .= '<div class="my-4">';
    $string .= '<label for="username" class="form-label">USERNAME</label>';
    $string .= '<input type="text" name="username" id="username" value="" class="form-control "  required>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '<label for="password" class="form-label">PASSWORD</label>';
    $string .= '<input type="password" name="password" id="password" value="" class="form-control "  required>';
    $string .= '</div>';
    $string .= '<div class="my-4">';
    $string .= '<button type="submit" id="submit" class="btn btn-primary">Foo</button>';
    $string .= '</div>';
    $string .= '</form>';

    $element = preg_replace('/\s+/', '', $element);
    $string = preg_replace('/\s+/', '', $string);

    expect($element)->toBeString()->toContain($string);
});

it('applies global min validation rule for name field', function () {
    $_POST['name'] = 'John';
    $this->form->request('name');

    expect($this->form->hasError('name'))->toBeTrue()
        ->and($this->form->getError('name'))->toBe('must be at least 5');
});

it('applies global max validation rule for name field', function () {
    $_POST['name'] = 'John Doe Smith';
    $this->form->request('name');

    expect($this->form->hasError('name'))->toBeTrue()
        ->and($this->form->getError('name'))->toBe('cannot be more than 10');
});

it('applies global min validation rule for comments field', function () {
    $_POST['comments'] = 'Short';
    $this->form->request('comments');

    expect($this->form->hasError('comments'))->toBeTrue()
        ->and($this->form->getError('comments'))->toBe('must be at least 10');
});

it('passes global validation when all rules are satisfied', function () {
    $_POST['name'] = 'John Doe';
    $_POST['comments'] = 'This is a valid comment';
    $this->form->request('Name, Comments');

    expect($this->form->ok())->toBeTrue()
        ->and($this->form->getErrors())->toBeEmpty();
});

it('overrides global rules with element-specific rules', function () {
    $_POST['name'] = 'John Doe Smith';
    $this->form->request('name', ['max:15']);

    expect($this->form->ok())->toBeTrue()
        ->and($this->form->getErrors())->toBeEmpty();
});

it('keeps live collaborators out of the config bag', function () {
    $request = new ArrayRequest([
        'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
    ]);
    $session = new ArraySession;

    $form = new Flick([
        'request' => $request,
        'session' => $session,
        'onJson' => fn () => null,
        'echo' => false,
        'csrf' => false,
    ]);

    // the adapters are resolved onto typed properties; the settings bag
    // holds only scalars and plain arrays
    expect($form->config('request'))->toBeNull()
        ->and($form->config('session'))->toBeNull()
        ->and($form->config('onJson'))->toBeNull()
        ->and($form->session)->toBe($session)
        ->and($form->persistingToSession())->toBeFalse();
});
