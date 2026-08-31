<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration is off by default (see RegistrationTest, which guards that).
 * This class turns the flag on before the application boots — routes are
 * registered during bootstrap, so setting the config afterwards would not
 * register them — and exercises the path an owner would take if they ever
 * re-enabled sign-up. Without it, nothing catches the registration code
 * rotting against routes that were removed around it.
 */
class RegistrationEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $_ENV['REGISTRATION_ENABLED'] = 'true';
        $_SERVER['REGISTRATION_ENABLED'] = 'true';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($_ENV['REGISTRATION_ENABLED'], $_SERVER['REGISTRATION_ENABLED']);

        parent::tearDown();
    }

    public function test_the_flag_registers_the_route_again(): void
    {
        $this->assertTrue(config('app.registration_enabled'));

        $this->get('/register')->assertOk();
    }

    public function test_registering_lands_on_the_home_page_rather_than_a_missing_route(): void
    {
        $response = $this->post('/register', [
            'name' => 'Wojtek',
            'email' => 'wojtek@example.com',
            'password' => 'haslo-testowe-1',
            'password_confirmation' => 'haslo-testowe-1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
        $this->assertSame(1, User::query()->count());
    }
}
