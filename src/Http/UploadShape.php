<?php

declare(strict_types=1);

namespace Flick\Http;

/**
 * The one reading of "does this $_FILES entry hold a real upload?".
 *
 * Lives here, next to ProxyTrust, for the same reason ProxyTrust does: this
 * check used to be copy-pasted into both request adapters, and only one copy
 * was tested — an edit to the array-shape handling in one adapter would have
 * passed CI while leaving the other silently stale.
 */
final class UploadShape
{
    /**
     * @param  mixed  $error  The entry's 'error' field: an int for a scalar
     *                        upload, or a tree of ints with the same nesting the
     *                        input name had (files[] one level, files[a][b] two,
     *                        and so on - RequestInterface sets no depth limit).
     */
    public static function hasUpload(mixed $error): bool
    {
        // One predicate, applied to the tree: a branch holds an upload when any
        // child does, a leaf when it is not "no file". This walked exactly one
        // level once, so a nested slot was compared array !== int - always true,
        // so an untouched files[a][b] field reported as present and took the
        // upload path in Validate::form().
        if (is_array($error)) {
            foreach ($error as $slotError) {
                if (self::hasUpload($slotError)) {
                    return true;
                }
            }

            return false;
        }

        return $error !== UPLOAD_ERR_NO_FILE;
    }
}
