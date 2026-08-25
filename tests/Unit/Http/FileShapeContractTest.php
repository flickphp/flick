<?php

declare(strict_types=1);

use Flick\Http\ArrayRequest;

/*
 * The $_FILES shape contract that RequestInterface's file()/files()/hasFile()
 * docblocks now declare.
 *
 * It was previously written down nowhere: the three implementations agreed only
 * because each had been fixed to, twice (the Laravel adapter's nested hasFile,
 * the upload service's sparse keys). These pin the rules the docblocks state, so
 * a fourth implementation has something to conform to.
 */

it('returns the five $_FILES keys for a single upload', function () {
    $request = new ArrayRequest(['files' => [
        'avatar' => ['name' => 'a.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/a', 'error' => UPLOAD_ERR_OK, 'size' => 10],
    ]]);

    expect(array_keys($request->file('avatar')))
        ->toBe(['name', 'type', 'tmp_name', 'error', 'size']);
});

it('returns null for a key that was not submitted', function () {
    expect((new ArrayRequest(['files' => []]))->file('nope'))->toBeNull();
});

it('keeps parallel arrays with the input nesting, not per-file records', function () {
    $request = new ArrayRequest(['files' => [
        'docs' => [
            'name' => ['group' => ['item' => 'n.pdf']],
            'type' => ['group' => ['item' => 'application/pdf']],
            'tmp_name' => ['group' => ['item' => '/tmp/n']],
            'error' => ['group' => ['item' => UPLOAD_ERR_OK]],
            'size' => ['group' => ['item' => 10]],
        ],
    ]]);

    // Five top-level keys, each carrying the SAME nesting - not a list of files.
    expect($request->file('docs')['name']['group']['item'])->toBe('n.pdf')
        ->and($request->file('docs')['error']['group']['item'])->toBe(UPLOAD_ERR_OK);
});

it('preserves sparse keys instead of packing them into a list', function () {
    $request = new ArrayRequest(['files' => [
        'docs' => [
            'name' => [0 => 'a.pdf', 2 => 'c.pdf'],
            'type' => [0 => 'application/pdf', 2 => 'application/pdf'],
            'tmp_name' => [0 => '/tmp/a', 2 => '/tmp/c'],
            'error' => [0 => UPLOAD_ERR_OK, 2 => UPLOAD_ERR_OK],
            'size' => [0 => 1, 2 => 3],
        ],
    ]]);

    expect(array_keys($request->file('docs')['name']))->toBe([0, 2]);
});

it('reports a failed upload as present, because something was submitted', function () {
    $request = new ArrayRequest(['files' => [
        'avatar' => ['name' => 'big.jpg', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0],
    ]]);

    // Not "no file": the caller has to be able to report "too large".
    expect($request->hasFile('avatar'))->toBeTrue();
});

it('reports an entirely empty field as absent', function () {
    $request = new ArrayRequest(['files' => [
        'avatar' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
    ]]);

    expect($request->hasFile('avatar'))->toBeFalse();
});

it('reports an array field as present when any one slot holds an upload', function () {
    $request = new ArrayRequest(['files' => [
        'docs' => [
            'name' => ['', 'b.pdf'],
            'type' => ['', 'application/pdf'],
            'tmp_name' => ['', '/tmp/b'],
            'error' => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
            'size' => [0, 2],
        ],
    ]]);

    expect($request->hasFile('docs'))->toBeTrue();
});

it('reports an array field as absent when every slot is empty', function () {
    $request = new ArrayRequest(['files' => [
        'docs' => [
            'name' => ['', ''],
            'type' => ['', ''],
            'tmp_name' => ['', ''],
            'error' => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_FILE],
            'size' => [0, 0],
        ],
    ]]);

    expect($request->hasFile('docs'))->toBeFalse();
});

/*
 * Audit-081702 F05-A. file() has always carried whatever nesting the input name
 * had - files[group][item] included, which the interface documents and the
 * parallel-arrays test above pins. hasUpload() only ever walked ONE level, then
 * compared each slot to UPLOAD_ERR_NO_FILE. At depth 2 that comparison is
 * array !== int, which is always true, so an untouched nested file field
 * reported as PRESENT and Validate::form() sent it down the upload path.
 *
 * The Laravel adapter already recurses and already pins both cases; core's
 * "one reading" never grew to the tree its own interface describes.
 */
it('reports a nested field as present when a nested slot holds an upload', function () {
    $request = new ArrayRequest(['files' => [
        'docs' => [
            'name' => ['group' => ['item' => 'n.pdf']],
            'type' => ['group' => ['item' => 'application/pdf']],
            'tmp_name' => ['group' => ['item' => '/tmp/n']],
            'error' => ['group' => ['item' => UPLOAD_ERR_OK]],
            'size' => ['group' => ['item' => 10]],
        ],
    ]]);

    expect($request->hasFile('docs'))->toBeTrue();
});

it('reports a nested field with nothing chosen as absent', function () {
    $request = new ArrayRequest(['files' => [
        'docs' => [
            'name' => ['group' => ['item' => '']],
            'type' => ['group' => ['item' => '']],
            'tmp_name' => ['group' => ['item' => '']],
            'error' => ['group' => ['item' => UPLOAD_ERR_NO_FILE]],
            'size' => ['group' => ['item' => 0]],
        ],
    ]]);

    expect($request->hasFile('docs'))->toBeFalse();
});

it('reports a failed nested upload as present, same as a scalar one', function () {
    $request = new ArrayRequest(['files' => [
        'docs' => [
            'name' => ['group' => ['item' => 'big.pdf']],
            'type' => ['group' => ['item' => '']],
            'tmp_name' => ['group' => ['item' => '']],
            'error' => ['group' => ['item' => UPLOAD_ERR_INI_SIZE]],
            'size' => ['group' => ['item' => 0]],
        ],
    ]]);

    expect($request->hasFile('docs'))->toBeTrue();
});

it('reports a mixed-depth field as present when only the deep slot holds an upload', function () {
    $request = new ArrayRequest(['files' => [
        'docs' => [
            'name' => ['', 'group' => ['item' => 'n.pdf']],
            'type' => ['', 'group' => ['item' => 'application/pdf']],
            'tmp_name' => ['', 'group' => ['item' => '/tmp/n']],
            'error' => [UPLOAD_ERR_NO_FILE, 'group' => ['item' => UPLOAD_ERR_OK]],
            'size' => [0, 'group' => ['item' => 10]],
        ],
    ]]);

    expect($request->hasFile('docs'))->toBeTrue();
});
