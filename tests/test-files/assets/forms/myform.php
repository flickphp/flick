<?php

return [
    'action' => 'myform.php',
    'method' => 'POST',
    'attributes' => [
        'id' => 'myForm',
    ],
    'button' => [
        'text' => 'Foo',
    ],
    'fields' => [
        'username' => [
            'type' => 'text',
            'name' => 'username',
            'label' => 'USERNAME',
            'rules' => ['required', 'min:3'],
            'messages' => ['required' => 'Please enter your username'],
        ],
        'password' => [
            'type' => 'password',
            'name' => 'password',
            'label' => 'PASSWORD',
            'rules' => ['required', 'min:8'],
            'messages' => ['required' => 'Please enter your password'],
        ],
    ],
];
