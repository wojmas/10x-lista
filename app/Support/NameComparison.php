<?php

namespace App\Support;

/**
 * The single definition of "these two names are the same thing".
 *
 * Four callers depend on it — rejecting a duplicate product, rejecting a
 * duplicate shop, matching a typed-in category against an existing one, and the
 * category seeder. All four must call this; if any drifts apart, products stay
 * deduplicated while categories quietly split ("Nabiał" next to "nabiał"), which
 * is the failure that makes the S-04 recommendation rule point at the wrong shop
 * without reporting an error.
 *
 * What the family means by "the same product" and what this rule therefore
 * ignores: letter case, surrounding whitespace, and runs of whitespace inside
 * the name — "Mleko  2%" is a typo for "Mleko 2%", not a second product.
 *
 * Polish diacritics are significant by decision: "nabial" and "nabiał" are two
 * different names.
 *
 * Deliberately out of scope, both recorded in context/foundation/test-plan.md §7:
 * unicode whitespace (NBSP, zero-width space) is stripped by Laravel's global
 * TrimStrings before validation ever gets here, so it is handled on the HTTP path
 * and nowhere else; and NFD-decomposed diacritics ("a" + combining ogonek) match
 * nothing, because a phone keyboard emits NFC and the paste path is not worth an
 * intl dependency.
 */
class NameComparison
{
    /**
     * Collapse whitespace runs without the /u modifier: the pattern is ASCII, and
     * whitespace bytes cannot occur inside a multibyte UTF-8 sequence, so byte-wise
     * matching is safe. With /u, preg_replace returns null on malformed UTF-8 and
     * an ugly name would become a TypeError instead of an ugly name.
     */
    public static function normalize(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    public static function matches(string $a, string $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }
}
