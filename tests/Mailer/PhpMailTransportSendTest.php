<?php

declare(strict_types=1);

use Flick\Mailer\Transport\PhpMailTransport;

/**
 * PhpMailTransport::send() and getLastError().
 *
 * send() goes through PHP's mail(), which hands the message to whatever
 * sendmail_path points at. That setting is PHP_INI_SYSTEM, so the send runs in
 * a child process started with an explicit -d sendmail_path: once at a binary
 * that does not exist (the failure branch) and once at a small stub script
 * that captures the message (the success branch) -- a shell script on POSIX, a
 * batch file on Windows. No MTA is involved, so the result is the same here and
 * on CI.
 *
 * Spec: websites/flickphp.test/resources/docs/guide/mail.md, "PHP mail()
 * Transport" (lines 31-52) and "Methods" (lines 235-271).
 */
beforeEach(function () {
    $this->workingDirectory = sys_get_temp_dir().'/flick-phpmail-'.bin2hex(random_bytes(6));
    mkdir($this->workingDirectory);

    // Expand any 8.3 short components before the path is used anywhere.
    // GitHub's Windows runners return the temp directory as
    // C:\Users\RUNNER~1\AppData\Local\Temp, and that '~' is an operator in
    // PHP's INI value syntax -- so handing the path to -d below ended the value
    // early ("syntax error, unexpected '~'"), leaving sendmail_path set to the
    // truncated C:\Users\RUNNER, which cmd could not find. realpath() resolves
    // the short name to the real one; the -d value is quoted as well, so
    // neither mitigation depends on the other.
    if (PHP_OS_FAMILY === 'Windows') {
        $this->workingDirectory = realpath($this->workingDirectory) ?: $this->workingDirectory;
    }

    $this->capturePath = $this->workingDirectory.'/captured.eml';

    // A stand-in sendmail: swallow the message, keep it, report success.
    //
    // sendmail_path is honoured on Windows too -- it overrides the
    // SMTP/smtp_port settings there -- but PHP runs it through cmd.exe, and two
    // things have to be right for that to work:
    //
    //   1. The stub must be in the local shell's language: cmd cannot run a
    //      #!/bin/sh script. `findstr "^"` matches every line and echoes it
    //      through, which is the batch pass-through closest to `cat`. (`more`
    //      also copies stdin but expands tabs, which would corrupt what these
    //      tests assert against.)
    //   2. Every path handed to cmd must use backslashes. sys_get_temp_dir()
    //      returns them, the '/' separators appended above do not, and cmd
    //      reads a forward slash as the start of a switch -- so a mixed path
    //      is not a path it can execute. The PHP file APIs above accept either,
    //      which is exactly why this is easy to miss.
    if (PHP_OS_FAMILY === 'Windows') {
        $this->capturePath = str_replace('/', '\\', $this->capturePath);
        $this->fakeSendmail = str_replace('/', '\\', $this->workingDirectory.'/sendmail.bat');

        file_put_contents(
            $this->fakeSendmail,
            "@echo off\r\nfindstr \"^\" > \"{$this->capturePath}\"\r\n"
        );
    } else {
        $this->fakeSendmail = $this->workingDirectory.'/sendmail.sh';
        file_put_contents($this->fakeSendmail, "#!/bin/sh\ncat > \"{$this->capturePath}\"\n");
        chmod($this->fakeSendmail, 0o755);
    }

    // Reading the capture directly hid why a send failed: file_get_contents on
    // a missing file gave a warning and an empty string, so the assertion below
    // it reported "does not contain" instead of "the stub never ran". Surface
    // the stub and what the child said instead.
    $this->captured = function (): string {
        if (! is_file($this->capturePath)) {
            throw new RuntimeException(
                "The fake sendmail wrote nothing.\n".
                "  stub:     {$this->fakeSendmail}\n".
                "  capture:  {$this->capturePath}\n".
                // What the child actually resolved, which is the thing to
                // compare against the stub above: if they differ, the -d value
                // was mangled rather than the stub failing to run.
                '  resolved: '.($this->lastSendmailPath ?? '(not run)')."\n".
                '  stderr:   '.($this->lastStderr ?: '(none)')
            );
        }

        return (string) file_get_contents($this->capturePath);
    };

    $this->send = function (string $sendmailPath, array $options = []): array {
        $command = [
            PHP_BINARY,
            // The value is quoted because -d is parsed as INI, not taken
            // literally, and INI treats ~ | & ^ ( ) as operators. GitHub's
            // Windows runners hand back a temp path under the 8.3 short name
            // C:\Users\RUNNER~1\..., whose ~ ends the value early: PHP reported
            // "syntax error, unexpected '~'", kept the truncated C:\Users\RUNNER
            // as sendmail_path, and cmd then could not find it. Quoting makes
            // the whole thing a literal string. Backslashes need no escaping
            // here -- this is the same form php.ini uses for Windows paths.
            '-d', 'sendmail_path="'.$sendmailPath.'"',
            __DIR__.'/Fixtures/php-mail-send.php',
            json_encode($options),
        ];

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $this->lastStderr = trim($stderr);
        $this->lastSendmailPath = json_decode(trim($output), true)['sendmailPath'] ?? '(not reported)';

        $decoded = json_decode(trim($output), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Child process returned no result. stdout: {$output} stderr: {$stderr}");
        }

        return $decoded;
    };
});

afterEach(function () {
    array_map('unlink', glob($this->workingDirectory.'/*') ?: []);
    rmdir($this->workingDirectory);
});

/*
|--------------------------------------------------------------------------
| The failure branch
|--------------------------------------------------------------------------
|
| Both of these are POSIX-only, and not because the test is awkward to write
| on Windows -- the behaviour they assert does not exist there.
|
| PHP's mail() hands the message to sendmail_path through popen(). On Windows
| that pipe goes through cmd.exe, which starts successfully whether or not the
| command it was asked to run exists, so mail() reports success and there is
| nothing for getLastError() to report. CI confirms it: with sendmail_path
| pointing at a path that does not exist, the Windows runners returned true.
|
| That is a PHP limitation rather than a Flick one -- send() returns what
| mail() told it -- so there is no implementation change that would make these
| pass. It is documented for developers in guide/mail.md instead.
*/

$sendmailFailureIsDetectable = PHP_OS_FAMILY !== 'Windows';

it('returns false when mail() cannot deliver', function () {
    $result = ($this->send)('/nonexistent/flick-has-no-sendmail');

    expect($result['sent'])->toBeFalse();
})->skip(! $sendmailFailureIsDetectable, 'PHP mail() cannot detect a failed sendmail command on Windows');

it('explains the failure through getLastError', function () {
    $result = ($this->send)('/nonexistent/flick-has-no-sendmail');

    expect($result['error'])->toBe('PHP mail() function failed. Check server mail configuration.');
})->skip(! $sendmailFailureIsDetectable, 'PHP mail() cannot detect a failed sendmail command on Windows');

it('has no last error before anything is sent', function () {
    expect((new PhpMailTransport)->getLastError())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The success branch
|--------------------------------------------------------------------------
*/

it('returns true when mail() delivers', function () {
    $result = ($this->send)($this->fakeSendmail);

    expect($result['sent'])->toBeTrue()
        ->and($result['error'])->toBeNull();
});

it('hands the subject, body and headers to sendmail', function () {
    ($this->send)($this->fakeSendmail, [
        'subject' => 'Order confirmed',
        'text' => 'Thanks for your order.',
        'replyTo' => 'reply@example.com',
    ]);

    $captured = ($this->captured)();

    expect($captured)->toContain('Order confirmed')
        ->and($captured)->toContain('Thanks for your order.')
        ->and($captured)->toContain('From: "Flick Sender" <from@example.com>')
        ->and($captured)->toContain('Reply-To: reply@example.com');
});

it('hands an html message to sendmail as multipart alternative', function () {
    ($this->send)($this->fakeSendmail, ['html' => '<h1>Thanks!</h1>']);

    $captured = ($this->captured)();

    expect($captured)->toContain('multipart/alternative')
        ->and($captured)->toContain('<h1>Thanks!</h1>')
        ->and($captured)->toContain('Thanks for your order.');
});

it('does not let a CRLF in the subject open a new header', function () {
    ($this->send)($this->fakeSendmail, [
        'subject' => "Order confirmed\r\nBcc: evil@example.com",
    ]);

    $captured = ($this->captured)();

    expect($captured)->not->toContain("\nBcc: evil@example.com");
});
