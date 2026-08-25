<?php

declare(strict_types=1);

use Flick\Mailer\Mailer;
use Flick\Mailer\Message\FormDataFormatter;
use Flick\Mailer\Transport\TransportInterface;
use Flick\Support\Errors;
use Flick\Support\Support;

/*
 * Decided 2026-08-20 (Tim): the two end-user texts Mailer writes to the
 * error bag - a send that failed with no transport detail, and a recipient
 * that is not an email address (often typed into the user's own form) -
 * come from lang/<code>/messages.php like every other Flick message, so a
 * translation covers them. The map below is shaped exactly as Flick builds
 * it: the shipped English with one key laid over, the sibling untouched.
 */

function mailerWithMessages(array $translated, TransportInterface $transport): array
{
    $english = require __DIR__.'/../../lang/en/messages.php';
    $errors = new Errors;
    $support = new Support(['applicationMessages' => array_replace($english, $translated)], $errors);

    $mailer = new Mailer(
        ['fromAddress' => 'noreply@example.com', 'fromName' => 'Test App'],
        $support,
        $transport,
        new FormDataFormatter
    );

    return [$mailer, $errors];
}

afterEach(function () {
    Mockery::close();
});

it('reports a detail-less transport failure in the translated text', function () {
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andReturn(false);
    $transport->shouldReceive('getLastError')->once()->andReturn(null);

    [$mailer, $errors] = mailerWithMessages(['MailSendFailed' => 'No se pudo enviar el correo'], $transport);

    expect($mailer->send('user@example.com', 'Subject', 'Body'))->toBeFalse()
        ->and($errors->get('mail'))->toBe('No se pudo enviar el correo');
});

it('reports an invalid recipient in the translated text with the address spliced in', function () {
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldNotReceive('send');

    [$mailer, $errors] = mailerWithMessages(['MailInvalidAddress' => 'Correo no válido: :address'], $transport);

    expect($mailer->send('not-an-address', 'Subject', 'Body'))->toBeFalse()
        ->and($errors->get('mail'))->toBe('Correo no válido: not-an-address');
});

it('leaves a key the translation does not carry in the shipped English', function () {
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldNotReceive('send');

    // Only MailSendFailed is translated; the invalid-address path must still
    // read the English text for its own key.
    [$mailer, $errors] = mailerWithMessages(['MailSendFailed' => 'No se pudo enviar el correo'], $transport);

    expect($mailer->send('not-an-address', 'Subject', 'Body'))->toBeFalse()
        ->and($errors->get('mail'))->toBe('Invalid email address: not-an-address');
});
