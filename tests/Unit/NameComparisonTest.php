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
     * A member who hits the space bar twice has not thought of a second product.
     * The rule has to agree with that, or the list grows two entries that read
     * identically and nobody can tell apart.
     */
    public function test_a_doubled_space_inside_a_name_is_the_same_product(): void
    {
        $this->assertTrue(NameComparison::matches('Mleko  2%', 'Mleko 2%'));
        $this->assertTrue(NameComparison::matches("Mleko\t2%", 'mleko 2%'));
        $this->assertTrue(NameComparison::matches("Warzywa i\n owoce", 'Warzywa i owoce'));
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
