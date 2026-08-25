<?php

/**
 * A throwaway SMTP server for the transport tests.
 *
 * Runs as its own process so the transport can block on a real socket without
 * deadlocking the test run. Listens on an ephemeral port, prints that port on
 * stdout so the parent knows where to connect, serves exactly one connection,
 * writes every byte the client sent to a transcript file, and exits.
 *
 * Usage: php fake-smtp-server.php <transcript-path> [scenario]
 *
 * Scenarios:
 *   ok             normal, cooperative server (default)
 *   auth-fail      rejects AUTH with 535
 *   greeting-fail  answers the connection with 554 instead of 220
 *   data-fail      rejects the message body with 554 after the final dot
 */

declare(strict_types=1);

$transcriptPath = $argv[1] ?? null;
$scenario = $argv[2] ?? 'ok';

if ($transcriptPath === null) {
    fwrite(STDERR, "usage: fake-smtp-server.php <transcript-path> [scenario]\n");
    exit(1);
}

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

if ($server === false) {
    fwrite(STDERR, "listen failed: {$errstr}\n");
    exit(1);
}

// Tell the parent which port to talk to.
fwrite(STDOUT, stream_socket_get_name($server, false)."\n");
fflush(STDOUT);

$transcript = '';
$connection = @stream_socket_accept($server, 10);

if ($connection === false) {
    file_put_contents($transcriptPath, $transcript);
    exit(0);
}

stream_set_timeout($connection, 5);

$say = function (string $line) use ($connection): void {
    fwrite($connection, $line."\r\n");
};

$read = function () use ($connection, &$transcript): ?string {
    $line = fgets($connection);

    if ($line === false) {
        return null;
    }

    $transcript .= $line;

    return rtrim($line, "\r\n");
};

if ($scenario === 'greeting-fail') {
    $say('554 No service here');
    fclose($connection);
    file_put_contents($transcriptPath, $transcript);
    exit(0);
}

$say('220 fake.test ESMTP FakeSmtp');

while (($line = $read()) !== null) {
    $verb = strtoupper(strtok($line, ' ') ?: '');

    switch ($verb) {
        case 'EHLO':
            $say('250-fake.test');
            $say('250-AUTH LOGIN PLAIN');
            $say('250-SIZE 10485760');
            $say('250 HELP');
            break;

        case 'HELO':
            $say('250 fake.test');
            break;

        case 'STARTTLS':
            $say('454 TLS not available');
            break;

        case 'AUTH':
            $mechanism = strtoupper((string) strtok(' '));
            $argument = strtok('');

            if ($mechanism === 'LOGIN') {
                $say('334 VXNlcm5hbWU6');
                $read();
                $say('334 UGFzc3dvcmQ6');
                $read();
            } elseif ($argument === false || $argument === '') {
                $say('334 ');
                $read();
            }

            $say($scenario === 'auth-fail' ? '535 Authentication failed' : '235 Authentication succeeded');
            break;

        case 'MAIL':
        case 'RCPT':
            $say('250 OK');
            break;

        case 'DATA':
            $say('354 Send it');

            while (($bodyLine = $read()) !== null) {
                if ($bodyLine === '.') {
                    break;
                }
            }

            $say($scenario === 'data-fail' ? '554 Message rejected' : '250 OK queued');
            break;

        case 'RSET':
        case 'NOOP':
            $say('250 OK');
            break;

        case 'QUIT':
            $say('221 Bye');
            break 2;

        default:
            $say('500 Unrecognized command');
            break;
    }
}

fclose($connection);
fclose($server);

file_put_contents($transcriptPath, $transcript);
