<?php

declare(strict_types=1);

use Flick\Flick;

/*
 * Audit 2026-08-19 — checkboxInline() and radioInline() wrote
 * $attributes['inline'] = true onto a parameter typed array|string, so the
 * string form every field element documents ('class=x', 'id=x', raw attribute
 * text, or '') fatalled with "Cannot access offset of type string on string"
 * while checkbox() and radio() took the same argument fine.
 *
 * The string is now read the same way Build reads it for every other element,
 * so the inline form renders exactly what the array form renders.
 */

beforeEach(function () {
    $this->form = new Flick(['csrf' => false, 'echo' => false]);
});

it('accepts a class= string on checkboxInline', function () {
    expect($this->form->checkboxInline('colors[]', 'Red', 'red', 'class=x'))
        ->toBe($this->form->checkboxInline('colors[]', 'Red', 'red', ['class' => 'x']))
        ->toContain('class="flick-checkbox-input x"')
        ->toContain('flick-checkbox-inline');
});

it('accepts a class= string on radioInline', function () {
    // the flick theme's inline view shares its class names between the two
    // boolean types, so only the appended class and the type are pinned here
    expect($this->form->radioInline('size', 'Small', 's', 'class=x'))
        ->toBe($this->form->radioInline('size', 'Small', 's', ['class' => 'x']))
        ->toContain('type="radio"')
        ->toContain('-input x"')
        ->toContain('-inline');
});

it('accepts an id= string on both inline elements', function () {
    // radios append the ucwords() value to keep ids unique within the group
    expect($this->form->checkboxInline('terms', 'I agree', '1', 'id=agree'))
        ->toBe($this->form->checkboxInline('terms', 'I agree', '1', ['id' => 'agree']))
        ->toContain('id="agree"')
        ->and($this->form->radioInline('size', 'Small', 's', 'id=small'))
        ->toBe($this->form->radioInline('size', 'Small', 's', ['id' => 'small']))
        ->toContain('id="smallS"');
});

it('accepts raw attribute text and an empty string on both inline elements', function () {
    expect($this->form->checkboxInline('terms', 'I agree', '1', 'data-x="1"'))
        ->toBe($this->form->checkboxInline('terms', 'I agree', '1', ['string' => 'data-x="1"']))
        ->toContain('data-x="1"')
        ->and($this->form->radioInline('size', 'Small', 's', ''))
        ->toBe($this->form->radioInline('size', 'Small', 's', []));
});
