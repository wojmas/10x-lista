<?php

namespace App\Support;

/**
 * The single definition of "these two names are the same thing".
 *
 * Used in exactly two places — rejecting a duplicate product and matching a
 * typed-in category against an existing one. Both must call this; if the two
 * ever drift apart, products stay deduplicated while categories quietly split
 * ("Nabiał" next to "nabiał"), which is the failure that makes the S-04
 * recommendation rule point at the wrong shop without reporting an error.
 *
 * Polish diacritics are significant by decision: "nabial" and "nabiał" are two
 * different names.
 */
class NameComparison
{
    public static function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public static function matches(string $a, string $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }
}
