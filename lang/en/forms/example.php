<?php

return [
    'action' => '/',
    'method' => 'POST',
    'attributes' => [
        'id' => 'form-example',
        'multipart' => true,
        'class' => 'needs-validation',
        'string' => 'novalidate',
    ],
    'button' => [
        'text' => 'Submit Me',
        'attributes' => [
            'class' => 'btn-lg',
        ],
    ],
    'fields' => [
        'text' => [
            'name' => 'text',
            'label' => 'Text',
            'attributes' => [
                'help' => 'This field also has some help text...',
                'classes' => 'text-primary text-sm',
            ],
            'rules' => ['required', 'min:3'],
            'messages' => ['required' => 'Please enter some text'],
        ],
        'email' => [
            'type' => 'email',
            'name' => 'email',
            'label' => 'Email',
            'rules' => ['email'],
            'messages' => ['email' => 'Please enter a valid email address'],
        ],
        'password' => [
            'type' => 'password',
            'name' => 'password',
            'label' => 'Password',
            'rules' => ['min:8'],
            'messages' => ['min' => 'Password needs to be at least 8 characters'],
        ],
        'confirm' => [
            'type' => 'password',
            'name' => 'password-confirm',
            'label' => 'Confirm Password',
            'rules' => ['min:8', 'matches:password', 'requiredWith:password'],
        ],
        'textarea' => [
            'type' => 'textarea',
            'name' => 'textarea',
            'label' => 'Textarea',
            'rules' => ['min:3'],
            'messages' => ['min' => 'Please enter at least 3 characters'],
        ],
        'num' => [
            'type' => 'number',
            'name' => 'number',
            'label' => 'Number',
            'attributes' => ['class' => 'col-sm-2'],
        ],
        'tel' => [
            'type' => 'tel',
            'name' => 'telephone',
            'label' => 'Telephone Number',
        ],
        'date' => [
            'type' => 'date',
            'name' => 'date',
            'label' => 'Date Field',
        ],
        'time' => [
            'type' => 'time',
            'name' => 'time',
            'label' => 'Time Field',
        ],
        'color' => [
            'type' => 'color',
            'name' => 'color',
            'label' => 'Color Field',
        ],
        'week' => [
            'type' => 'week',
            'name' => 'week',
            'label' => 'Week Field',
        ],
        'month' => [
            'type' => 'month',
            'name' => 'month',
            'label' => 'Month Field',
        ],
        'range' => [
            'type' => 'range',
            'name' => 'range',
            'label' => 'Range Field',
            'attributes' => [
                'string' => 'min="0" max="100" value="90" step="10"',
            ],
        ],
        'url' => [
            'type' => 'url',
            'name' => 'url',
            'label' => 'URL Field',
        ],
        'fieldset' => [
            'legend' => 'File Uploads',
            'fields' => [
                'photo' => [
                    'type' => 'file',
                    'name' => 'photo',
                    'label' => 'Single File',
                    'rules' => ['image'],
                    'messages' => ['image' => 'Please select a JPG, PNG, or GIF'],
                ],
                'photos' => [
                    'type' => 'files',
                    'name' => 'photos[]',
                    'label' => 'Multiple Files',
                    'rules' => ['image'],
                    'messages' => ['image' => 'Please select a JPG, PNG, or GIF'],
                ],
            ],
        ],
        'fieldset-select' => [
            'legend' => 'Select Menus',
            'fields' => [
                'select' => [
                    'type' => 'select',
                    'name' => 'state',
                    'label' => 'State Select Menu',
                    'options' => 'states',
                ],
                'select2' => [
                    'type' => 'select',
                    'name' => 'foobarBaz',
                    'label' => 'Foo Select Menu',
                    'options' => [
                        'foo' => 'Foo',
                        'bar' => 'Foo',
                        'baz' => 'Baz',
                    ],
                ],
                'select3' => [
                    'type' => 'select',
                    'name' => 'fruit[]',
                    'label' => 'Multiple Select Menu',
                    'attributes' => [
                        'multiple' => true,
                        'options' => [
                            'apple' => 'Apple',
                            'banana' => 'Banana',
                            'peach' => 'Peach',
                        ],
                    ],
                ],
            ],
        ],
        'fieldset-radios' => [
            'legend' => 'Radios and Checkbox',
            'fields' => [
                'foo' => [
                    'type' => 'radio',
                    'name' => 'radio',
                    'label' => 'Radio Group Foo',
                    'value' => 'foo',
                ],
                'bar' => [
                    'type' => 'radio',
                    'name' => 'radio',
                    'label' => 'Radio Group Foo',
                    'value' => 'bar',
                ],
                'a' => [
                    'type' => 'radio',
                    'name' => 'radio-inline',
                    'label' => 'Inline Radio Group A',
                    'value' => 'a',
                    'attributes' => ['inline' => true],
                ],
                'b' => [
                    'type' => 'radio',
                    'name' => 'radio-inline',
                    'label' => 'Inline Radio Group B',
                    'value' => 'b',
                    'attributes' => ['inline' => true],
                ],
                'agree' => [
                    'type' => 'checkbox',
                    'name' => 'agree',
                    'label' => 'Checkbox',
                    'value' => 'agree',
                ],
            ],
        ],
    ],
];
