<?php

use Flick\Flick;

beforeEach(function () {
    $this->form = new Flick([
        'csrf' => false,
        'echo' => false,
    ]);
});

it('reports a registered service as set', function () {
    // Flick reaches services through __get(), so without a matching __isset()
    // every `isset($form->someService)` guard silently reports "not installed".
    expect($this->form->hasService('views'))->toBeTrue();
    expect(isset($this->form->views))->toBeTrue();
});

it('reports an unknown service as not set', function () {
    expect($this->form->hasService('definitelyNotAService'))->toBeFalse();
    expect(isset($this->form->definitelyNotAService))->toBeFalse();
});

it('keeps isset() and hasService() in agreement for every built-in service', function () {
    foreach (['views', 'forms', 'dropdowns', 'mail'] as $service) {
        expect(isset($this->form->$service))
            ->toBe($this->form->hasService($service), "isset() disagreed with hasService() for '{$service}'");
    }
});

it('does not report an undefined non-service property as set', function () {
    expect(isset($this->form->somethingThatIsNotAServiceAtAll))->toBeFalse();
});
