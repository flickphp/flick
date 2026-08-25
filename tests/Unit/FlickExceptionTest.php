<?php

declare(strict_types=1);

use Flick\Support\FlickException;

it('is a RuntimeException', function () {
    expect(new FlickException('boom'))->toBeInstanceOf(RuntimeException::class);
});

it('defaults to a Whoops heading and empty extras', function () {
    $e = new FlickException('boom');

    expect($e->heading)->toBe('Whoops')
        ->and($e->help)->toBe('')
        ->and($e->codeSample)->toBe('')
        ->and($e->docsUrl)->toBe('');
});

it('reports the factory call site as its own file and line', function () {
    // frameworks read getFile()/getLine() directly, so the exception itself
    // must carry the caller, not the factory that constructed it
    $line = __LINE__ + 1;
    $e = FlickException::viewFileNotFound('/tmp/nope.view.php');

    expect($e->getFile())->toBe(__FILE__)
        ->and($e->getLine())->toBe($line);
});

it('normalizes parent-directory segments out of message paths', function () {
    $e = FlickException::viewFileNotFound('/src/Views/../../resources/views/flick/nope.view.php');

    expect($e->getMessage())->toContain('/resources/views/flick/nope.view.php')
        ->and($e->getMessage())->not->toContain('..');
});

it('keeps substituted values raw in the message', function () {
    $e = FlickException::viewFileNotFound('/tmp/<script>alert(1)</script>.view.php');

    expect($e->getMessage())->toContain('/tmp/<script>alert(1)</script>.view.php')
        ->and($e->getMessage())->not->toContain('&lt;');
});

it('produces plain-text messages from every factory', function () {
    $exceptions = [
        FlickException::addServiceError('underlying failure'),
        FlickException::alertTypeIsNotAvailable('fancy'),
        FlickException::assetsDirectoryNotFound('/tmp/assets'),
        FlickException::assetsDirectoryNotWritable('/tmp/assets'),
        FlickException::cacheDirectoryCannotBeCreated('/tmp/assets'),
        FlickException::cachingIsDisabled(),
        FlickException::dropdownNotFound('states'),
        FlickException::formNotFound('contact'),
        FlickException::invalidServiceProvider('foo', 'Foo\\FooServiceProvider'),
        FlickException::languageFileNotFound('/tmp/lang/en.php'),
        FlickException::missingCsrfSessionStart(),
        FlickException::missingDropdownOptions(),
        FlickException::missingDropdownOptionsInsideParentheses(),
        FlickException::missingFieldKey('Email'),
        FlickException::missingOptions('states'),
        FlickException::serviceIsNotAvailable('mail'),
        FlickException::sessionIsRequired(),
        FlickException::viewCacheDirectoryNotWritable('/tmp/cache'),
        FlickException::viewCacheFileNotWritable('/tmp/cache', 'input.view.php'),
        FlickException::viewFileNotFound('/tmp/nope.view.php'),
        FlickException::viewPathIsNotDefined('/tmp/views'),
    ];

    foreach ($exceptions as $e) {
        expect($e->getMessage())->not->toMatch('/<[a-z]/i')
            ->and($e->help)->not->toMatch('/<[a-z]/i')
            ->and($e->heading)->not->toMatch('/<[a-z]/i');
    }
});

it('gives a missing view its heading and path', function () {
    $e = FlickException::viewFileNotFound('/tmp/nope.view.php');

    expect($e->heading)->toBe('View file not found')
        ->and($e->getMessage())->toContain('/tmp/nope.view.php');
});

it('gives an unavailable service its help, code sample and docs link', function () {
    $e = FlickException::serviceIsNotAvailable('mail');

    expect($e->getMessage())->toContain('mail')
        ->and($e->help)->not->toBe('')
        ->and($e->codeSample)->toContain("hasService('mail')")
        ->and($e->docsUrl)->toContain('flickphp.com');
});

it('gives the caching-disabled error its code sample', function () {
    $e = FlickException::cachingIsDisabled();

    expect($e->codeSample)->toContain("'assets'");
});
