<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Session\ArraySession;

beforeEach(function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_id' => 'myForm'];
    $_GET = [];
});

afterEach(function () {
    $_POST = [];
    $_GET = [];
});

// Bug #11a — injecting a session ADAPTER must not switch on field persistence;
// the truthy adapter object made config('session') read as "persist everything".
it('does not persist validated fields just because a session adapter is injected (#11)', function () {
    $session = new ArraySession;
    $form = new Flick(['session' => $session, 'csrf' => false, 'echo' => false]);

    $_POST['password'] = 'hunter2';
    $form->request('password', 'required');

    expect($session->hasValue('password'))->toBeFalse();
});

it('persists validated fields when persistToSession is enabled (#11)', function () {
    $session = new ArraySession;
    $form = new Flick([
        'session' => $session,
        'persistToSession' => true,
        'csrf' => false,
        'echo' => false,
    ]);

    $_POST['nickname'] = 'timbo';
    $form->request('nickname', 'required');

    expect($session->getValue('nickname'))->toBe('timbo');
});

// Bug #11b — an abandoned multistep flow's persistence flag is scoped to that
// form's id; it must not leak persistence into an unrelated form.
it('does not persist an unrelated form because another form left a persistence flag (#11)', function () {
    $session = new ArraySession;
    $session->setValue('_persist_wizard', true); // leftover from an abandoned multistep 'wizard'

    $form = new Flick(['session' => $session, 'csrf' => false, 'echo' => false]);

    $_POST['password'] = 'hunter2';
    $form->request('password', 'required');

    expect($session->hasValue('password'))->toBeFalse();
});

it('persists fields for the form whose own multistep flag is set (#11)', function () {
    $session = new ArraySession;
    $session->setValue('_persist_myForm', true);

    $form = new Flick(['session' => $session, 'csrf' => false, 'echo' => false]);

    $_POST['city'] = 'Austin';
    $form->request('city', 'required');

    expect($session->getValue('city'))->toBe('Austin');
});

it('createMultistep sets a persistence flag scoped to the form id, not a bare session flag (#11)', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];

    $session = new ArraySession;
    $session->start();
    $form = new Flick(['session' => $session, 'csrf' => false, 'echo' => false]);

    $steps = ['Step One' => ['fields' => ['name' => ['type' => 'text', 'label' => 'Name', 'name' => 'name']]]];
    $form->createMultistep($steps, ['auto' => true]);

    expect($session->hasValue('_persist_myForm'))->toBeTrue()
        ->and($session->hasValue('session'))->toBeFalse();
});

// render side: repopulation from the session follows the same switch
it('repopulates a rendered field from the session only when persistence is on (#11)', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];

    $session = new ArraySession;
    $session->setValue('city', 'Austin');

    $plain = new Flick(['session' => $session, 'csrf' => false, 'echo' => false]);
    expect($plain->text('city', 'City'))->not->toContain('value="Austin"');

    $persisting = new Flick([
        'session' => $session,
        'persistToSession' => true,
        'csrf' => false,
        'echo' => false,
    ]);
    expect($persisting->text('city', 'City'))->toContain('value="Austin"');
});
