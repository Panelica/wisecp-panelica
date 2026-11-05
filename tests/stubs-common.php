<?php

declare(strict_types=1);

/*
 * The parts of WISECP that are the same in both generations.
 *
 * Utility::redirect() is one of them. It is what sends the customer to the
 * panel, so here it records instead of sending: a test can then say where the
 * customer was sent without a browser, and nothing is printed.
 */

class Utility
{
    /** @var list<string> every URL the module sent someone to */
    public static array $redirects = [];

    public static function redirect(string $url = ''): void
    {
        self::$redirects[] = $url;
    }

    /** @param mixed $value */
    public static function jencode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
