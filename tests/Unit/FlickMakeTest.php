<?php

use Flick\Flick;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

test('make() creates a new instance', function () {
    $form = Flick::make(['csrf' => false, 'echo' => false]);

    expect($form)->toBeInstanceOf(Flick::class);
});

test('make() works with no arguments', function () {
    $form = Flick::make();

    expect($form)->toBeInstanceOf(Flick::class);
});

test('make() accepts string config for views', function () {
    $form = Flick::make('bootstrap');

    expect($form)->toBeInstanceOf(Flick::class)
        ->and($form->config('views'))->toBe('bootstrap');
});

test('make() produces equivalent result to constructor', function () {
    $config = ['csrf' => false, 'echo' => false, 'id' => 'testForm'];

    $formMake = Flick::make($config);
    $formNew = new Flick($config);

    expect($formMake)->toBeInstanceOf(Flick::class)
        ->and($formNew)->toBeInstanceOf(Flick::class)
        ->and($formMake->config('id'))->toBe($formNew->config('id'))
        ->and($formMake->config('echo'))->toBe($formNew->config('echo'));
});
