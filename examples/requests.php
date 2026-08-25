<?php

// Examples of how you can utilize the request() method to process your forms.
// The PHP Superglobals $_POST and $_GET will be referred to as 'Request'.

use Flick\Flick;

$form = new Flick;

/**
 * String
 * Get the Request values for a form built with a string.
 */
$request = $form->request('Name, Email, Comments');

/**
 * String
 * Get Request values for a form built with a string and add rules and messages.
 * Rules and messages are placed inside brackets.
 * Rules first, messages second: [rule1, rule2][message1, message2]
 */
$data = $form->request(
    '
    Name[min:2, max:5][min:Less than 2, max: No more than 5], 
    Email[max:3], 
    Comments'
);

/**
 * Fields
 * Get the Request values for individual fields.
 */
if ($form->submitted()) {
    $name = $form->request('name');
    $email = $form->request('email');
    $comments = $form->request('comments');
}

/**
 * Fields
 * Get the Request values for an individual field and add validation rules.
 * The validation rules array goes in the request() method's second parameter.
 */
$name = $form->request('name', ['required', 'max:60']);

/**
 * Rules & Messages
 * Add validation rules and validation messages.
 * The validation rules array goes in the request() method's second parameter.
 * The validation messages array goes in the request() method's third parameter.
 */
$name = $form->request(
    'name',
    ['required', 'max:60'],
    [
        'required' => 'Please enter your name',
        'max' => 'Cannot be more than 60 characters',
    ]
);

/**
 * Array
 * Get the Request values for a form created with an array.
 */
$array = [
    'fields' => [
        'name' => [
            'label' => 'Name',
            'rules' => [
                'required',
            ],
            'messages' => [
                'required' => 'Please enter your name',
            ],
        ],
    ],
];
$data = $form->request($array);

/**
 * Prebuilt Form
 * Get the Request values for a prebuilt form.
 * Type a forward slash, then the name of the file.
 * The file's extension is not required.
 */
$data = $form->request('/login');
