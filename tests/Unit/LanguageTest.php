<?php

use Flick\Flick;
use Flick\Http\ArrayRequest;
use Flick\Session\ArraySession;
use Flick\Support\FlickException;

it('does not throw an exception when loading the default English language file', function () {
    expect(function () {
        new Flick([
        ]);
    })->not()->toThrow(RuntimeException::class);
});

it('explains how to fix a missing assets language file', function () {
    $assetsPath = sys_get_temp_dir().'/flick-lang-missing-'.bin2hex(random_bytes(4));
    mkdir($assetsPath);

    try {
        new Flick([
            'lang' => 'de',
            'assets' => $assetsPath,
        ]);
        $this->fail('Expected RuntimeException for the missing language file');
    } catch (RuntimeException $e) {
        expect($e)->toBeInstanceOf(FlickException::class);
        expect($e->getMessage())->toContain($assetsPath.'/lang/de/rules.php');
        expect($e->help)
            ->toContain('vendor/flickphp/flick/lang')
            ->toContain('`lang` key');
    } finally {
        rmdir($assetsPath);
    }
});

it('does not throw an exception when loading a valid user-supplied language file', function () {
    $assetsPath = realpath(__DIR__.'/../test-files/assets');

    expect(function () use ($assetsPath) {
        new Flick([
            'lang' => 'es',
            'assets' => $assetsPath,
        ]);
    })->not()->toThrow(RuntimeException::class);
});

it('keeps languages per instance within one process', function () {
    $assetsPath = realpath(__DIR__.'/../test-files/assets');

    $spanish = new Flick([
        'lang' => 'es',
        'assets' => $assetsPath,
        'request' => ArrayRequest::createPost(['_id' => 'myForm']),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
    ]);
    $spanish->createAndValidate('Name[required]');

    $english = new Flick([
        'request' => ArrayRequest::createPost(['_id' => 'myForm']),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
    ]);
    $english->createAndValidate('Name[required]');

    expect($spanish->getErrors()['name'])->toBe('El campo name es obligatorio')
        ->and($english->getErrors()['name'])->toBe('The name field is required');
});

/*
 * Decided 2026-08-20 (Tim): a translation may be partial. Keys a custom
 * language file leaves out fall back to the shipped English, per key, for
 * both rules.php and messages.php. A missing FILE still throws (decision #7
 * in AUDIT-FOLLOWUPS) - only a missing KEY falls back. Before this, the first
 * read of a missing key was an undefined-index warning and then a TypeError,
 * and the repo's own es fixture (1 of 50 rule keys, 1 of 6 message keys)
 * only survived because no test touched the other 54.
 */
function partialSpanishForm(array $post = []): Flick
{
    return new Flick([
        'lang' => 'es',
        'assets' => realpath(__DIR__.'/../test-files/assets'),
        'request' => ArrayRequest::createPost(['_id' => 'myForm'] + $post),
        'session' => new ArraySession,
        'echo' => false,
        'csrf' => false,
    ]);
}

it('falls back to the shipped English for a rule the translation leaves out', function () {
    $form = partialSpanishForm(['email' => 'nope']);
    $form->createAndValidate('Email|email[email]');

    $english = require __DIR__.'/../../lang/en/rules.php';

    expect($form->getErrors()['email'])->toBe(str_replace(':key', 'email', $english['email']));
});

it('still uses the translated text for a rule the translation does have', function () {
    $form = partialSpanishForm(['name' => '']);
    $form->createAndValidate('Name[required]');

    expect($form->getErrors()['name'])->toBe('El campo name es obligatorio');
});

it('falls back per key for application messages too', function () {
    $messages = partialSpanishForm()->config('applicationMessages');
    $english = require __DIR__.'/../../lang/en/messages.php';

    expect($messages['SessionHasExpired'])->toBe('Tu sesión ha expirado. Por favor, actualiza la página.')
        ->and($messages['InvalidSecurityToken'])->toBe($english['InvalidSecurityToken'])
        ->and(array_keys($messages))->toBe(array_keys($english));
});
