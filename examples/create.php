<?php

// Examples of how you can utilize the create() method to build your forms.

use Flick\Flick;

/**
 * String
 * Create a form with a string.
 */
$form = new Flick;
$form->create('Name, Email');

/**
 * String
 * Create a form with a string and assign element types.
 * We can tell Flick what type of field element we want to use for each value.
 */
$formAssign = new Flick;
$formAssign->create('Name, Email|email, Comments|textarea');

/**
 * String
 * Create a form with a string, assign element types, and add default values.
 * Default field values are placed inside curly braces {}
 */
$formAssignDefault = new Flick;
$formAssignDefault->create(
    'Name{Gern}, 
    Email|email{gern@example.com}, 
    Comments|textarea'
);

/**
 * String
 * Create a form with a string and add the 'states' prebuilt dropdown menu.
 * Dropdown options are placed inside parentheses ()
 */
$formDropdown = new Flick;
$formDropdown->create(
    'Name, 
	Email|email, 
	State|select(states), 
	Comments|textarea'
);

/**
 * String Example
 * All the previous stuff, plus default <select> options and validation rules.
 * Add a default menu option by preceding it with double colons ::
 * Validation rules are placed inside brackets [].
 * Use the 'r' shortcut to make a field required.
 */
$string = '
 	Name{Gern}[min:3, max:30, r], 
 	Email|email[r, email], 
 	Foo|select([one:One, two:Two]::Select Something...)[r],
 	Date|date[before:yesterday], 
 	State{NV}|select(states::Select a State...)[required],
 	Comments|textarea[required],
 	I Agree to the Terms and Privacy Policy|checkbox{agree}
 ';
$formStringWow = new Flick;
$formStringWow->create($string);

/**
 * Use a Prebuilt Form
 * Create a form by loading a file which contains an array.
 * Type a forward slash, then the name of the file.
 * The file's extension is not required.
 */
$formFromFile = new Flick;
$formFromFile->create('/login');

/**
 * Multipart String
 * Create a multipart form for uploading files.
 * Flick adds 'enctype="multipart/form-data"' to the <form> tag.
 */
$formMultipart = new Flick;
$formMultipart->createMultipart('Photo|file');

/**
 * Array
 * Create a form with an array.
 * We can add dropdown options using a string or an array.
 */
$formDropdowns = new Flick;
$formDropdowns->create([
    'fields' => [
        'name' => [
            'name' => 'fullName',
            'label' => 'Name',
        ],
        'email' => [
            'name' => 'email',
            'type' => 'email',
            'label' => 'Email',
        ],
        'state' => [
            'name' => 'state',
            'type' => 'select',
            'label' => 'State',
            'value' => 'FL',
            'options' => 'states',
        ],
        'boolean' => [
            'name' => 'boolean',
            'type' => 'select',
            'label' => 'Boolean',
            'options' => [
                'yes' => 'Yes',
                'no' => 'No',
            ],
        ],
    ],
]);

/**
 * Array
 * Create a form with an array.
 * Omitting the 'name' attribute tells Flick to use the array key as the name.
 */
$formKeysAsName = new Flick;
$formKeysAsName->create([
    'fields' => [
        'name' => [
            'label' => 'Name',
        ],
        'email' => [
            'type' => 'email',
            'label' => 'Email',
        ],
    ],
]);

/**
 * Array
 * Create a form with an array and micromanage the heck out of it.
 */
$array = [
    'action' => '/',
    'method' => 'POST',
    'attributes' => [
        'id' => 'kifflom',
        'class' => 'form-horizontal',
        'multipart' => true,
        'string' => ' novalidate',
    ],
    'button' => [
        'text' => 'Join the Cult!',
        'attributes' => [
            'class' => 'btn btn-default',
        ],
    ],
    'fields' => [
        'username' => [
            'type' => 'text',
            'name' => 'username',
            'label' => 'Username',
            'attributes' => [
                'help' => 'between 3-20 characters',
                'classes' => 'form-control',
            ],
            'rules' => [
                'required',
                'min:3',
            ],
            'messages' => [
                'required' => 'Please enter your username',
                'min' => 'Username must be at least 3 characters',
            ],
        ],
        'email' => [
            'type' => 'email',
            'name' => 'email',
            'label' => 'Email',
            'rules' => [
                'required',
                'email',
            ],
            'messages' => [
                'required' => 'Please enter your email',
                'email' => 'Please enter a valid email address',
            ],
        ],
        'referrer' => [
            'type' => 'select',
            'name' => 'referrer',
            'label' => 'Referred By',
            'options' => [
                '' => 'Who Sent You?',
                'chris' => 'Chris Formage',
                'Marnie' => 'Marnie Allen',
                'jimmy' => 'Jimmy Boston',
            ],
            'rules' => [
                'required',
            ],
            'messages' => [
                'required' => 'Select a referrer',
            ],
        ],
    ],
];
$formLumbergh = new Flick;
$formLumbergh->create($array);
