<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * No Flick documentation page promises this; the expectation is Bulma's own.
 * Bulma colours a select through <div class="select is-danger"> and a file
 * field through <div class="file is-danger">. The class on the <select> or
 * the hidden <input class="file-input"> styles nothing: measured in Chrome
 * against Bulma 1.0.2, the border stayed the default grey with it there.
 *
 * The control keeps the class as well - ValidationAccessibilityTest requires
 * it on the control in every pack.
 *
 * Production change that fails these: taking @error is-danger@enderror off
 * the wrapper in bulma/select.view.php or bulma/file.view.php.
 */

$bulma = fn (): Flick => new Flick(['csrf' => false, 'echo' => false, 'views' => 'bulma']);

$wrapperClasses = function (string $html, string $wrapper): string {
    preg_match('/<div class="('.$wrapper.'\b[^"]*)"/', $html, $matches);

    return $matches[1] ?? '';
};

it('puts the invalid class on the Bulma select wrapper after a failed validation', function () use ($bulma, $wrapperClasses) {
    $form = $bulma();
    $form->addError('color', 'Color is required');

    $html = $form->select('color', 'Color', '', ['options' => ['r' => 'Red']]);

    expect(preg_split('/\s+/', $wrapperClasses($html, 'select')))->toContain('select', 'is-danger');
});

it('keeps is-multiple beside the invalid class on a failed Bulma multiple select', function () use ($bulma, $wrapperClasses) {
    $form = $bulma();
    $form->addError('color', 'Color is required');

    $html = $form->select('color', 'Color', '', ['options' => ['r' => 'Red'], 'multiple' => 'multiple']);

    expect(preg_split('/\s+/', $wrapperClasses($html, 'select')))->toContain('select', 'is-multiple', 'is-danger');
});

it('puts the invalid class on the Bulma file wrapper after a failed validation', function () use ($bulma, $wrapperClasses) {
    $form = $bulma();
    $form->addError('doc', 'Doc is required');

    $html = $form->file('doc', 'Doc');

    expect(preg_split('/\s+/', $wrapperClasses($html, 'file')))->toContain('file', 'is-danger');
});

it('leaves the Bulma select and file wrappers unmarked on a clean field', function () use ($bulma, $wrapperClasses) {
    $select = $bulma()->select('color', 'Color', '', ['options' => ['r' => 'Red']]);
    $file = $bulma()->file('doc', 'Doc');

    expect($wrapperClasses($select, 'select'))->not->toBe('')
        ->and($wrapperClasses($select, 'select'))->not->toContain('is-danger')
        ->and($wrapperClasses($file, 'file'))->not->toBe('')
        ->and($wrapperClasses($file, 'file'))->not->toContain('is-danger');
});
