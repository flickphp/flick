<p align="center">
  <a href="https://flickphp.com" target="_blank">
      <img src="https://raw.githubusercontent.com/flickphp/flick/main/art/flick-logo.svg" alt="Flick logo" width="300" style="max-width: 100%;">
  </a>
</p>

<p align="center">
    <a href="https://packagist.org/packages/flickphp/flick">
        <img src="https://img.shields.io/packagist/v/flickphp/flick?style=flat" alt="Latest Stable Version">
    </a>
    <a href="https://packagist.org/packages/flickphp/flick">
        <img src="https://img.shields.io/packagist/dependency-v/flickphp/flick/php?style=flat" alt="PHP Version">
    </a>
    <a href="https://packagist.org/packages/flickphp/flick">
        <img src="https://img.shields.io/packagist/l/flickphp/flick?style=flat" alt="License">
    </a>
</p>

# Flick

A stupidly-fast PHP form builder. One line of code builds a complete HTML form, with built-in server-side validation, CSRF protection, and all the other stuff you and your users need to stay fast and safe.

Sure, you can get AI to build your forms, but then you have to maintain them. And the more you build, the bigger the maintenance headache because none of them will be the same.

Flick is the successor to [Formr](https://github.com/formr/formr), which I also wrote. If you're coming from Formr, [flickphp/migrate](https://github.com/flickphp/migrate) converts your old code for you.

Full docs here: [flickphp.com](https://flickphp.com)

If you find Flick useful, please consider starring the project. Thank you!


## Installation

```bash
composer require flickphp/flick
```

## Configuration

Flick works with zero configuration. To change defaults, pass a config array:

```php
$form = new Flick\Flick([
    'views' => 'bootstrap',   // CSS framework view: flick, bootstrap, bulma, tailwind, ...
]);
```

See the full configuration reference at https://flickphp.com/guide/configuration.

## Usage

```php
<?php
require 'vendor/autoload.php';

$form = new Flick\Flick();

// Renders the form, handles submission, and validation
$form->createAndValidate('Name[required], Email[r,email], Message|textarea[max:255]');
```

### Separate form creation and submission
```php
<?php
require 'vendor/autoload.php';

$form = new Flick\Flick();

// Handle submission
if ($form->submitted()) {
    $data = $form->request('Name[required], Email[required, email], Message[min:10]');

    if ($form->ok()) {
        echo "Thanks, {$data['name']}!";
    }
}

// Render the form
$form->create('Name, Email, Message|textarea');
```

That's it. You get:
- CSRF protection (automatic)
- HTML injection prevention (automatic)
- Server-side validation
- Error messages that stick
- Values that persist on errors

## Use Any CSS Framework

```php
$form = new Flick\Flick(['views' => 'tailwind']);
// Also: flick, bootstrap, bootstrap4, bulma, foundation, materialize
```

## Common Patterns

**Contact form with honeypot spam protection:**

```php
$form = new Flick\Flick([
    'honeypot' => 'website_url',
    'views' => 'tailwind',
]);

if ($form->submitted()) {
    $data = $form->request('
        Name[required],
        Email[required, email],
        Phone[phone],
        Message[required, min:10, max:1000]
    ');

    if ($form->ok()) {
        // Send email, save to database, etc.
    }
}

$form->create('Name, Email, Phone, Message|textarea');
```

**Registration with password confirmation:**

```php
if ($form->submitted()) {
    // confirmed compares password against password_confirmation
    $data = $form->request('
        Username[required, min:3, alphaDash],
        Email[required, email],
        Password[required, strongPassword, confirmed],
        Password Confirmation[required]
    ');

    if ($form->ok()) {
        // $data['password'] is ready to use
    }
}

$form->create('Username, Email, Password|password, Password Confirmation|password');
```

**Build forms field by field:**

```php
$form->open('/contact');
$form->text('name', 'Your Name');
$form->email('email', 'Email Address');
$form->select('subject', 'Subject', '', ['General', 'Support', 'Sales']);
$form->textarea('message', 'Message');
$form->submit('Send');
$form->close();
```

## Upgrade to Pro

[Flick Pro](https://flickphp.com/pro) adds:
- **Auth** – Login, registration, password reset
- **Checkout** – Hosted payments with Polar and Stripe
- **Mail** – Send emails with any provider
- **OTP** – One-time email codes for login and 2FA
- **SQL** – Database queries made simple
- **Throttle** – Rate limiting for logins and forms
- **Upload** – File uploads with validation
- **Validation** – Real-time JavaScript validation
- **reCAPTCHA & Turnstile** – Bot protection

## Documentation

Full docs, validation rules, and examples at **[flickphp.com](https://flickphp.com)**

## AI Assistance

For AI coding assistants, comprehensive API references are available:
- [llms.txt](https://flickphp.com/llms.txt) – Quick reference
- [llms-full.txt](https://flickphp.com/llms-full.txt) – Complete API documentation

## Requirements

- PHP 8.3+
- ext-ctype, ext-curl, ext-fileinfo, ext-openssl

## See Also

- [Flick Documentation](https://flickphp.com) - Full Flick documentation
- [Flick for Laravel](https://github.com/flickphp/laravel) - Laravel integration
- [Flick Migrate](https://github.com/flickphp/migrate) - Formr to Flick migration tool
- [Flick Pro](https://flickphp.com/pro) - Premium services

## License

MIT
