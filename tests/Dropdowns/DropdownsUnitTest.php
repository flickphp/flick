<?php

use Flick\Dropdowns\Dropdowns;
use Flick\Support\Support;

beforeEach(function () {
    $this->config = ['form' => ['lang' => 'en']];
    $this->support = Mockery::mock(Support::class)->makePartial();
});

afterEach(function () {
    Mockery::close();
});

describe('Dropdowns::load() - All Available Dropdowns', function () {

    it('loads months dropdown with 12 entries', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('months');

        expect($result)->toBeArray()
            ->and(count($result))->toBe(12)
            ->and($result[1])->toBe('January')
            ->and($result[12])->toBe('December');
    });

    it('loads months2 dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('months2');

        expect($result)->toBeArray()
            ->and(count($result))->toBe(12);
    });

    it('loads states dropdown with 51 entries', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('states');

        expect($result)->toBeArray()
            ->and(count($result))->toBe(51); // 50 states + DC
    });

    it('loads provinces dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('provinces');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

    it('loads statesProvinces dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('statesProvinces');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

    it('loads countries dropdown with many entries', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('countries');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(100);
    });

    it('loads countriesUs dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('countriesUs');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(100);
    });

    it('loads timezones dropdown with many entries', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('timezones');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(100);
    });

    it('loads ages dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('ages');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

    it('loads currencies dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('currencies');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(100);
    });

    it('loads days dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('days');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

    it('loads heights dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('heights');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

    it('stores heights labels as raw text, not pre-encoded entities', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('heights');

        // the view layer escapes labels on render; pre-encoded entities in the
        // data would be double-escaped and show literal &quot;/&amp; in the browser
        expect($result['4-0'])->toBe('4\' 0"')
            ->and($result['6-0'])->toBe("6' & Over");

        foreach ($result as $label) {
            expect($label)->not->toContain('&quot;')
                ->and($label)->not->toContain('&amp;');
        }
    });

    it('renders heights option labels single-escaped', function () {
        $form = new Flick\Flick(['echo' => false]);
        $html = $form->create('Height|select(heights)');

        expect($html)->toContain('4&#039; 0&quot;')
            ->and($html)->not->toContain('&amp;quot;')
            ->and($html)->not->toContain('&amp;amp;');
    });

    it('loads heightsMetric dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('heightsMetric');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

    it('loads languages dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('languages');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

    it('loads years dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('years');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

    it('loads yearsPlus dropdown', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('yearsPlus');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0);
    });

});

describe('Dropdowns::load() - Data Integrity', function () {

    it('months have correct names', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('months');

        expect($result[1])->toBe('January')
            ->and($result[2])->toBe('February')
            ->and($result[6])->toBe('June')
            ->and($result[12])->toBe('December');
    });

    it('states contain expected US states', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('states');

        expect($result)->toHaveKey('CA')
            ->and($result)->toHaveKey('NY')
            ->and($result)->toHaveKey('TX')
            ->and($result['CA'])->toBe('California')
            ->and($result['NY'])->toBe('New York')
            ->and($result['TX'])->toBe('Texas');
    });

    it('countries contain expected entries', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->load('countries');

        expect($result)->toHaveKey('US')
            ->and($result)->toHaveKey('CA')
            ->and($result)->toHaveKey('GB');
    });

});

describe('Dropdowns::loadAsset()', function () {

    it('loads dropdown using absolute path', function () {
        $dropdowns = new Dropdowns($this->config, $this->support);
        $result = $dropdowns->loadAsset(__DIR__.'/../../lang/en/dropdowns/months.php');

        expect($result)->toBeArray()
            ->and(count($result))->toBe(12)
            ->and($result[1])->toBe('January');
    });

});

describe('Configuration', function () {

    it('uses configured language path', function () {
        $config = ['form' => ['lang' => 'en']];
        $dropdowns = new Dropdowns($config, $this->support);
        $result = $dropdowns->load('months');

        expect($result)->toBeArray()
            ->and($result)->not->toBeEmpty();
    });

});
