<?php

/**
 * Tests to ensure all framework templates are complete and valid.
 * We do the hard work here so developers never hit a bug.
 */

namespace Flick\Tests\Unit;

$frameworks = ['flick', 'bootstrap', 'bootstrap4', 'bulma', 'foundation', 'materialize', 'tailwind'];

$requiredFiles = [
    'input.view.php',
    'select.view.php',
    'textarea.view.php',
    'boolean.view.php',
    'boolean-inline.view.php',
    'file.view.php',
    'hidden.view.php',
    'submit.view.php',
    'breadcrumbs.view.php',
    'alerts/error.view.php',
    'alerts/success.view.php',
    'alerts/warning.view.php',
    'alerts/info.view.php',
];

describe('Framework Templates - File Existence', function () use ($frameworks, $requiredFiles) {

    foreach ($frameworks as $framework) {
        foreach ($requiredFiles as $file) {
            test("{$framework}/{$file} exists", function () use ($framework, $file) {
                $path = __DIR__."/../../resources/views/{$framework}/{$file}";
                expect(file_exists($path))->toBeTrue();
            });
        }
    }

});

describe('Framework Templates - Input Placeholders', function () use ($frameworks) {

    foreach ($frameworks as $framework) {
        test("{$framework}/input.view.php has required placeholders", function () use ($framework) {
            $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/input.view.php");

            expect($content)->toContain('{{ name }}');
            expect($content)->toContain('{{ id }}');
            expect($content)->toContain('{{ type }}');
            expect($content)->toContain('{{ value }}');
            expect($content)->toContain('{{ classes }}');
            expect($content)->toContain('{{attributes}}');
        });
    }

});

describe('Framework Templates - Select Placeholders', function () use ($frameworks) {

    foreach ($frameworks as $framework) {
        test("{$framework}/select.view.php has required placeholders", function () use ($framework) {
            $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/select.view.php");

            expect($content)->toContain('{{ name }}');
            expect($content)->toContain('{{ id }}');
            expect($content)->toContain('{{ options }}');
            expect($content)->toContain('{{ classes }}');
            expect($content)->toContain('{{attributes}}');
        });
    }

});

describe('Framework Templates - Textarea Placeholders', function () use ($frameworks) {

    foreach ($frameworks as $framework) {
        test("{$framework}/textarea.view.php has required placeholders", function () use ($framework) {
            $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/textarea.view.php");

            expect($content)->toContain('{{ name }}');
            expect($content)->toContain('{{ id }}');
            expect($content)->toContain('{{ value }}');
            expect($content)->toContain('{{ classes }}');
            expect($content)->toContain('{{attributes}}');
        });
    }

});

describe('Framework Templates - Boolean Placeholders', function () use ($frameworks) {

    foreach ($frameworks as $framework) {
        test("{$framework}/boolean.view.php has required placeholders", function () use ($framework) {
            $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/boolean.view.php");

            expect($content)->toContain('{{ name }}');
            expect($content)->toContain('{{ id }}');
            expect($content)->toContain('{{ type }}');
            expect($content)->toContain('{{ value }}');
            expect($content)->toContain('{{attributes}}');
        });
    }

});

describe('Framework Templates - Hidden Placeholders', function () use ($frameworks) {

    foreach ($frameworks as $framework) {
        test("{$framework}/hidden.view.php has required placeholders", function () use ($framework) {
            $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/hidden.view.php");

            expect($content)->toContain('{{ name }}');
            expect($content)->toContain('{{ id }}');
            expect($content)->toContain('{{ value }}');
            expect($content)->toContain('{{attributes}}');
        });
    }

});

describe('Framework Templates - Submit Placeholders', function () use ($frameworks) {

    foreach ($frameworks as $framework) {
        test("{$framework}/submit.view.php has required placeholders", function () use ($framework) {
            $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/submit.view.php");

            expect($content)->toContain('{{ id }}');
            expect($content)->toContain('{{ label }}');
            expect($content)->toContain('{{ classes }}');
            expect($content)->toContain('{{attributes}}');
        });
    }

});

describe('Framework Templates - File Placeholders', function () use ($frameworks) {

    foreach ($frameworks as $framework) {
        test("{$framework}/file.view.php has required placeholders", function () use ($framework) {
            $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/file.view.php");

            expect($content)->toContain('{{ name }}');
            expect($content)->toContain('{{ id }}');
            expect($content)->toContain('{{attributes}}');
        });
    }

});

describe('Framework Templates - Directives', function () use ($frameworks) {

    // boolean-inline.view.php carried @help/@error in only two of the seven
    // packs until 2026-08-19, so help text and errors on inline checkboxes
    // silently rendered nothing in the other five, the default theme included
    $filesWithDirectives = ['input.view.php', 'select.view.php', 'textarea.view.php', 'boolean.view.php', 'boolean-inline.view.php'];

    foreach ($frameworks as $framework) {
        foreach ($filesWithDirectives as $file) {
            test("{$framework}/{$file} has paired directives", function () use ($framework, $file) {
                $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/{$file}");

                expect($content)->toContain('@label');
                expect($content)->toContain('@endlabel');
                expect($content)->toContain('@help');
                expect($content)->toContain('@endhelp');
                expect($content)->toContain('@error');
                expect($content)->toContain('@enderror');
            });
        }
    }

});

describe('Framework Templates - JS Validation', function () use ($frameworks) {

    $filesRequiringJsValidation = [
        'input.view.php',
        'select.view.php',
        'textarea.view.php',
        'boolean.view.php',
        'file.view.php',
    ];

    foreach ($frameworks as $framework) {
        foreach ($filesRequiringJsValidation as $file) {
            test("{$framework}/{$file} has js validation", function () use ($framework, $file) {
                $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/{$file}");

                expect($content)->toContain('<!-- js validation -->');
                expect($content)->toContain('has-error-{{ id }}');
            });
        }
    }

});

describe('Framework Templates - Alert Message', function () use ($frameworks) {

    $alertTypes = ['error', 'success', 'warning', 'info'];

    foreach ($frameworks as $framework) {
        foreach ($alertTypes as $type) {
            test("{$framework}/alerts/{$type}.view.php has message placeholder", function () use ($framework, $type) {
                $content = file_get_contents(__DIR__."/../../resources/views/{$framework}/alerts/{$type}.view.php");

                expect($content)->toContain('{{ message }}');
            });
        }
    }

});

describe('Flick Default - Uses flick-* Classes', function () {

    test('input uses flick-* classes', function () {
        $content = file_get_contents(__DIR__.'/../../resources/views/flick/input.view.php');

        expect($content)->toContain('flick-field');
        expect($content)->toContain('flick-label');
        expect($content)->toContain('flick-input');
        expect($content)->toContain('flick-error');
    });

    test('select uses flick-* classes', function () {
        $content = file_get_contents(__DIR__.'/../../resources/views/flick/select.view.php');

        expect($content)->toContain('flick-field');
        expect($content)->toContain('flick-select');
    });

    test('boolean uses flick-checkbox classes', function () {
        $content = file_get_contents(__DIR__.'/../../resources/views/flick/boolean.view.php');

        expect($content)->toContain('flick-checkbox');
        expect($content)->toContain('flick-checkbox-input');
        expect($content)->toContain('flick-checkbox-label');
    });

    test('submit uses flick-button class', function () {
        $content = file_get_contents(__DIR__.'/../../resources/views/flick/submit.view.php');

        expect($content)->toContain('flick-button');
    });

    test('alerts use flick-alert classes', function () {
        $alerts = ['error', 'success', 'warning', 'info'];

        foreach ($alerts as $type) {
            $content = file_get_contents(__DIR__."/../../resources/views/flick/alerts/{$type}.view.php");

            expect($content)->toContain('flick-alert');
            expect($content)->toContain("flick-alert-{$type}");
        }
    });

});

describe('CSS File', function () {

    test('flick.css exists', function () {
        expect(file_exists(__DIR__.'/../../resources/views/flick/flick.css'))->toBeTrue();
    });

    test('flick.css contains essential classes', function () {
        $content = file_get_contents(__DIR__.'/../../resources/views/flick/flick.css');

        expect($content)->toContain('.flick-field');
        expect($content)->toContain('.flick-label');
        expect($content)->toContain('.flick-input');
        expect($content)->toContain('.flick-button');
        expect($content)->toContain('.flick-error');
        expect($content)->toContain('.flick-alert');
    });

    test('flick.css contains group classes', function () {
        $content = file_get_contents(__DIR__.'/../../resources/views/flick/flick.css');

        expect($content)->toContain('.flick-checkbox-group');
        expect($content)->toContain('.flick-radio-group');
    });

});
