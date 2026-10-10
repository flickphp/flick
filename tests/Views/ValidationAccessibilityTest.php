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
    'tailwind' => 'outline-red-500',
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

/**
 * The control's class attribute as whole class names. A substring match is
 * not enough: the tailwind pack keeps `aria-invalid:outline-red-500` on a
 * clean control, which contains the invalid class without being it.
 *
 * @return list<string>
 */
function controlClasses(string $html): array
{
    preg_match('/<(?:input|select|textarea)[^>]*class="([^"]*)"/', $html, $classes);

    return preg_split('/\s+/', $classes[1] ?? '', -1, PREG_SPLIT_NO_EMPTY);
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

            expect(controlClasses($html))->toContain($invalidClass);
        });

        it("does not mark a clean {$field} in the {$pack} pack as invalid", function () use ($pack, $invalidClass, $render) {
            $html = cleanField($pack, $render);

            expect($html)->not->toContain('aria-invalid=')
                ->and($html)->not->toContain('aria-describedby')
                ->and($html)->toContain('style="display:none"')
                ->and($html)->not->toContain('Email is required');

            expect(controlClasses($html))->not->toContain($invalidClass);
        });
    }
}

/*
 * The tailwind pack draws text, textarea and select with a 1px outline and
 * no border, so a border colour on them changes nothing a person can see.
 * The state colour has to be an outline colour, and it hangs off
 * aria-invalid because that is the one thing a PHP failure and Pro's script
 * both set. The variant also outranks the pack's focus colour, so the edge
 * stays red or green while the field has focus.
 *
 * Production change that fails these: putting a border colour back on a
 * control that has no border, or dropping the aria-invalid variants.
 */
foreach (['text', 'textarea', 'select'] as $field) {
    it("colours the outline a failed tailwind {$field} draws", function () use ($fields, $field) {
        $classes = controlClasses(failedField('tailwind', $fields[$field]));

        expect($classes)->toContain('outline-1')
            ->and($classes)->toContain('aria-invalid:outline-red-500')
            ->and($classes)->toContain('aria-[invalid=false]:outline-green-500')
            ->and(array_values(array_filter($classes, fn (string $class): bool => str_contains($class, 'border-'))))->toBe([]);
    });
}

/*
 * A Tailwind build only generates the classes it can read out of the view
 * files (guide/views.md has developers add this directory as a source), and
 * it reads `outline-red-500@enderror` as one unknown class. Written that way
 * the invalid class reached the page and had no CSS behind it.
 *
 * Production change that fails this: closing the gap before @enderror.
 */
foreach (['input', 'textarea', 'select', 'file', 'boolean', 'boolean-inline'] as $view) {
    it("leaves the invalid class readable to a Tailwind build in the tailwind {$view} view", function () use ($view) {
        $source = file_get_contents(dirname(__DIR__, 2)."/resources/views/tailwind/{$view}.view.php");

        expect($source)->toMatch('/@error\s+outline-red-500\s+@enderror/');
    });
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
