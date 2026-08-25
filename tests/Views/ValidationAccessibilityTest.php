<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * No documentation page promises these attributes yet. After a PHP
 * validation failure, default views must put aria-invalid and
 * aria-describedby on the control, point describedby at the same
 * has-error-{id} node Pro JS uses (with the message inside that node),
 * and add the pack's invalid class. A clean field must have none of that.
 *
 * Production change that fails these: dropping the aria attributes from
 * Build.php, leaving the PHP message in a second unlabeled sibling, or
 * omitting the pack invalid class from a template.
 */

$packs = [
    'bootstrap' => 'is-invalid',
    'bootstrap4' => 'is-invalid',
    'bulma' => 'is-danger',
    'flick' => 'has-error',
    'foundation' => 'is-invalid-input',
    'materialize' => 'invalid',
    'tailwind' => 'border-red-500',
];

$fields = [
    'text' => fn (Flick $form): string => $form->text('email', 'Email'),
    'textarea' => fn (Flick $form): string => $form->textarea('email', 'Email'),
    'select' => fn (Flick $form): string => $form->select('email', 'Email', '', ['options' => ['a' => 'A']]),
    'file' => fn (Flick $form): string => $form->file('email', 'Email'),
    'checkbox' => fn (Flick $form): string => $form->checkbox('email', 'Email', '1'),
    'checkboxInline' => fn (Flick $form): string => $form->checkboxInline('email', 'Email', '1'),
];

function failedField(string $pack, callable $render): string
{
    $form = new Flick(['csrf' => false, 'echo' => false, 'views' => $pack]);
    $form->addError('email', 'Email is required');

    return $render($form);
}

function cleanField(string $pack, callable $render): string
{
    $form = new Flick(['csrf' => false, 'echo' => false, 'views' => $pack]);

    return $render($form);
}

foreach ($packs as $pack => $invalidClass) {
    foreach ($fields as $field => $render) {
        it("marks a failed {$field} in the {$pack} pack for assistive tech", function () use ($pack, $invalidClass, $render) {
            $html = failedField($pack, $render);

            expect($html)->toContain('aria-invalid="true"')
                ->and($html)->toContain('aria-describedby="has-error-email"')
                ->and($html)->toMatch('/id="has-error-email"[^>]*>\s*Email is required/')
                ->and(substr_count($html, 'id="has-error-email"'))->toBe(1)
                ->and($html)->toContain('style="display:block"')
                ->and($html)->not->toContain('@error');

            preg_match('/<(?:input|select|textarea)[^>]*class="([^"]*)"/', $html, $classes);

            expect($classes[1] ?? '')->toContain($invalidClass);
        });

        it("does not mark a clean {$field} in the {$pack} pack as invalid", function () use ($pack, $invalidClass, $render) {
            $html = cleanField($pack, $render);

            expect($html)->not->toContain('aria-invalid')
                ->and($html)->not->toContain('aria-describedby')
                ->and($html)->toContain('style="display:none"')
                ->and($html)->not->toContain('Email is required');

            preg_match('/<(?:input|select|textarea)[^>]*class="([^"]*)"/', $html, $classes);

            expect($classes[1] ?? '')->not->toContain($invalidClass);
        });
    }
}

it('points aria-describedby at the value-suffixed id on a radio', function () {
    $form = new Flick(['csrf' => false, 'echo' => false, 'views' => 'flick']);
    $form->addError('size', 'Pick a size');

    $html = $form->radioInline('size', 'Small', 's');

    expect($html)->toContain('id="sizeS"')
        ->and($html)->toContain('aria-invalid="true"')
        ->and($html)->toContain('aria-describedby="has-error-sizeS"')
        ->and($html)->toMatch('/id="has-error-sizeS"[^>]*>\s*Pick a size/')
        ->and(substr_count($html, 'id="has-error-sizeS"'))->toBe(1);
});

it('does not copy aria onto a sibling field that has no error', function () {
    $form = new Flick(['csrf' => false, 'echo' => false, 'views' => 'flick']);
    $form->addError('email', 'Email is required');

    $email = $form->email('email', 'Email');
    $name = $form->text('name', 'Name');

    expect($email)->toContain('aria-invalid="true"')
        ->and($name)->not->toContain('aria-invalid')
        ->and($name)->not->toContain('aria-describedby');
});

it('does not emit a second aria-describedby when the developer already set one', function () {
    $form = new Flick(['csrf' => false, 'echo' => false, 'views' => 'flick']);
    $form->addError('email', 'Email is required');

    $html = $form->email('email', 'Email', '', ['aria-describedby' => 'custom-hint']);

    expect(substr_count($html, 'aria-describedby'))->toBe(1)
        ->and($html)->toContain('aria-describedby="custom-hint"')
        ->and($html)->toContain('aria-invalid="true"');
});

it('does not emit a second aria-invalid when the developer already set one', function () {
    $form = new Flick(['csrf' => false, 'echo' => false, 'views' => 'flick']);
    $form->addError('email', 'Email is required');

    $html = $form->email('email', 'Email', '', ['aria-invalid' => 'true']);

    expect(substr_count($html, 'aria-invalid'))->toBe(1)
        ->and($html)->toContain('aria-describedby="has-error-email"');
});
