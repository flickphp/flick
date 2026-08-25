<?php

test('no debugging statements')
    ->arch()
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed()
    ->ignoring('Flick\Support\Helpers'); // Intentional dd() helper for users

// One expectation over the whole namespace, rather than ten hand-listed
// sub-namespaces that between them missed src/Flick.php - the largest file in
// the package. A new directory is covered the day it is added.
test('classes use strict types')
    ->arch()
    ->expect('Flick')
    ->toUseStrictTypes();

test('exit/die only in Response send methods and dd helper')
    ->arch()
    ->expect(['exit', 'die'])
    ->not->toBeUsed()
    ->ignoring('Flick\Http\RedirectResponse')  // exit in send()
    ->ignoring('Flick\Http\JsonResponse')      // exit in send()
    ->ignoring('Flick\Http\HtmlResponse')      // exit in send()
    ->ignoring('Flick\Http\ResponseHandlers')  // default handlers use exit
    ->ignoring('Flick\Support\Helpers');       // Intentional dd() helper

test('session_start only in NativeSession')
    ->arch()
    ->expect(['session_start'])
    ->not->toBeUsed()
    ->ignoring('Flick\Session\NativeSession');

test('session_regenerate_id only in NativeSession')
    ->arch()
    ->expect(['session_regenerate_id'])
    ->not->toBeUsed()
    ->ignoring('Flick\Session\NativeSession');

test('session_status only in NativeSession')
    ->arch()
    ->expect(['session_status'])
    ->not->toBeUsed()
    ->ignoring('Flick\Session\NativeSession');

test('session_set_cookie_params only in NativeSession')
    ->arch()
    ->expect(['session_set_cookie_params'])
    ->not->toBeUsed()
    ->ignoring('Flick\Session\NativeSession');

test('setcookie only in NativeRequest')
    ->arch()
    ->expect(['setcookie'])
    ->not->toBeUsed()
    ->ignoring('Flick\Http\NativeRequest');
