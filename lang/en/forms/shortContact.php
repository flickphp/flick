<?php

return [
    'action' => '/',
    'method' => 'POST',
    'attributes' => [
        'id' => 'form-contact',
    ],
    'button' => [
        'text' => 'Submit form',
    ],
    'fields' => [
        'name' => [
            'name' => 'name',
            'label' => 'Name',
            'rules' => ['required', 'min:5'],
            'messages' => [
                'required' => 'Please enter your name',
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
        'body' => [
            'type' => 'textarea',
            'name' => 'body',
            'label' => 'Message',
            'rules' => ['required'],
            'messages' => [
                'required' => 'Please enter your message',
            ],
        ],
    ],
];
