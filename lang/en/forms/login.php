<?php

return [
    'action' => '/',
    'method' => 'POST',
    'attributes' => [
        'id' => 'form-login',
    ],
    'button' => [
        'text' => 'Login',
    ],
    'fields' => [
        'username' => [
            'type' => 'text',
            'name' => 'username',
            'label' => 'Username',
            'rules' => ['required'],
            'messages' => [
                'required' => 'Please enter your username',
            ],
        ],
        'password' => [
            'type' => 'password',
            'name' => 'password',
            'label' => 'Password',
            'rules' => ['required'],
            'messages' => [
                'required' => 'Please enter your password',
            ],
        ],
    ],
];
