<?php

use Flick\Flick;

beforeEach(function () {
    $this->flick = new Flick([]);
});

describe('Views::load() - Flick views', function () {

    it('loads the input view', function () {
        $result = $this->flick->views->load('flick/input.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<input')
            ->and($result)->toContain('{{ name }}');
    });

    it('loads the select view', function () {
        $result = $this->flick->views->load('flick/select.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<select')
            ->and($result)->toContain('{{ options }}');
    });

    it('loads the textarea view', function () {
        $result = $this->flick->views->load('flick/textarea.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<textarea');
    });

    it('loads the hidden view', function () {
        $result = $this->flick->views->load('flick/hidden.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('type="hidden"');
    });

    it('loads the submit view', function () {
        $result = $this->flick->views->load('flick/submit.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('type="submit"');
    });

    it('loads the file view', function () {
        $result = $this->flick->views->load('flick/file.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('type="file"');
    });

    it('loads the boolean view', function () {
        $result = $this->flick->views->load('flick/boolean.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('flick-checkbox')
            ->and($result)->toContain('{{ type }}');
    });

    it('loads the boolean-inline view', function () {
        $result = $this->flick->views->load('flick/boolean-inline.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('flick-checkbox-inline')
            ->and($result)->toContain('{{ type }}');
    });

    it('loads the breadcrumbs view', function () {
        $result = $this->flick->views->load('flick/breadcrumbs.view.php');

        expect($result)->toBeString();
    });

});

describe('Views::load() - Alert views', function () {

    it('loads the error alert view', function () {
        $result = $this->flick->views->load('flick/alerts/error.view.php');

        expect($result)->toBeString()
            ->and($result)->not->toBeEmpty();
    });

    it('loads the success alert view', function () {
        $result = $this->flick->views->load('flick/alerts/success.view.php');

        expect($result)->toBeString()
            ->and($result)->not->toBeEmpty();
    });

    it('loads the warning alert view', function () {
        $result = $this->flick->views->load('flick/alerts/warning.view.php');

        expect($result)->toBeString()
            ->and($result)->not->toBeEmpty();
    });

    it('loads the info alert view', function () {
        $result = $this->flick->views->load('flick/alerts/info.view.php');

        expect($result)->toBeString()
            ->and($result)->not->toBeEmpty();
    });

});

describe('Views::load() - Bootstrap views', function () {

    it('loads bootstrap input view', function () {
        $result = $this->flick->views->load('bootstrap/input.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<input');
    });

    it('loads bootstrap select view', function () {
        $result = $this->flick->views->load('bootstrap/select.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<select');
    });

    it('loads bootstrap textarea view', function () {
        $result = $this->flick->views->load('bootstrap/textarea.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<textarea');
    });

});

describe('Views::load() - Bulma views', function () {

    it('loads bulma input view', function () {
        $result = $this->flick->views->load('bulma/input.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<input');
    });

    it('loads bulma select view', function () {
        $result = $this->flick->views->load('bulma/select.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<select');
    });

    it('loads bulma textarea view', function () {
        $result = $this->flick->views->load('bulma/textarea.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('<textarea');
    });

});

describe('Views::loadAsset()', function () {

    it('loads a view using absolute path', function () {
        $result = $this->flick->views->loadAsset(__DIR__.'/../../resources/views/flick/hidden.view.php');

        expect($result)->toBeString()
            ->and($result)->toContain('type="hidden"');
    });

});

describe('View Content Validation', function () {

    it('input view contains placeholder variables', function () {
        $result = $this->flick->views->load('flick/input.view.php');

        expect($result)->toContain('{{ name }}')
            ->and($result)->toContain('{{ type }}');
    });

    it('select view contains options placeholder', function () {
        $result = $this->flick->views->load('flick/select.view.php');

        expect($result)->toContain('{{ options }}');
    });

});
