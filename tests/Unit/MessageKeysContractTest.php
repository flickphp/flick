<?php

declare(strict_types=1);

/*
 * Every key core asks Support::message() for must exist in the shipped
 * lang/en/messages.php. A missing one would surface as a LogicException in
 * a user's error path, so it is caught here instead. The keys are read out
 * of the source rather than from a hand-kept list - AUDIT-FOLLOWUPS records
 * how those drift. The Pro suite runs the same scan over its own packages.
 */

function messageKeysAskedForUnder(string $directory): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        // Anchored on the Support property so Build::message(), the alert-box
        // renderer Flick's *Message() helpers call, is not mistaken for a key.
        preg_match_all("/support->message\(\s*'([A-Za-z0-9]+)'/", file_get_contents($file->getPathname()), $found);
        $keys = array_merge($keys, $found[1]);
    }

    sort($keys);

    return array_values(array_unique($keys));
}

it('ships an English text for every key core asks Support::message() for', function () {
    $asked = messageKeysAskedForUnder(__DIR__.'/../../src');
    $shipped = array_keys(require __DIR__.'/../../lang/en/messages.php');

    // An empty scan means the regex no longer sees the call sites, not that
    // core has stopped asking - fail rather than pass vacuously.
    expect($asked)->not->toBeEmpty()
        ->and(array_values(array_diff($asked, $shipped)))->toBe([]);
});
