<?php

// Flick's available configuration values.

use Flick\Flick;

$config = [

    /**
     * Field Views
     * The HTML/CSS views you will use to wrap your elements.
     * Default: 'flick'
     */
    'views' => 'bootstrap',

    /**
     * Alerts
     * Show an alert box when there are form errors.
     * Default behavior displays the error message beneath the element.
     * Default: FALSE
     */
    'showErrorsAlert' => true,

    /**
     * Form ID
     * The Form's ID.
     * Default: 'myForm'
     */
    'id' => 'myFlickForm',

    /**
     * Caching
     * Cache the field element view files.
     * Default: FALSE
     *
     * Requires an `assets` directory — that is where the cached views are
     * written — so enabling it without one is a fail-fast error at
     * construction. Commented out here alongside `assets` below.
     */
    // 'cache' => true,      // enables caching (caches the view files).
    // 'cache' => false,     // disables caching (does not delete cached views).
    // 'cache' => 'flush',   // flushes the cache (deletes the cached views).

    /**
     * Echo
     * Echos field elements to the browser.
     * Default: TRUE
     */
    'echo' => false,

    /**
     * Date Format
     * How you would like your dates formatted.
     * Default: 'Y-m-d'
     */
    'dateFormat' => 'm-d-Y',

    /**
     * Honeypot
     * Add a honeypot to assist in thwarting spam.
     * Accepts a name for the honeypot field element.
     */
    'honeypot' => 'company',

    /**
     * CSRF Protection
     * A CSRF token is automatically added to your form.
     * You can disable CSRF by setting this value to FALSE.
     * Default: 3600 (1 hour)
     */
    'csrf' => 1800,

    /**
     * Translation
     * The language you want to use for messaging and validation errors.
     * The lang file must exist in /lang, or use your own file (see: Assets).
     * Default: 'en'
     *
     * Commented out because Flick ships 'en' only, and naming a language whose
     * rules.php it cannot find is a fail-fast error at construction, not a
     * silent fallback. Uncomment once you have lang/<code>/rules.php.
     */
    // 'lang' => 'es',

    /**
     * Assets Directory
     * This is where you will store all of your custom Flick files.
     * Flick will look here for views, forms, etc., or fallback to defaults.
     *
     * Commented out for the same reason: once you point 'assets' somewhere,
     * Flick expects the language file under it too, so an assets directory with
     * no lang/<code>/rules.php fails at construction even when you only wanted
     * custom views.
     */
    // 'assets' => __DIR__.'/../myFlickFiles',

    /**
     * Validation Rules
     * Add your custom validation rules here to make them globally available.
     * Write once, use twice!
     */
    'rules' => [
        'name' => [
            'min:2',
            'max:60',
        ],
        'comments' => [
            'min:20',
        ],
    ],

    /**
     * Validation Messages
     * Add your custom validation messages here to make them globally available.
     * Write once, use twice!
     */
    'messages' => [
        'name' => [
            'min' => 'Must be at least 2 characters',
            'max' => 'Cannot be more than 60 characters',
        ],
        'comments' => [
            'min' => 'Must be at least 20 characters',
        ],
    ],

    /**
     * Service Providers
     * Extend Flick with Service Providers!
     * Each provider must have a key/value pair.
     * The key is the name of the service.
     * The value is the service's settings, or an empty array.
     * Example: $form->foo->hello();
     * Example: $form->bar->updateProfile();
     */
    'services' => [
        'foo' => [],
        'bar' => [
            'token' => 'FooBarBaz',
            'secret' => 'Kifflom!',
        ],
    ],
];

// Add the configuration values to Flick during instantiation.
$form = new Flick($config);

// If you only want to define the wrapper...
$form = new Flick('bootstrap');

// If you're happy with Flick's defaults, just omit the config altogether.
$form = new Flick;
