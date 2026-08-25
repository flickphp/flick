<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * The superglobal readers on the standalone request adapter.
 *
 * These are the accessors a plain-PHP (non-Laravel) install runs through on
 * every request. They were at zero coverage, which meant nothing caught a
 * header lookup silently returning null or `has()` missing GET data.
 *
 * Spec: websites/flickphp.test/resources/docs/guide/adapters.md documents
 * RequestInterface as the seam these implement.
 */
beforeEach(function () {
    $this->getBackup = $_GET;
    $this->postBackup = $_POST;
    $this->serverBackup = $_SERVER;
    $this->filesBackup = $_FILES;
    $this->envBackup = $_ENV;

    $_GET = [];
    $_POST = [];
    $_FILES = [];
});

afterEach(function () {
    $_GET = $this->getBackup;
    $_POST = $this->postBackup;
    $_SERVER = $this->serverBackup;
    $_FILES = $this->filesBackup;
    $_ENV = $this->envBackup;
});

/*
|--------------------------------------------------------------------------
| all() and has()
|--------------------------------------------------------------------------
*/

it('merges GET and POST data', function () {
    $_GET = ['page' => '2'];
    $_POST = ['name' => 'Ada'];

    expect((new NativeRequest)->all())->toBe(['page' => '2', 'name' => 'Ada']);
});

it('lets POST win when both carry the same key', function () {
    $_GET = ['id' => 'from-query'];
    $_POST = ['id' => 'from-body'];

    expect((new NativeRequest)->all()['id'])->toBe('from-body');
});

it('returns an empty array when nothing was submitted', function () {
    expect((new NativeRequest)->all())->toBe([]);
});

it('finds a key in POST', function () {
    $_POST = ['name' => 'Ada'];

    expect((new NativeRequest)->has('name'))->toBeTrue();
});

it('finds a key in GET', function () {
    $_GET = ['page' => '2'];

    expect((new NativeRequest)->has('page'))->toBeTrue();
});

it('reports a missing key as absent', function () {
    expect((new NativeRequest)->has('nope'))->toBeFalse();
});

it('counts a submitted-but-empty field as present', function () {
    // An unchecked box or a cleared text input still posts the key; treating it
    // as missing would make "was this field submitted?" unanswerable.
    $_POST = ['nickname' => ''];

    expect((new NativeRequest)->has('nickname'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| file()
|--------------------------------------------------------------------------
*/

it('returns the raw upload entry for a field', function () {
    $_FILES['avatar'] = [
        'name' => 'avatar.png',
        'type' => 'image/png',
        'tmp_name' => '/tmp/php123',
        'error' => UPLOAD_ERR_OK,
        'size' => 1024,
    ];

    expect((new NativeRequest)->file('avatar'))->toBe($_FILES['avatar']);
});

it('returns null for a field with no upload', function () {
    expect((new NativeRequest)->file('avatar'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| server()
|--------------------------------------------------------------------------
*/

it('reads a server value', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';

    expect((new NativeRequest)->server('REQUEST_METHOD'))->toBe('POST');
});

it('falls back to the default for an unknown server key', function () {
    unset($_SERVER['NOT_SET_ANYWHERE']);

    expect((new NativeRequest)->server('NOT_SET_ANYWHERE', 'fallback'))->toBe('fallback');
});

/*
|--------------------------------------------------------------------------
| header()
|--------------------------------------------------------------------------
*/

it('reads a header through its HTTP_ server key', function () {
    $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'value';

    expect((new NativeRequest)->header('X-Custom-Header'))->toBe('value');
});

it('treats header names case-insensitively', function () {
    $_SERVER['HTTP_ACCEPT'] = 'application/json';

    expect((new NativeRequest)->header('accept'))->toBe('application/json');
});

it('reads Content-Type from its unprefixed server key', function () {
    // PHP puts these two in $_SERVER without the HTTP_ prefix.
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    unset($_SERVER['HTTP_CONTENT_TYPE']);

    expect((new NativeRequest)->header('Content-Type'))->toBe('application/json');
});

it('reads Content-Length from its unprefixed server key', function () {
    $_SERVER['CONTENT_LENGTH'] = '348';
    unset($_SERVER['HTTP_CONTENT_LENGTH']);

    expect((new NativeRequest)->header('Content-Length'))->toBe('348');
});

it('falls back to the default for a header that was not sent', function () {
    unset($_SERVER['HTTP_X_NOPE']);

    expect((new NativeRequest)->header('X-Nope', 'fallback'))->toBe('fallback');
});

/*
|--------------------------------------------------------------------------
| isAjax()
|--------------------------------------------------------------------------
*/

it('detects an ajax request from the requested-with header', function () {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

    expect((new NativeRequest)->isAjax())->toBeTrue();
});

it('detects an ajax request whatever the header casing', function () {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

    expect((new NativeRequest)->isAjax())->toBeTrue();
});

it('does not call an ordinary request ajax', function () {
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);

    expect((new NativeRequest)->isAjax())->toBeFalse();
});

it('ignores a requested-with header naming something else', function () {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'SomethingElse';

    expect((new NativeRequest)->isAjax())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| env()
|--------------------------------------------------------------------------
*/

it('reads an environment value from $_ENV first', function () {
    $_ENV['FLICK_TEST_VALUE'] = 'from-env';
    $_SERVER['FLICK_TEST_VALUE'] = 'from-server';

    expect((new NativeRequest)->env('FLICK_TEST_VALUE'))->toBe('from-env');
});

it('falls back to $_SERVER when $_ENV has nothing', function () {
    unset($_ENV['FLICK_TEST_VALUE']);
    $_SERVER['FLICK_TEST_VALUE'] = 'from-server';

    expect((new NativeRequest)->env('FLICK_TEST_VALUE'))->toBe('from-server');
});

it('falls back to the default when the variable is set nowhere', function () {
    unset($_ENV['FLICK_DEFINITELY_UNSET'], $_SERVER['FLICK_DEFINITELY_UNSET']);

    expect((new NativeRequest)->env('FLICK_DEFINITELY_UNSET', 'fallback'))->toBe('fallback');
});

/*
|--------------------------------------------------------------------------
| hasFile()
|--------------------------------------------------------------------------
| Audit 2026-08-16, F4-A: this logic existed byte-identically in both request
| adapters but only ArrayRequest's copy was tested — a divergence here would
| have passed CI. These mirror ArrayRequestTest's cases against real $_FILES.
*/

it('checks if a file was uploaded', function () {
    $_FILES['uploaded'] = ['name' => 'file.txt', 'tmp_name' => '/tmp/x', 'size' => 100, 'error' => UPLOAD_ERR_OK];
    $_FILES['no_file'] = ['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE];

    $request = new NativeRequest;

    expect($request->hasFile('uploaded'))->toBeTrue()
        ->and($request->hasFile('no_file'))->toBeFalse()
        ->and($request->hasFile('missing'))->toBeFalse();
});

it('reports no file for an all-empty array-shaped upload', function () {
    $_FILES['photos'] = [
        'name' => ['', ''],
        'tmp_name' => ['', ''],
        'size' => [0, 0],
        'error' => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_FILE],
    ];

    expect((new NativeRequest)->hasFile('photos'))->toBeFalse();
});

it('reports a file when one slot of an array-shaped upload is populated', function () {
    $_FILES['photos'] = [
        'name' => ['', 'b.jpg'],
        'tmp_name' => ['', '/tmp/b'],
        'size' => [0, 200],
        'error' => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
    ];

    expect((new NativeRequest)->hasFile('photos'))->toBeTrue();
});
