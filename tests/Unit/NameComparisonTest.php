<?php

namespace Tests\Unit;

use App\Support\NameComparison;
use PHPUnit\Framework\TestCase;

class NameComparisonTest extends TestCase
{
    public function test_it_ignores_letter_case(): void
    {
        $this->assertTrue(NameComparison::matches('Mleko', 'mleko'));
        $this->assertTrue(NameComparison::matches('NABIAŁ', 'nabiał'));
    }

    public function test_it_ignores_surrounding_whitespace(): void
    {
        $this->assertTrue(NameComparison::matches('  mleko  ', 'mleko'));
    }

    /**
     * Polish diacritics are significant by decision — dropping them would make
     * "łoś" and "los" the same name.
     */
    public function test_it_treats_polish_diacritics_as_significant(): void
    {
        $this->assertFalse(NameComparison::matches('nabial', 'nabiał'));
    }

    public function test_it_does_not_match_different_names(): void
    {
        $this->assertFalse(NameComparison::matches('mleko', 'mleko 2%'));
    }
}
