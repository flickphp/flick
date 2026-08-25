<?php

use Flick\Flick;

it('adds a honeypot field to the form', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $form = new Flick([
        'honeypot' => 'my_honeypot',
        'echo' => false,
        'csrf' => false,
    ]);

    $html = $form->open();

    expect($html)->toContain('<input type="text" name="my_honeypot" value="" style="display:none">');
});

it('allows form submission when honeypot is empty', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $_POST['my_honeypot'] = '';
    $_POST['_id'] = 'myForm'; // Add form ID to match default

    $form = new Flick([
        'honeypot' => 'my_honeypot',
        'csrf' => false,
    ]);

    expect($form->submitted())->toBeTrue();
});
