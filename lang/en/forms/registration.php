<?php

return [
    'action' => '/',
    'method' => 'POST',
    'attributes' => [
        'id' => 'form-registration',
    ],
    'button' => [
        'text' => 'Sign Up',
    ],
    'fields' => [
        'username' => [
            'type' => 'text',
            'name' => 'username',
            'label' => 'Username',
            'rules' => ['required', 'min:3', 'max:20', 'slug'],
            'messages' => [
                'required' => 'Please enter a username',
                'min' => 'Username must be at least 3 characters',
                'max' => 'Username cannot be more than 20 characters',
            ],
        ],
        'email' => [
            'type' => 'email',
            'name' => 'email',
            'label' => 'Email',
            'rules' => ['required', 'email'],
            'messages' => [
                'required' => 'Please enter your email address',
                'email' => 'Please enter a valid email address',
            ],
        ],
        'password' => [
            'type' => 'password',
            'name' => 'password',
            'label' => 'Password',
            'rules' => ['required', 'min:8', 'hash'],
            'messages' => [
                'required' => 'Please enter a password',
                'min' => 'Your password must be at least 8 characters',
            ],
        ],
        'confirm' => [
            'type' => 'password',
            'name' => 'password-confirm',
            'label' => 'Confirm Password',
            'rules' => ['required', 'matches:password'],
            'messages' => [
                'required' => 'Please confirm your password',
                'matches' => 'Your passwords do not match',
            ],
        ],
        'agree' => [
            'type' => 'checkbox',
            'name' => 'agree',
            'label' => 'I agree to the Terms',
            'value' => '1',
            'rules' => ['required'],
            'messages' => ['required' => 'You must agree to the Terms'],
        ],
    ],
];
