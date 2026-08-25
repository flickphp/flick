<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * Audit 2026-08-19, S13 — boolean.view.php carried @help and @error in all
 * seven packs, but boolean-inline.view.php had them only in bootstrap and
 * bulma. An inline checkbox or radio with help text, or with a validation
 * error against it, rendered neither in the other five packs - the default
 * flick theme among them. FrameworkTemplatesTest now holds the directives
 * present; this file holds that they actually render.
 */

$packs = ['bootstrap', 'bootstrap4', 'bulma', 'flick', 'foundation', 'materialize', 'tailwind'];

foreach ($packs as $pack) {
    it("renders help text on an inline checkbox in the {$pack} pack", function () use ($pack) {
        $form = new Flick(['csrf' => false, 'echo' => false, 'views' => $pack]);

        $html = $form->checkboxInline('terms', 'I agree', '1', ['help' => 'Read them first']);

        expect($html)->toContain('Read them first')
            ->and($html)->not->toContain('@help');
    });

    it("renders a validation error on an inline radio in the {$pack} pack", function () use ($pack) {
        $form = new Flick(['csrf' => false, 'echo' => false, 'views' => $pack]);
        $form->addError('size', 'Pick a size');

        $html = $form->radioInline('size', 'Small', 's');

        expect($html)->toContain('Pick a size')
            ->and($html)->not->toContain('@error');
    });
}
